import { parseIsoTimestamp, initCountdown } from './countdown.js';

function toArray(nodeList) {
    if (!nodeList) {
        return [];
    }
    try {
        return Array.prototype.slice.call(nodeList);
    } catch (error) {
        const arr = [];
        for (let i = 0; i < nodeList.length; i += 1) {
            arr.push(nodeList[i]);
        }
        return arr;
    }
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function selectModalElements() {
    const modal = document.querySelector('[data-waitlist-modal]');
    if (!modal) {
        return null;
    }

    return {
        modal,
        dialog: modal.querySelector('[data-waitlist-dialog]'),
        closeButtons: toArray(modal.querySelectorAll('[data-waitlist-close]')),
        form: modal.querySelector('[data-waitlist-form]'),
        emailInput: modal.querySelector('input[name="email"]'),
        nameInput: modal.querySelector('input[name="name"]'),
        messageBox: modal.querySelector('[data-waitlist-message]'),
        heading: modal.querySelector('[data-waitlist-heading]'),
        title: modal.querySelector('[data-waitlist-title]'),
        subtitle: modal.querySelector('[data-waitlist-subtitle]'),
        ctaButton: modal.querySelector('[data-waitlist-cta]'),
    };
}

function showModal(modalElements) {
    const { modal, dialog, messageBox } = modalElements;
    if (!modal || !dialog) {
        return;
    }

    modal.style.display = 'flex';
    modal.classList.add('waitlist-open');
    modal.removeAttribute('aria-hidden');
    requestAnimationFrame(() => {
        dialog.classList.add('waitlist-dialog-visible');
        if (modalElements.emailInput) {
            modalElements.emailInput.focus();
        }
        if (messageBox) {
            messageBox.classList.add('hidden');
            messageBox.textContent = '';
            messageBox.style.display = 'none';
        }
    });
}

function hideModal(modalElements) {
    const { modal, dialog } = modalElements;
    if (!modal || !dialog) {
        return;
    }

    dialog.classList.remove('waitlist-dialog-visible');
    modal.setAttribute('aria-hidden', 'true');
    window.setTimeout(() => {
        modal.classList.remove('waitlist-open');
        modal.style.display = 'none';
    }, 200);
}

function renderMessage(messageBox, type, text) {
    if (!messageBox) {
        return;
    }
    messageBox.textContent = text;
    messageBox.classList.remove('hidden');
    messageBox.style.display = 'block';
    messageBox.style.backgroundColor = type === 'success' ? '#dcfce7' : '#fee2e2';
    messageBox.style.color = type === 'success' ? '#166534' : '#991b1b';
    messageBox.style.borderRadius = '0.5rem';
    messageBox.style.padding = '0.65rem 0.75rem';
}

function buildPayload({ slug, source, nameInput, emailInput }) {
    const payload = {
        slug,
        source,
        context: {
            path: window.location.pathname,
            title: document.title,
        },
    };

    if (nameInput && nameInput.value.trim() !== '') {
        payload.name = nameInput.value.trim();
    }

    if (emailInput) {
        payload.email = emailInput.value.trim();
    }

    return payload;
}

function setTriggerBusy(trigger, busy) {
    if (!trigger) {
        return;
    }

    trigger.setAttribute('data-loading', busy ? 'true' : 'false');
    trigger.setAttribute('aria-busy', busy ? 'true' : 'false');

    if (trigger.classList.contains('waitlist-joined')) {
        return;
    }

    if (trigger.tagName === 'BUTTON') {
        trigger.disabled = !!busy;
    } else if (trigger.tagName === 'A') {
        if (busy) {
            trigger.dataset.restoreHref = trigger.dataset.restoreHref || trigger.getAttribute('href') || '#';
            trigger.removeAttribute('href');
        } else if (trigger.dataset.restoreHref) {
            trigger.setAttribute('href', trigger.dataset.restoreHref);
            delete trigger.dataset.restoreHref;
        }
    }
}

function markTriggerJoined(trigger) {
    if (!trigger) {
        return;
    }

    trigger.classList.add('waitlist-joined');
    trigger.setAttribute('aria-disabled', 'true');
    trigger.removeAttribute('data-loading');
    trigger.removeAttribute('aria-busy');
    if (trigger.dataset.restoreHref) {
        delete trigger.dataset.restoreHref;
    }

    if (!trigger.dataset.originalLabel) {
        trigger.dataset.originalLabel = trigger.textContent || '';
    }

    trigger.textContent = 'Joined';

    if (trigger.tagName === 'BUTTON') {
        trigger.disabled = true;
    } else if (trigger.tagName === 'A') {
        trigger.removeAttribute('href');
    }
}

function submitWaitlist(modalElements, bannerDataset, triggerElement) {
    const form = modalElements.form;
    const messageBox = modalElements.messageBox;
    const emailInput = modalElements.emailInput;
    if (!form || !emailInput) {
        return;
    }

    const payload = buildPayload({
        slug: bannerDataset.dropSlug,
        source: bannerDataset.waitlistSource || 'banner',
        nameInput: modalElements.nameInput,
        emailInput,
    });

    if (!payload.email) {
        renderMessage(messageBox, 'error', 'Please provide an email so we can reach you.');
        emailInput.focus();
        return;
    }

    form.classList.add('waitlist-form-loading');
    setTriggerBusy(triggerElement, true);

    fetch('drop_waitlist_enroll.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(payload),
    })
        .then((response) => response.json().then((json) => ({ status: response.status, json })))
        .then(({ status, json }) => {
            const state = json && typeof json.status === 'string' ? json.status : 'error';
            switch (state) {
                case 'stored':
                    renderMessage(messageBox, 'success', bannerDataset.waitlistSuccess || 'You are confirmed for the drop.');
                    form.reset();
                    markTriggerJoined(triggerElement);
                    break;
                case 'exists':
                    renderMessage(messageBox, 'success', json.message || 'You are already on the list.');
                    break;
                case 'rate_limited':
                    renderMessage(messageBox, 'error', json.message || 'Too many attempts. Try again shortly.');
                    break;
                case 'invalid':
                    renderMessage(messageBox, 'error', json.message || 'Check the details and try again.');
                    emailInput.focus();
                    break;
                default:
                    renderMessage(messageBox, 'error', json && json.message ? json.message : 'We could not save your request.');
                    break;
            }
        })
        .catch(() => {
            renderMessage(messageBox, 'error', 'We could not connect right now. Please try later.');
        })
        .finally(() => {
            form.classList.remove('waitlist-form-loading');
            setTriggerBusy(triggerElement, false);
        });
}

