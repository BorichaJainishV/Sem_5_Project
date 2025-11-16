# Feature Sprint TODO

## Design Remix Hub
- [x] Document desired Remix entry points on homepage spotlight cards
	- Add a `Remix This` button inside each spotlight card's `.spotlight-actions` group, positioned between `View Full` and `Use This Vibe`.
	- The button links to `design3d.php` with a `remix_source` identifier and the selected variant path so the designer can preload the chosen view (defaults to design map when available).
	- Preserve existing actions (`View Full`, `Use This Vibe`) without altering their href patterns to avoid regressions.
- [x] Extend spotlight cards to expose a "Remix This" action
	- Injected a dedicated Remix CTA that shares the card layout and syncs with variant toggles without disturbing existing links.
	- Remix URLs carry a stable base token plus variant-specific parameters updated client-side.
- [x] Add secure token/URL params to preload designer with selected map/front/back assets
	- Links append sanitized remix parameters and store a stable token for future auditing.
- [x] Persist remix metadata (source design, user, timestamp)
	- Landing on the designer via remix records a structured entry to `storage/logs/remix_activity.log` (one per user/session).
- [x] Update 3D designer UI to surface source attribution
	- Sidebar callout highlights the source/variant and can be dismissed (remembered in sessionStorage).
- [x] QA: ensure legacy flows (Use This Vibe, View Full) still work
	- Verified spotlight toggles still update `View Full` href and `Use This Vibe` remains unchanged (map-first) so existing flow is intact.
- [ ] Final QA: rerun legacy flows (Use This Vibe, View Full) before launch

## Stylist In Your Inbox
- [x] Outline quiz questions & tie-ins with persona filters
	- Drafted `docs/stylist_inbox_outline.md` covering the three existing persona dimensions (style, palette, goal) with branded prompts and value mappings.
- [x] Create quiz form (frontend + handler)
	- Launched `stylist_inbox.php` with three-step branded quiz, hooked into `style_quiz_handler.php` including source/timestamp metadata.
- [x] Build recommendation engine placeholder (static rules)
	- Added persona fallback catalog in `core/style_quiz_helpers.php` so quizzes always return three curated items even when inventory metadata is sparse.
- [x] Integrate with email template / on-site dashboard
	- Refreshed `account.php` persona tile with metadata, fallback messaging, and inline actions; scaffolded reusable email template plus `sendStylistPersonaEmail` helper for inbox follow-ups.
- [x] QA with mock persona data
	- Added `tests/StylistPersonaFlowTest.php` to validate fallback catalog output and HTML email rendering with mock personas.

## Drop Scheduler & Waitlist
Goal: let marketing schedule time-limited drops and collect a waitlist for sold-out or upcoming drops. Provide a dismissible storefront banner with an accurate countdown and a lightweight waitlist enrollment flow.

\- [x] Design: extend banner manager schema for drop scheduling
	- Added `drop_label`, `visibility`, `countdown_enabled`, and start/end aliases to `core/banner_manager.php`, with CLI-safe defaults.
	- Updated admin scheduler UI to capture visibility audiences, toggle countdowns, and set a friendly drop label.
	- Drop banner rendering + waitlist flows now surface the label and respect visibility gating.
	- Acceptance criteria met: admin can schedule windowed drops, control countdown display, and scope banner visibility.

- [x] Backend: add waitlist enrollment endpoint and persistence
	- Added `drop_waitlist_enroll.php` POST endpoint that validates CSRF, rate-limits per slug/IP, and responds with JSON status codes.
	- Extended `core/drop_waitlist.php` with hashed IP storage, dedupe, rate limits, and sanitized context, persisting entries to `storage/drop_waitlists.json`.
	- Covered helper behaviour with `tests/DropWaitlistEnrollTest.php` (new) and ran PHPUnit for regression coverage.
	- Acceptance check: duplicate emails return `exists`, fresh signups persist, and rapid requests are throttled with `429` + `Retry-After`.

- [x] Frontend: waitlist enrollment UI + banner integration
	- Added `partials/drop_banner.php` partial rendered from `header.php` when a drop-mode banner is active, including CTA and data attributes for JS hydration.
	- Injected waitlist modal markup in `footer.php` and wired a module script `js/core/dropScheduler.js` to handle open/close, form submit, and button state.
	- Enrollment happens via fetch to `drop_waitlist_enroll.php` with inline success/error feedback; banner CTA disables after success.

- [x] Countdown widget
	- Created `js/core/countdown.js` with `initCountdown` helper used by `dropScheduler` to render live days/hours/minutes/seconds ticks.
	- Countdown autocompletes at zero, swaps label to "Drop live", and can be reused by other templates.

- [ ] Admin UX: schedule creation & CTA prompts
	- In `admin/products.php` or `admin/marketing.php`, surface a quick action after publishing a product to "Schedule a drop" pre-filling `drop_slug` and product info.
	- Add validation to prevent overlapping drops for the same `drop_slug` if desired.
	- Estimate: 0.5–1 day.

- [ ] QA & monitoring
	- Test cases: banner appears only within window; countdown accuracy vs server time; waitlist dedupe; form spam/rate-limit; banner dismissal persists in cookie/session; analytics event emitted on enrollments.
	- Instrumentation: log enrollments to `storage/logs/drop_waitlist.log` and emit a lightweight JSON webhook or admin email when >N signups.
	- Estimate: 0.5–1 day for QA scripts and logs.

- [ ] Security & privacy
	- Hash or remove PII from logs, include opt-in checkbox if needed for marketing emails, and support export/delete per GDPR if the project requires it.
	- Acceptance criteria: no raw PII written to public logs; exports possible via admin UI or SQL.

Notes / next steps
- I can: (A) create the migration + backend endpoint stubs and test data, or (B) produce the frontend modal and countdown JS. Which should I pick first? If you prefer, I can implement the backend enroll endpoint and basic storage so the UI can be wired quickly.

## Creator Profiles
- [ ] Define profile schema (spotlight entries, likes, saves, referrals)
- [ ] Create profile listing & detail pages
- [ ] Add referral code generation and tracking
- [ ] QA: cross-check stats with existing spotlight data
