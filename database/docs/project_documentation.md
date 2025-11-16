# Mystic Clothing Platform Documentation

<!-- TOC -->
- [Project Specification](#project-specification)
- [Technology Stack](#technology-stack)
- [Getting Started](#getting-started)
- [Core Feature Summary](#core-feature-summary)
- [Development Challenges & Resolved Errors](#development-challenges--resolved-errors)
- [Future Plans & Roadmap](#future-plans--roadmap)
- [References & How-To](#references--how-to)

## Project Specification
- **Purpose:** Full-stack ecommerce experience for Mystic Clothing, covering storefront browsing, custom design workflows, scheduled product drops, and support tooling for stylists and marketing teams.
- **Primary User Journeys:**
  - Browse catalog (`index.php`, `shop.php`, `product` partials) and purchase through `cart.php`, `checkout.php`, and `order_success.php`.
  - Manage accounts via `account.php`, including reward balances, saved designs, and stylist recommendations.
  - Participate in drops through the flash banner + waitlist combo (`partials/drop_banner.php`, `drop_waitlist_enroll.php`).
  - Designers build or remix looks (`design3d.php`, `old_3D/`, `save_design.php`) and submit entries to Spotlight (`submit_design_spotlight.php`).
  - Stylists answer inbox quizzes and track tickets (`stylist_inbox.php`, `support_issue_handler.php`).
  - Admins oversee orders, customers, marketing prompts, spotlight approvals, and automation inside `admin/`.
- **Non-Functional Goals:**
  - Keep storefront responsive with the shared stylesheet (`style.css`, `css/cleaned_style.css`).
  - Persist state in MySQL (schema captured in `mystic_clothing.sql`) plus JSON stores under `storage/` for lightweight services.
  - Provide automated promotion control via CLI/PowerShell so marketing can stage drops without SSH access.

## 2. Technology Stack
| Layer | Technology | Notes |
| --- | --- | --- |
| Server runtime | PHP 8.x on XAMPP | Entry points: `index.php`, API handlers (`*_handler.php`), CLI scripts in `scripts/` |
| Database | MySQL / MariaDB | Seed schema in `mystic_clothing.sql`; migrations live in `database/migrations/` |
| Frontend | HTML, vanilla JS (`js/app.js`, `js/core/*.js`), CSS (`style.css`, `css/cleaned_style.css`) | Countdown + waitlist modules, toast notifications (`js/toast.js`) |
| Task automation | Windows Task Scheduler + PowerShell (`scripts/*.ps1`) | `run_drop_scheduler.ps1`, `scheduler_watchdog.ps1`, `auto_revert_failsafe.ps1`, `setup_drop_tasks.ps1`, `manage_drop_tasks.ps1` |
| Background services | PHP CLI scripts (`scripts/drop_scheduler.php`, `drop_promotion_sync.php`, `waitlist_conversion_report.php`, etc.) | Run via PowerShell wrappers |
| Testing | PHPUnit (`phpunit.xml.dist`, `tests/*.php`) | Coverage for drop waitlist, stylist personas, etc. |
| Storage helpers | JSON under `storage/` (drop waitlists, promotions state, logs) | Simple persistence for automation modules |

## Getting Started

This section provides the minimal steps for developers and operators to run and test the project locally, control the drop automation, and run the test-suite.

- Prerequisites:
  - XAMPP with Apache + PHP 8.x installed and running.
  - MySQL/MariaDB available and the credentials configured in your environment.
  - PowerShell (Windows) for Task Scheduler integration.

- Start local webserver (XAMPP): use the XAMPP control panel to start `Apache` and `MySQL`.

- Example environment variables (see `.env.example` in repo root):

```
DB_HOST=127.0.0.1
DB_NAME=mystic
DB_USER=root
DB_PASS=secret
MYSTIC_ENV=local
DROP_SCHEDULER_ALLOW_ACTIVATE=false
PHP_PATH=C:\\xampp\\php\\php.exe
WEBHOOK_URL=
WEBHOOK_AUTH_HEADER=
WEBHOOK_AUTH_VALUE=
```

- Running the scheduler in dry-run mode (manual test):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/run_drop_scheduler.ps1 -PhpPath "C:\\xampp\\php\\php.exe" -DryRun
php scripts/drop_scheduler.php --dry-run
```

- Register Task Scheduler tasks (dry run first):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup_drop_tasks.ps1 -DryRun -TaskPrefix "Mystic"
# When ready (careful):
powershell -ExecutionPolicy Bypass -File scripts/setup_drop_tasks.ps1 -TaskPrefix "Mystic"
```

- Quick task control helper (status / enable / disable):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action status
powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action disable
powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action enable
```

## 3. Core Feature Summary
1. **Storefront & Cart** – Standard catalog browsing, cart, checkout, shipping info, and order history, with upsells (cart bundles) and returns/privacy/legal pages.
2. **Custom Design & Remix Hub** – 3D designer (`design3d.php`) supports remix tokens, session persistence, and Spotlight submissions with admin review queues.
3. **Style Quiz & Stylist Inbox** – `style_quiz_handler.php` + helpers turn quiz answers into personas; results email via `emails/stylist_persona_template.html` and tests under `tests/` ensure deterministic recommendations.
4. **Drop Promotions & Waitlist** – Flash banner + waitlist JSON store, CLI scheduler, watchdog, failsafe, drift detector, and archival utilities automate go-live/rollback flows.
5. **Support & Feedback** – Users can submit support tickets, compliments, and feedback; admin dashboards expose queues and ticket history.
6. **Admin Suite** – `admin/` modules cover customers, orders, marketing, spotlight, and support queue management with shared session helpers.

## 4. Development Challenges & Resolved Errors
| Issue | Impact | Resolution |
| --- | --- | --- |
| Task Scheduler rejected repetition interval strings (`"00:00:60"`) | `setup_drop_tasks.ps1` failed to register tasks | Switched to `New-TimeSpan` objects for interval arguments. |
| `Register-ScheduledTask` threw `Duration:P99999999DT23H59M59S` | Infinite duration default exceeded Task Scheduler bounds | Replaced `[TimeSpan]::MaxValue` with a 10-year `TimeSpan` to keep triggers valid. |
| PowerShell jobs kept running after XAMPP stopped | Drop automations continued polling, confusing developers | Added `manage_drop_tasks.ps1` helper and documented enable/disable steps; tasks now default to manual activation when dev stack is offline. |
| Non-prod environments accidentally triggered drops | Risk of activating promotions during local testing | Added env guard in `scripts/drop_scheduler.php` using `MYSTIC_ENV` + `DROP_SCHEDULER_ALLOW_ACTIVATE`. |
| Need for reliable rollback & monitoring | Drops could stall without visibility | Implemented watchdog + failsafe scripts, webhook alerts, drift detector, and archive utilities for forensic snapshots. |

## 5. Future Plans & Roadmap
- **Drop Scheduler Enhancements (from `TODO_FEATURES.md`):** Add admin-side quick actions when publishing products, prevent overlapping windows, and expand QA/monitoring (banner visibility tests, rate-limit telemetry, PII scrubbing).
- **Security & Privacy:** Hash or redact PII in logs, add marketing consent checkboxes, and expose data export/delete options.
- **Creator Profiles:** Implement schema + UI for creator stats (likes, saves, referrals) and integrate referral tracking.
- **Automation Quality Gate:** Extend `setup_drop_tasks.ps1` to allow staged/disabled registration and integrate health-check pings before each run.
- **Analytics & Insights:** Expand `waitlist_conversion_report.php` outputs, add dashboard cards inside `admin/dashboard.php`, and wire automated email summaries.

## 6. References & How-To
- **Task Management:** Use `scripts/setup_drop_tasks.ps1` to register tasks, and `scripts/manage_drop_tasks.ps1 -Action enable|disable|status` to control them locally.
- **Data Stores:** JSON files under `storage/` capture drop states, waitlists, tickets, and logs—version or archive via `scripts/archive_drop_state.php`.
- **Testing:** Run PHPUnit with `vendor/bin/phpunit` (configuration in `phpunit.xml.dist`). Feature-specific tests live in `tests/`.
- **Deployment Notes:** Ensure `.env`/environment variables supply DB credentials, `MYSTIC_ENV`, and `DROP_SCHEDULER_ALLOW_ACTIVATE`. Set Task Scheduler credentials to an account with access to XAMPP/PHP binaries.

This document can evolve alongside the TODO files—append new challenges/resolutions and roadmap entries as each sprint concludes.

## Deployment Checklist & Rollback (Ops Runbook)

Follow this checklist when promoting a drop from staging to production. These are concise operational steps to reduce risk during a launch.

Pre-deploy (staging/validation)
- Confirm database migrations are applied to the target environment.
- Run `php scripts/drop_promotion_snapshot.php --expect-status=idle` to confirm no active promotions are present.
- Run the scheduler in dry-run against staging and inspect `storage/logs/drop_scheduler.log`.

Deploy
- Apply code and asset changes (standard deployment process).
- Run any required DB migrations.
- If using Task Scheduler automation: register tasks via `scripts/setup_drop_tasks.ps1` but keep them disabled until final verification.

Go-live (final)
- Enable tasks via `scripts/manage_drop_tasks.ps1 -Action enable` or enable the tasks in Task Scheduler GUI.
- Monitor logs: `storage/logs/drop_scheduler.log`, `storage/logs/drop_watchdog.log` (if present), and any webhook alerts.
- Validate storefront state (banner, countdown, waitlist) and run `php scripts/drop_promotion_snapshot.php --expect-status=active --expect-slug=<slug>`.

Rollback (if things go wrong)
- Disable scheduled tasks immediately: `powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action disable`.
- Run `php scripts/drop_promotion_snapshot.php --expect-status=idle` to check promotion state; if promotion remains active, run the CLI deactivation: `php scripts/drop_promotion_sync.php --deactivate` or use `drop_promotion_deactivate()` via a small admin script.
- Restore database or data artifacts from the archive created by `php scripts/archive_drop_state.php` (run this after a stable launch to capture the pre-launch snapshot).
- Inspect logs (`storage/logs/*`) and webhook payloads for root cause analysis.

Post-incident
- Record a postmortem entry in the project's `docs/` folder and update this runbook with any changes to the rollback procedure.