function handleCountdownState(banner, dataset, nowOffsetMs = 0) {
    const countdownWrapper = banner.querySelector('[data-countdown]');
    const targetIso = dataset.countdownTarget;
    if (!countdownWrapper || !dataset.countdownEnabled || !targetIso) {
        return null;
    }

    const targetDate = parseIsoTimestamp(targetIso);
    if (!targetDate) {
        return null;
    }

    return initCountdown(countdownWrapper, targetDate, {
        onComplete: () => {
            const labelNode = countdownWrapper.querySelector('[data-countdown-label]');
            if (labelNode) {
                labelNode.textContent = 'Drop live';
            }
            countdownWrapper.classList.add('waitlist-countdown-complete');
        },
        getNow: () => Date.now() + nowOffsetMs,
    });
}

function hydrateDropBanner() {
    const banner = document.querySelector('[data-drop-banner="true"]');
    if (!banner) {
        return;
    }

    const dataset = {
        dropSlug: banner.getAttribute('data-drop-slug') || '',
        countdownTarget: banner.getAttribute('data-countdown-target') || '',
        countdownEnabled: banner.getAttribute('data-countdown-enabled') === 'true',
        waitlistEnabled: banner.getAttribute('data-waitlist-enabled') === 'true',
        waitlistSuccess: banner.getAttribute('data-waitlist-success') || '',
        waitlistSource: banner.getAttribute('data-waitlist-source') || 'banner',
        dropLabel: banner.getAttribute('data-drop-label') || '',
    };

    const serverNowIso = banner.getAttribute('data-server-now') || '';
    let nowOffsetMs = 0;
    if (serverNowIso) {
        const serverNow = parseIsoTimestamp(serverNowIso);
        if (serverNow) {
            nowOffsetMs = serverNow.getTime() - Date.now();
        }
    }

    handleCountdownState(banner, dataset, nowOffsetMs);

    if (!dataset.waitlistEnabled || !dataset.dropSlug) {
        return;
    }

    const modalElements = selectModalElements();
    if (!modalElements) {
        return;
    }

    if (dataset.dropLabel) {
        if (modalElements.heading) {
            modalElements.heading.textContent = `Drop Waitlist · ${dataset.dropLabel}`;
        }
        if (modalElements.title) {
            modalElements.title.textContent = `Reserve your spot for ${dataset.dropLabel}`;
        }
    }

    const triggerButtons = toArray(banner.querySelectorAll('[data-waitlist-trigger]'));
    if (!triggerButtons.length) {
        return;
    }

    let activeTriggerButton = null;

    triggerButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            activeTriggerButton = btn;
            showModal(modalElements);
        });
    });

    (modalElements.closeButtons || []).forEach((btn) => {
        btn.addEventListener('click', () => hideModal(modalElements));
    });

    modalElements.modal.addEventListener('click', (event) => {
        if (event.target === modalElements.modal) {
            hideModal(modalElements);
        }
    });

    if (modalElements.form) {
        modalElements.form.addEventListener('submit', (event) => {
            event.preventDefault();
            const buttonForState = activeTriggerButton || triggerButtons[0];
            submitWaitlist(modalElements, dataset, buttonForState);
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hydrateDropBanner);
} else {
    hydrateDropBanner();
}
