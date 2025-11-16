<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/core/style_quiz_helpers.php';

ensureStyleQuizResultsTable($conn);

$customerId = $_SESSION['customer_id'] ?? null;
$storedPersona = null;

if ($customerId) {
    $quizStmt = $conn->prepare('SELECT style_choice, palette_choice, goal_choice, persona_label, persona_summary, recommendations_json, source_label, submitted_at FROM style_quiz_results WHERE customer_id = ? LIMIT 1');
    if ($quizStmt) {
        $quizStmt->bind_param('i', $customerId);
        if ($quizStmt->execute()) {
            $quizResult = $quizStmt->get_result();
            $quizRow = $quizResult ? $quizResult->fetch_assoc() : null;
            if ($quizRow) {
                $decoded = json_decode($quizRow['recommendations_json'] ?? '[]', true);
                $storedPersona = [
                    'style' => $quizRow['style_choice'] ?? '',
                    'palette' => $quizRow['palette_choice'] ?? '',
                    'goal' => $quizRow['goal_choice'] ?? '',
                    'persona_label' => $quizRow['persona_label'] ?? '',
                    'persona_summary' => $quizRow['persona_summary'] ?? '',
                    'recommendations' => is_array($decoded) ? $decoded : [],
                    'source' => $quizRow['source_label'] ?? 'account',
                    'captured_at' => $quizRow['submitted_at'] ?? null,
                ];
            }
            if ($quizResult) {
                $quizResult->free();
            }
        }
        $quizStmt->close();
    }
}

if (!$storedPersona && isset($_SESSION['style_quiz_last_result']) && is_array($_SESSION['style_quiz_last_result'])) {
    $sessionPersona = $_SESSION['style_quiz_last_result'];
    $storedPersona = [
        'style' => $sessionPersona['style'] ?? '',
        'palette' => $sessionPersona['palette'] ?? '',
        'goal' => $sessionPersona['goal'] ?? '',
        'persona_label' => $sessionPersona['persona_label'] ?? '',
        'persona_summary' => $sessionPersona['persona_summary'] ?? '',
        'recommendations' => is_array($sessionPersona['recommendations'] ?? null) ? $sessionPersona['recommendations'] : [],
        'source' => $sessionPersona['source'] ?? 'session',
        'captured_at' => $sessionPersona['captured_at'] ?? null,
    ];
}

$quizPrefill = [
    'style' => $storedPersona['style'] ?? '',
    'palette' => $storedPersona['palette'] ?? '',
    'goal' => $storedPersona['goal'] ?? '',
    'persona_label' => $storedPersona['persona_label'] ?? '',
    'persona_summary' => $storedPersona['persona_summary'] ?? '',
    'recommendations' => $storedPersona['recommendations'] ?? [],
    'source' => $storedPersona['source'] ?? '',
    'captured_at' => $storedPersona['captured_at'] ?? null,
];

