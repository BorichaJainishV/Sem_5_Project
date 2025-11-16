const DEFAULT_INTERVAL = 1000;

/**
 * Parse an ISO timestamp into a Date instance while guarding against invalid values.
 */
export function parseIsoTimestamp(isoString) {
    if (typeof isoString !== 'string' || isoString.trim() === '') {
        return null;
    }

    const parsed = new Date(isoString);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed;
}

/**
 * Format the remaining milliseconds into day/hour/minute/second buckets.
 */
function diffToParts(msRemaining) {
    const totalSeconds = Math.max(0, Math.floor(msRemaining / 1000));
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return { days, hours, minutes, seconds };
}

function padTwo(value) {
    return value.toString().padStart(2, '0');
}

/**
 * Initialize a ticking countdown for the provided element.
 * @param {HTMLElement} container - root element containing [data-countdown-part] spans.
 * @param {Date} targetDate - timestamp to count down toward.
 * @param {Object} options
 * @param {function} [options.onComplete] - invoked once the countdown reaches zero.
 * @param {number} [options.interval] - tick interval in ms (defaults to 1000ms).
 */
export function initCountdown(container, targetDate, options = {}) {
    if (!container || !(container instanceof HTMLElement)) {
        return null;
    }
    if (!(targetDate instanceof Date) || Number.isNaN(targetDate.getTime())) {
        return null;
    }

    const onComplete = typeof options.onComplete === 'function' ? options.onComplete : null;
    const interval = typeof options.interval === 'number' ? Math.max(250, options.interval) : DEFAULT_INTERVAL;
    const getNow = typeof options.getNow === 'function' ? options.getNow : () => Date.now();

    const partMap = {
        days: container.querySelector('[data-countdown-part="days"]'),
        hours: container.querySelector('[data-countdown-part="hours"]'),
        minutes: container.querySelector('[data-countdown-part="minutes"]'),
        seconds: container.querySelector('[data-countdown-part="seconds"]'),
    };

    let timerId = null;
    let completed = false;

    const update = () => {
    const now = getNow();
        const msRemaining = targetDate.getTime() - now;

        if (msRemaining <= 0) {
            if (!completed) {
                Object.values(partMap).forEach((node) => {
                    if (node) {
                        node.textContent = '00';
                    }
                });
                completed = true;
                if (onComplete) {
                    onComplete();
                }
            }
            return;
        }

        const { days, hours, minutes, seconds } = diffToParts(msRemaining);
        if (partMap.days) partMap.days.textContent = padTwo(days);
        if (partMap.hours) partMap.hours.textContent = padTwo(hours);
        if (partMap.minutes) partMap.minutes.textContent = padTwo(minutes);
        if (partMap.seconds) partMap.seconds.textContent = padTwo(seconds);
    };

    update();
    timerId = window.setInterval(update, interval);

    return {
        destroy() {
            if (timerId !== null) {
                window.clearInterval(timerId);
                timerId = null;
            }
        },
        isComplete() {
            return completed;
        },
    };
}
