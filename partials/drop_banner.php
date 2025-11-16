<?php
// ---------------------------------------------------------------------
// partials/drop_banner.php - Renders drop-mode flash banner markup
// ---------------------------------------------------------------------

if (!function_exists('render_drop_banner')) {
    /**
     * Render the storefront drop banner with countdown and waitlist call-to-action.
     */
    function render_drop_banner(array $banner, string $gradientClass, string $bannerId, string $variantLabel, array $options = []): string
    {
        $message = trim((string) ($banner['message'] ?? ''));
        if ($message === '') {
            return '';
        }

        $subtext = trim((string) ($banner['subtext'] ?? ''));
        $badge = trim((string) ($banner['badge'] ?? ''));
        $cta = trim((string) ($banner['cta'] ?? ''));
        $href = trim((string) ($banner['href'] ?? ''));
        $dismissible = !empty($banner['dismissible']);

        $countdownTargetIso = trim((string) ($banner['countdown_target_iso'] ?? ''));
        $countdownLabel = trim((string) ($banner['countdown_label'] ?? 'Drop starts in'));
    $countdownEnabled = !empty($banner['countdown_enabled']);
        $dropSlug = trim((string) ($banner['waitlist_slug'] ?? $banner['drop_slug'] ?? ''));
    $dropLabel = trim((string) ($banner['drop_label'] ?? ''));

        $waitlistEnabled = !empty($banner['waitlist_enabled']);
        $waitlistButtonLabel = trim((string) ($banner['waitlist_button_label'] ?? 'Join Waitlist'));
        $waitlistSuccessCopy = trim((string) ($banner['waitlist_success_copy'] ?? 'You are on the list.'));
        $waitlistSource = trim((string) ($banner['waitlist_source'] ?? 'banner'));

        $dropStory = trim((string) ($banner['drop_story'] ?? ''));
        $dropTeaser = trim((string) ($banner['drop_teaser'] ?? ''));
        $rawHighlights = $banner['drop_highlights'] ?? [];
        if (is_string($rawHighlights)) {
            $rawHighlights = preg_split('/\r\n|\r|\n/', $rawHighlights) ?: [];
        }
        $dropHighlights = [];
        if (is_array($rawHighlights)) {
            foreach ($rawHighlights as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $dropHighlights[] = $item;
                }
            }
        }
        $dropAccessNotes = trim((string) ($banner['drop_access_notes'] ?? ''));
        $dropMediaUrl = trim((string) ($banner['drop_media_url'] ?? ''));
        $mediaPath = $dropMediaUrl !== '' ? (string) (parse_url($dropMediaUrl, PHP_URL_PATH) ?? '') : '';
        $mediaIsVideo = $dropMediaUrl !== '' && $mediaPath !== '' && preg_match('/\.(mp4|webm|ogg)$/i', $mediaPath);

        $primaryHighlight = '';
        if (!empty($dropHighlights)) {
            $firstHighlight = reset($dropHighlights);
            $primaryHighlight = is_string($firstHighlight) ? trim($firstHighlight) : trim((string) $firstHighlight);
        }

        $dropStoryPreview = '';
        if ($dropStory !== '') {
            $storyLines = preg_split('/\r\n|\r|\n/', $dropStory) ?: [];
            $dropStoryPreview = trim((string) ($storyLines[0] ?? ''));
            if ($dropStoryPreview === '') {
                $dropStoryPreview = $dropStory;
            }
        }

        $showcaseTeaser = $dropTeaser !== ''
            ? $dropTeaser
            : ($primaryHighlight !== '' ? $primaryHighlight : $dropStoryPreview);

        $scheduleStartIso = trim((string) ($banner['schedule_start_iso'] ?? ''));
        $scheduleEndIso = trim((string) ($banner['schedule_end_iso'] ?? ''));
        $state = trim((string) ($banner['state'] ?? 'live'));

        $stateLabel = '';
        if ($state === 'upcoming') {
            $stateLabel = 'Upcoming drop';
        } elseif ($state === 'live') {
            $stateLabel = 'Now live';
        } elseif ($state === 'ended') {
            $stateLabel = 'Closed';
        }

        $timezoneName = '';
        $timezoneAbbr = '';
        $timezoneObject = null;
        try {
            if (function_exists('mystic_banner_timezone')) {
                $timezoneObject = mystic_banner_timezone();
            } elseif (function_exists('mystic_app_timezone')) {
                $timezoneObject = mystic_app_timezone();
            } else {
                $timezoneObject = new DateTimeZone(date_default_timezone_get() ?: 'UTC');
            }

            if ($timezoneObject instanceof DateTimeZone) {
                $timezoneName = $timezoneObject->getName();
                $now = new DateTime('now', $timezoneObject);
                $timezoneAbbr = $now->format('T');
            }
        } catch (Throwable $timezoneError) {
            $timezoneObject = null;
            $timezoneName = date_default_timezone_get() ?: 'UTC';
            $timezoneAbbr = 'UTC';
        }

        $formatSchedule = static function (?string $iso) use ($timezoneAbbr, $timezoneObject, $timezoneName) {
            if ($iso === null || $iso === '') {
                return '';
            }

            $timestamp = strtotime($iso);
            if ($timestamp === false) {
                return $iso;
            }

            try {
                $dt = new DateTimeImmutable('@' . $timestamp);
                if ($timezoneObject instanceof DateTimeZone) {
                    $dt = $dt->setTimezone($timezoneObject);
                } elseif ($timezoneName !== '') {
                    $dt = $dt->setTimezone(new DateTimeZone($timezoneName));
                }
                $formatted = $dt->format('M j, Y · g:ia');
            } catch (Throwable $formatError) {
                $formatted = date('M j, Y · g:ia', $timestamp);
            }

            if ($timezoneAbbr !== '') {
                $formatted .= ' ' . $timezoneAbbr;
            }

            return $formatted;
        };

        $stateAnnouncement = '';
        $stateModifier = $state !== '' ? $state : 'default';
        if ($state === 'live') {
            $stateAnnouncement = 'Drop is live now';
            if ($scheduleEndIso !== '') {
                $formattedEnd = $formatSchedule($scheduleEndIso);
                if ($formattedEnd !== '') {
                    $stateAnnouncement .= ' · Ends ' . $formattedEnd;
                }
            }
        } elseif ($state === 'upcoming') {
            $scheduled = $formatSchedule($scheduleStartIso);
            $stateAnnouncement = $scheduled !== '' ? 'Opens on ' . $scheduled : 'Drop kickoff coming soon';
        } elseif ($state === 'ended') {
            $stateAnnouncement = 'Drop has closed';
        }

        $metaItems = [];
        if ($dropLabel !== '') {
            $metaItems[] = [
                'label' => 'Drop',
                'value' => $dropLabel,
            ];
        }
        if ($scheduleStartIso !== '') {
            $startTimestamp = strtotime($scheduleStartIso);
            $displayStart = $formatSchedule($scheduleStartIso);
            $metaItems[] = [
                'label' => 'Opens',
                'value' => $displayStart !== '' ? $displayStart : ($startTimestamp ? date('M j, Y · g:ia', $startTimestamp) : $scheduleStartIso),
            ];
        }
        if ($scheduleEndIso !== '') {
            $endTimestamp = strtotime($scheduleEndIso);
            $displayEnd = $formatSchedule($scheduleEndIso);
            $metaItems[] = [
                'label' => 'Closes',
                'value' => $displayEnd !== '' ? $displayEnd : ($endTimestamp ? date('M j, Y · g:ia', $endTimestamp) : $scheduleEndIso),
            ];
        }
        if ($dropSlug !== '') {
            $metaItems[] = [
                'label' => 'Drop code',
                'value' => strtoupper($dropSlug),
            ];
        }
        if (!empty($banner['timezone'])) {
            $metaItems[] = [
                'label' => 'Timezone',
                'value' => trim($banner['timezone'] . ($timezoneAbbr !== '' ? ' (' . $timezoneAbbr . ')' : '')),
            ];
        } elseif ($timezoneName !== '') {
            $metaItems[] = [
                'label' => 'Timezone',
                'value' => trim($timezoneName . ($timezoneAbbr !== '' ? ' (' . $timezoneAbbr . ')' : '')),
            ];
        }

        $hasCountdown = $countdownEnabled && $countdownTargetIso !== '';
        $hasWaitlist = $waitlistEnabled && $dropSlug !== '';
        $hasInsights = $dropStory !== '' || !empty($dropHighlights) || $dropAccessNotes !== '' || $hasWaitlist;

        $dismissVersion = (string) ($options['dismiss_version'] ?? $banner['updated_at'] ?? $banner['countdown_target_ts'] ?? time());
        $serverNowIso = trim((string) ($options['server_now_iso'] ?? date(DateTimeInterface::ATOM)));

        $waitlistHref = '';
        if ($hasWaitlist) {
            $waitlistHref = 'drop_waitlist.php';
            if ($dropSlug !== '') {
                $waitlistHref .= '?drop=' . rawurlencode($dropSlug);
            }
        }

        $dataAttrs = [
            'data-banner-id' => $bannerId,
            'data-drop-banner' => 'true',
            'data-drop-state' => $state,
            'data-drop-slug' => $dropSlug,
            'data-countdown-target' => $countdownTargetIso,
            'data-countdown-enabled' => $countdownEnabled ? 'true' : 'false',
            'data-countdown-label' => $countdownLabel,
            'data-schedule-start' => $scheduleStartIso,
            'data-schedule-end' => $scheduleEndIso,
            'data-waitlist-enabled' => $waitlistEnabled ? 'true' : 'false',
            'data-waitlist-success' => $waitlistSuccessCopy,
            'data-waitlist-source' => $waitlistSource,
            'data-dismiss-version' => $dismissVersion,
            'data-server-now' => $serverNowIso,
            'data-drop-live' => $state === 'live' ? 'true' : 'false',
            'data-drop-timezone' => $timezoneName,
            'data-drop-timezone-abbr' => $timezoneAbbr,
            'data-drop-label' => $dropLabel,
        ];

        // Remove empty data attributes to keep markup lean.
        $filteredAttrs = array_filter($dataAttrs, static function ($value) {
            return $value !== '' && $value !== null;
        });

        $attrHtml = '';
        foreach ($filteredAttrs as $key => $value) {
            $attrHtml .= ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
        }

        ob_start();
        $showcaseClasses = 'drop-showcase';
        if ($dropMediaUrl === '') {
            $showcaseClasses .= ' drop-showcase--no-media';
        }
        ?>
        <section class="drop-spotlight bg-gradient-to-r <?php echo htmlspecialchars($gradientClass, ENT_QUOTES); ?> text-white"<?php echo $attrHtml; ?>>
            <div class="container drop-spotlight__inner">
                <?php if ($dismissible): ?>
                    <button type="button" data-dismiss-banner class="drop-spotlight__dismiss" aria-label="Dismiss drop announcement">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                <?php endif; ?>

                <div class="drop-chip-row">
                    <span class="drop-chip drop-chip--variant"><?php echo htmlspecialchars($variantLabel, ENT_QUOTES); ?></span>
                    <?php if ($badge !== ''): ?>
                        <span class="drop-chip drop-chip--badge"><?php echo htmlspecialchars($badge, ENT_QUOTES); ?></span>
                    <?php endif; ?>
                    <?php if ($stateLabel !== ''): ?>
                        <span class="drop-chip drop-chip--state"><?php echo htmlspecialchars($stateLabel, ENT_QUOTES); ?></span>
                    <?php endif; ?>
                </div>

                <div class="<?php echo htmlspecialchars($showcaseClasses, ENT_QUOTES); ?>">
                    <div class="drop-showcase__content">
                        <header class="drop-showcase__header">
                            <h2 class="drop-showcase__title"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></h2>
                            <?php if ($subtext !== ''): ?>
                                <p class="drop-showcase__subtitle"><?php echo htmlspecialchars($subtext, ENT_QUOTES); ?></p>
                            <?php endif; ?>
                            <?php if ($showcaseTeaser !== ''): ?>
                                <p class="drop-showcase__teaser"><?php echo htmlspecialchars($showcaseTeaser, ENT_QUOTES); ?></p>
                            <?php endif; ?>
                        </header>

                        <?php if ($stateAnnouncement !== ''): ?>
                            <div class="drop-showcase__status drop-showcase__status--<?php echo htmlspecialchars($stateModifier, ENT_QUOTES); ?>">
                                <span class="drop-showcase__status-dot" aria-hidden="true"></span>
                                <span><?php echo htmlspecialchars($stateAnnouncement, ENT_QUOTES); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($hasWaitlist || ($cta !== '' && $href !== '')): ?>
                            <div class="drop-showcase__actions">
                                <?php if ($hasWaitlist && $waitlistHref !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($waitlistHref, ENT_QUOTES); ?>" class="btn btn-primary">
                                        <?php echo htmlspecialchars($waitlistButtonLabel, ENT_QUOTES); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($cta !== '' && $href !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($href, ENT_QUOTES); ?>" class="btn btn-outline-light" target="_blank" rel="noopener">
                                        <?php echo htmlspecialchars($cta, ENT_QUOTES); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($dropMediaUrl !== ''): ?>
                        <figure class="drop-showcase__media">
                            <?php if ($mediaIsVideo): ?>
                                <video src="<?php echo htmlspecialchars($dropMediaUrl, ENT_QUOTES); ?>" autoplay muted loop playsinline controls></video>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($dropMediaUrl, ENT_QUOTES); ?>" alt="Preview for <?php echo htmlspecialchars($message, ENT_QUOTES); ?>" loading="lazy">
                            <?php endif; ?>
                        </figure>
                    <?php endif; ?>
                </div>

                <?php if ($hasCountdown || !empty($metaItems)): ?>
                    <div class="drop-timeline">
                        <?php if ($hasCountdown): ?>
                            <div class="drop-timeline__countdown">
                                <div class="drop-countdown" data-countdown>
                                    <p class="drop-countdown__label" data-countdown-label><?php echo htmlspecialchars($countdownLabel !== '' ? $countdownLabel : 'Drop countdown', ENT_QUOTES); ?></p>
                                    <div class="drop-countdown__timer" data-countdown-display>
                                        <div class="drop-countdown__segment">
                                            <span class="drop-countdown__value" data-countdown-part="days">00</span>
                                            <span class="drop-countdown__caption">Days</span>
                                        </div>
                                        <div class="drop-countdown__segment">
                                            <span class="drop-countdown__value" data-countdown-part="hours">00</span>
                                            <span class="drop-countdown__caption">Hours</span>
                                        </div>
                                        <div class="drop-countdown__segment">
                                            <span class="drop-countdown__value" data-countdown-part="minutes">00</span>
                                            <span class="drop-countdown__caption">Mins</span>
                                        </div>
                                        <div class="drop-countdown__segment">
                                            <span class="drop-countdown__value" data-countdown-part="seconds">00</span>
                                            <span class="drop-countdown__caption">Secs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($metaItems)): ?>
                            <div class="drop-timeline__meta">
                                <?php foreach ($metaItems as $meta): ?>
                                    <div class="drop-timeline__meta-item">
                                        <p class="drop-timeline__meta-label"><?php echo htmlspecialchars($meta['label'], ENT_QUOTES); ?></p>
                                        <p class="drop-timeline__meta-value"><?php echo htmlspecialchars($meta['value'], ENT_QUOTES); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($hasInsights): ?>
                    <div class="drop-insights-grid">
                        <?php if ($dropStory !== ''): ?>
                            <section class="drop-insight drop-insight--story">
                                <h3 class="drop-insight__title">Drop story</h3>
                                <p class="drop-insight__body"><?php echo nl2br(htmlspecialchars($dropStory, ENT_QUOTES)); ?></p>
                            </section>
                        <?php endif; ?>

                        <?php if (!empty($dropHighlights)): ?>
                            <section class="drop-insight drop-insight--highlights">
                                <h3 class="drop-insight__title">Key highlights</h3>
                                <ul class="drop-insight__list">
                                    <?php foreach ($dropHighlights as $highlight): ?>
                                        <li>
                                            <span class="drop-insight__bullet" aria-hidden="true"></span>
                                            <span><?php echo htmlspecialchars($highlight, ENT_QUOTES); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <?php if ($dropAccessNotes !== ''): ?>
                            <section class="drop-insight drop-insight--access">
                                <h3 class="drop-insight__title">Access notes</h3>
                                <p class="drop-insight__body"><?php echo nl2br(htmlspecialchars($dropAccessNotes, ENT_QUOTES)); ?></p>
                            </section>
                        <?php endif; ?>

                        <?php if ($hasWaitlist): ?>
                            <section class="drop-insight drop-insight--waitlist">
                                <h3 class="drop-insight__title">How to claim your spot</h3>
                                <ul class="drop-insight__list">
                                    <li>
                                        <span class="drop-insight__bullet" aria-hidden="true"></span>
                                        <span>Secure priority access to <strong><?php echo htmlspecialchars($message, ENT_QUOTES); ?></strong>.</span>
                                    </li>
                                    <li>
                                        <span class="drop-insight__bullet" aria-hidden="true"></span>
                                        <span>We email early access links the moment the drop unlocks.</span>
                                    </li>
                                    <li>
                                        <span class="drop-insight__bullet" aria-hidden="true"></span>
                                        <span>Limited slots—one signup per email address.</span>
                                    </li>
                                </ul>
                            </section>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
        return trim((string) ob_get_clean());
    }
}