$prefillJson = json_encode($quizPrefill, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

include 'header.php';
?>

<main class="stylist-page">
    <section class="stylist-hero">
        <div class="container stylist-hero__grid">
            <div class="stylist-hero__copy">
                <span class="stylist-hero__eyebrow">Stylist in Your Inbox</span>
                <h1>Let our team curate your next Mystic capsule.</h1>
                <p>Answer three fast prompts and we will translate your vibe into a shoppable bundle. We will also sync your persona so upcoming drops and emails stay on-brand.</p>
                <ul class="stylist-hero__bullets">
                    <li>Personalized bundles pulled from live inventory</li>
                    <li>Saved to your account for faster future drops</li>
                    <li>Optional email follow-up with refreshed picks</li>
                </ul>
                <a href="#stylist-quiz" class="btn btn-primary btn-lg stylist-hero__cta">Start the style check</a>
            </div>
            <div class="stylist-hero__card" aria-hidden="true">
                <div class="stylist-hero__badge">New</div>
                <div class="stylist-hero__card-body">
                    <p class="stylist-hero__card-title">Inbox Styling</p>
                    <p class="stylist-hero__card-summary">Tap through the quiz and we will deliver curated looks straight to your inbox.</p>
                    <div class="stylist-hero__card-avatars">
                        <span class="avatar avatar--teal"></span>
                        <span class="avatar avatar--sunset"></span>
                        <span class="avatar avatar--slate"></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="stylist-quiz-section" id="stylist-quiz">
        <div class="container">
            <div class="stylist-quiz-card" data-stylist-quiz data-quiz-source="inbox_flow">
                <header class="stylist-quiz-header">
                    <span class="stylist-quiz-label">Three prompts. Instant vibe check.</span>
                    <h2>Tell us how you are styling your next drop.</h2>
                    <p>We map each answer to our inventory tags and queue an on-brand kit. Want to switch things up later? Retake anytime.</p>
                </header>

                <div class="stylist-quiz-progress" aria-hidden="true">
                    <div class="stylist-quiz-progress__track">
                        <div class="stylist-quiz-progress__fill" data-progress-fill></div>
                    </div>
                    <span class="stylist-quiz-progress__label" data-progress-label>Step 1 of 3</span>
                </div>

                <div class="stylist-quiz-steps">
                    <div class="stylist-quiz-step is-active" data-quiz-step="style">
                        <h3>Which vibe feels closest to your go-to outfits?</h3>
                        <div class="stylist-quiz-options">
                            <button type="button" class="stylist-quiz-option" data-question="style" data-answer="street">
                                <span class="stylist-quiz-option__emoji" aria-hidden="true">🌆</span>
                                <span class="stylist-quiz-option__title">Urban Creator</span>
                                <span class="stylist-quiz-option__copy">Oversized layers, street graphics, limited drops.</span>
                            </button>
                            <button type="button" class="stylist-quiz-option" data-question="style" data-answer="minimal">
                                <span class="stylist-quiz-option__emoji" aria-hidden="true">✨</span>
                                <span class="stylist-quiz-option__title">Clean Classic</span>
                                <span class="stylist-quiz-option__copy">Tailored basics, elevated essentials, neutral fits.</span>
                            </button>
                            <button type="button" class="stylist-quiz-option" data-question="style" data-answer="bold">
                                <span class="stylist-quiz-option__emoji" aria-hidden="true">🚀</span>
                                <span class="stylist-quiz-option__title">Statement Maker</span>
                                <span class="stylist-quiz-option__copy">High-impact prints, neon hits, spotlight pieces.</span>
                            </button>
                        </div>
                    </div>

                    <div class="stylist-quiz-step" data-quiz-step="palette">
                        <h3>Which color story are you leaning into right now?</h3>
                        <div class="stylist-quiz-options">
                            <button type="button" class="stylist-quiz-option" data-question="palette" data-answer="monochrome">
                                <span class="stylist-quiz-option__swatch swatch--mono" aria-hidden="true"></span>
                                <span class="stylist-quiz-option__title">Monochrome Sleek</span>
                                <span class="stylist-quiz-option__copy">Black, white, charcoal with sharp contrasts.</span>
                            </button>
                            <button type="button" class="stylist-quiz-option" data-question="palette" data-answer="earth">
                                <span class="stylist-quiz-option__swatch swatch--earth" aria-hidden="true"></span>
                                <span class="stylist-quiz-option__title">Earthy Balance</span>
                                <span class="stylist-quiz-option__copy">Clay, olive, sand with natural texture.</span>
                            </button>
                            <button type="button" class="stylist-quiz-option" data-question="palette" data-answer="vivid">
                                <span class="stylist-quiz-option__swatch swatch--vivid" aria-hidden="true"></span>
                                <span class="stylist-quiz-option__title">Vivid Gradient</span>
                                <span class="stylist-quiz-option__copy">Sunset gradients, electric brights, color-pop moments.</span>
                            </button>
                        </div>
                    </div>

                    <div class="stylist-quiz-step" data-quiz-step="goal">
                        <h3>What are you dreaming up with these pieces?</h3>
                        <div class="stylist-quiz-options">
                            <button type="button" class="stylist-quiz-option" data-question="goal" data-answer="everyday">
                                <span class="stylist-quiz-option__emoji" aria-hidden="true">🔁</span>
                                <span class="stylist-quiz-option__title">Dialed Everyday Rotation</span>
                                <span class="stylist-quiz-option__copy">Build reliable looks you can remix all week.</span>
                            </button>
                            <button type="button" class="stylist-quiz-option" data-question="goal" data-answer="launch">
                                <span class="stylist-quiz-option__emoji" aria-hidden="true">📣</span>
                                <span class="stylist-quiz-option__title">Launch-Ready Merch Drop</span>
                                <span class="stylist-quiz-option__copy">Prep limited merch or event-exclusive heat.</span>
                            </button>
                            <button type="button" class="stylist-quiz-option" data-question="goal" data-answer="gift">
                                <span class="stylist-quiz-option__emoji" aria-hidden="true">🎁</span>
                                <span class="stylist-quiz-option__title">Premium Gift Kit</span>
                                <span class="stylist-quiz-option__copy">Surprise someone with a custom bundle that feels luxe.</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="stylist-quiz-result" data-quiz-result>
                    <div class="stylist-quiz-result__copy">
                        <h3 data-quiz-persona>Your Mystic bundle is ready</h3>
                        <p data-quiz-summary></p>
                        <p class="stylist-quiz-result__meta" data-quiz-meta></p>
                    </div>
                    <div class="stylist-quiz-recommendations" data-quiz-recommendations></div>
                    <div class="stylist-quiz-actions">
                        <button type="button" class="btn btn-outline" data-quiz-retake>Retake quiz</button>
                        <a href="shop.php#styleQuiz" class="btn btn-primary">Shop the vibe</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="stylist-followup">
        <div class="container stylist-followup__grid">
            <div>
                <h3>What happens next?</h3>
                <p>We log your persona and use it to recommend limited drops, design templates, and bundle pricing in your account dashboard.</p>
                <ul class="stylist-followup__list">
                    <li><strong>Bundle alerts:</strong> We nudge you when new inventory aligns with your picks.</li>
                    <li><strong>Stylist emails:</strong> Opt-in to get refreshed suggestions tailored to your vibe.</li>
                    <li><strong>Design concierge:</strong> Want help? Reply to the email and our stylist squad will co-create.</li>
                </ul>
            </div>
            <div class="stylist-followup__card">
                <h4>Need that follow-up email?</h4>
                <p>Make sure your account email is current so the capsule lands where you want it.</p>
                <a href="account.php" class="btn btn-secondary">Review account details</a>
            </div>
        </div>
    </section>
</main>

<script>
window.__stylistQuizPrefill = <?php echo $prefillJson ?: 'null'; ?>;
</script>
<script>
(function () {
    const quizRoot = document.querySelector('[data-stylist-quiz]');
    if (!quizRoot) {
        return;
    }

    const steps = Array.from(quizRoot.querySelectorAll('.stylist-quiz-step'));
    const progressFill = quizRoot.querySelector('[data-progress-fill]');
    const progressLabel = quizRoot.querySelector('[data-progress-label]');
    const resultPanel = quizRoot.querySelector('[data-quiz-result]');
    const personaLabel = quizRoot.querySelector('[data-quiz-persona]');
    const personaSummary = quizRoot.querySelector('[data-quiz-summary]');
    const personaMeta = quizRoot.querySelector('[data-quiz-meta]');
    const recommendationsContainer = quizRoot.querySelector('[data-quiz-recommendations]');
    const retakeButton = quizRoot.querySelector('[data-quiz-retake]');
    const submitSource = quizRoot.getAttribute('data-quiz-source') || 'inbox_flow';
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    let currentStepIndex = 0;
    const answers = {};

    const prefill = window.__stylistQuizPrefill && typeof window.__stylistQuizPrefill === 'object'
        ? window.__stylistQuizPrefill
        : null;

    function updateProgressLabel() {
        if (!progressLabel) {
            return;
        }
        const stepNumber = currentStepIndex + 1;
        const totalSteps = steps.length || 1;
        progressLabel.textContent = 'Step ' + stepNumber + ' of ' + totalSteps;
    }

    function updateProgressFill() {
        if (!progressFill || steps.length === 0) {
            return;
        }
        const percent = Math.round(((currentStepIndex + 1) / steps.length) * 100);
        progressFill.style.width = percent + '%';
    }

    function showStep(index) {
        steps.forEach((step, idx) => {
            step.classList.toggle('is-active', idx === index);
        });
        currentStepIndex = index;
        updateProgressFill();
        updateProgressLabel();
    }

    function renderRecommendations(recommendations) {
        if (!recommendationsContainer) {
            return;
        }
        recommendationsContainer.innerHTML = '';

        if (!Array.isArray(recommendations) || recommendations.length === 0) {
            const muted = document.createElement('p');
            muted.className = 'stylist-quiz-empty';
            muted.textContent = 'We will follow up with a custom capsule shortly. Meanwhile, explore the shop to start building looks.';
            recommendationsContainer.appendChild(muted);
            return;
        }

        recommendations.forEach((rec) => {
            const card = document.createElement('article');
            card.className = 'stylist-rec-card';

            const title = document.createElement('h4');
            title.textContent = rec.name || 'Recommended pick';
            card.appendChild(title);

            if (rec.image_url) {
                const img = document.createElement('img');
                img.src = rec.image_url;
                img.alt = rec.name ? rec.name + ' preview' : 'Recommended product preview';
                card.appendChild(img);
            }

            if (rec.reason) {
                const reason = document.createElement('p');
                reason.className = 'stylist-rec-reason';
                reason.textContent = rec.reason;
                card.appendChild(reason);
            }

            if (typeof rec.price !== 'undefined' && rec.price !== null) {
                const parsedPrice = Number(rec.price);
                if (Number.isFinite(parsedPrice)) {
                    const price = document.createElement('p');
                    price.className = 'stylist-rec-price';
                    price.textContent = '₹' + parsedPrice.toFixed(2);
                    card.appendChild(price);
                }
            }

            if (rec.inventory_id) {
                const actions = document.createElement('div');
                actions.className = 'stylist-rec-actions';

                const addLink = document.createElement('a');
                addLink.className = 'btn btn-secondary btn-sm';
                addLink.href = 'cart_handler.php?action=add&id=' + encodeURIComponent(rec.inventory_id) + '&redirect=stylist_inbox.php&csrf_token=' + encodeURIComponent(csrfToken);
                addLink.textContent = 'Add to cart';
                actions.appendChild(addLink);

                const viewLink = document.createElement('a');
                viewLink.className = 'btn btn-outline btn-sm';
                viewLink.href = 'shop.php#styleQuiz';
                viewLink.textContent = 'See details';
                actions.appendChild(viewLink);

                card.appendChild(actions);
            }

            recommendationsContainer.appendChild(card);
        });
    }

    function showResult(personaData) {
        if (!resultPanel) {
            return;
        }
        resultPanel.classList.add('is-visible');
        if (personaLabel) {
            personaLabel.textContent = personaData.personaLabel || 'Your Mystic bundle is ready';
        }
        if (personaSummary) {
            personaSummary.textContent = personaData.personaSummary || '';
        }
        if (personaMeta) {
            if (personaData.capturedAt) {
                personaMeta.textContent = 'Saved on ' + personaData.capturedAt + ' · Source: ' + (personaData.source || 'quiz');
                personaMeta.classList.remove('is-hidden');
            } else {
                personaMeta.textContent = '';
                personaMeta.classList.add('is-hidden');
            }
        }
        renderRecommendations(personaData.recommendations || []);
    }

    function resetQuiz() {
        Object.keys(answers).forEach((key) => {
            delete answers[key];
        });
        steps.forEach((step) => {
            const options = step.querySelectorAll('.stylist-quiz-option');
            options.forEach((option) => option.classList.remove('is-active'));
        });
        if (resultPanel) {
            resultPanel.classList.remove('is-visible');
        }
        showStep(0);
        if (personaMeta) {
            personaMeta.classList.add('is-hidden');
            personaMeta.textContent = '';
        }
        if (personaSummary) {
            personaSummary.textContent = '';
        }
    }

    function submitQuiz() {
        if (!answers.style || !answers.palette || !answers.goal) {
            return;
        }

        if (resultPanel) {
            resultPanel.classList.add('is-visible');
            if (personaLabel) {
                personaLabel.textContent = 'One sec — curating your bundle...';
            }
            if (personaSummary) {
                personaSummary.textContent = 'We are matching your answers with inventory that fits your vibe.';
            }
            if (personaMeta) {
                personaMeta.classList.add('is-hidden');
                personaMeta.textContent = '';
            }
        }

        const payload = {
            style: answers.style,
            palette: answers.palette,
            goal: answers.goal,
            source: submitSource,
        };

        fetch('style_quiz_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data || data.success !== true) {
                    if (personaLabel) {
                        personaLabel.textContent = 'We hit a snag matching your style.';
                    }
                    if (personaSummary) {
                        const errorText = data && data.message ? data.message : 'Please retry or reach out for a custom concierge.';
                        personaSummary.textContent = errorText;
                    }
                    renderRecommendations([]);
                    return;
                }

                showResult({
                    personaLabel: data.personaLabel,
                    personaSummary: data.personaSummary,
                    recommendations: data.recommendations,
                    source: data.source,
                    capturedAt: data.submittedAt,
                });
            })
            .catch(() => {
                if (personaLabel) {
                    personaLabel.textContent = 'We hit a snag matching your style.';
                }
                if (personaSummary) {
                    personaSummary.textContent = 'Please refresh the page or try again shortly.';
                }
                renderRecommendations([]);
            });
    }

    steps.forEach((step, stepIndex) => {
        const options = step.querySelectorAll('.stylist-quiz-option');
        options.forEach((option) => {
            option.addEventListener('click', () => {
                const question = option.getAttribute('data-question');
                const answer = option.getAttribute('data-answer');
                if (!question || !answer) {
                    return;
                }

                step.querySelectorAll('.stylist-quiz-option').forEach((btn) => btn.classList.remove('is-active'));
                option.classList.add('is-active');
                answers[question] = answer;

                const nextIndex = stepIndex + 1;
                if (nextIndex < steps.length) {
                    showStep(nextIndex);
                } else {
                    submitQuiz();
                }
            });
        });
    });

    if (retakeButton) {
        retakeButton.addEventListener('click', () => {
            resetQuiz();
        });
    }

    function hydrateFromPrefill(prefillData) {
        if (!prefillData) {
            showStep(0);
            return;
        }

        ['style', 'palette', 'goal'].forEach((key) => {
            if (!prefillData[key]) {
                return;
            }
            answers[key] = prefillData[key];
            const target = quizRoot.querySelector('.stylist-quiz-option[data-question="' + key + '"][data-answer="' + prefillData[key] + '"]');
            if (target) {
                target.classList.add('is-active');
            }
        });

        if (prefillData.persona_label || prefillData.persona_summary) {
            showResult({
                personaLabel: prefillData.persona_label,
                personaSummary: prefillData.persona_summary,
                recommendations: prefillData.recommendations,
                source: prefillData.source,
                capturedAt: prefillData.captured_at,
            });
            showStep(steps.length - 1);
        } else if (answers.goal) {
            showStep(steps.length - 1);
        } else if (answers.palette) {
            showStep(1);
        } else {
            showStep(0);
        }
    }

    hydrateFromPrefill(prefill);
})();
</script>

<?php include 'footer.php'; ?>
