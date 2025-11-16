# Drop Scheduler

This utility keeps promotion automation in sync with the drop start/end window without editing any existing PHP files. The automated components are intended to be run via PowerShell wrappers and Task Scheduler on Windows so marketing can operate drops without SSH access.

## Script entry point

Run the scheduler from the project root:

```powershell
php scripts/drop_scheduler.php
```

Add `--dry-run` to see the intended action without touching promotions:

```powershell
php scripts/drop_scheduler.php --dry-run
```

## What the scheduler does

- Reads the saved drop payload in `storage/flash_banner.json`.
- Exits immediately when the active banner is not using drop mode.
- If the current time is **before** `schedule_start`, it ensures `drop_promotion_deactivate()` has been called so pricing/bundles stay idle.
- While the current time is **between** `schedule_start` and `schedule_end`, it calls `drop_promotion_sync(true)` once so the promotion engine activates right as the countdown expires.
- Once **after** `schedule_end`, it deactivates any lingering promotion state.
- Every operation is logged to stdout with ISO timestamps for easy auditing.

## Setting up automation on Windows (recommended)

Prefer the provided PowerShell helpers which add logging, dry-run support, and webhook integration instead of invoking `php.exe` directly. The recommended flow is:

1. Dry-run and verify behavior locally:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/run_drop_scheduler.ps1 -PhpPath "C:\\xampp\\php\\php.exe" -DryRun
```

2. Preview Task Scheduler registration (safe):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup_drop_tasks.ps1 -DryRun -TaskPrefix "Mystic"
```

3. Register tasks when ready (consider registering in a disabled state and enabling via `manage_drop_tasks.ps1`):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup_drop_tasks.ps1 -TaskPrefix "Mystic"
# Or to register and leave disabled by default, pass -TaskPrefix and manually disable after registration.
```

4. Use the management helper to check/enable/disable without opening Task Scheduler GUI:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action status
powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action disable
powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action enable
```

Notes on task behavior:
- There are three scheduled tasks created by `setup_drop_tasks.ps1`: Scheduler, Watchdog, and Failsafe. The Watchdog watches the scheduler log and can restart a missing task; the Failsafe probes the storefront and attempts automated rollback if the storefront reports an unexpected state.
- Tasks run independently of XAMPP; stopping the XAMPP control panel does not prevent Task Scheduler from launching the wrappers. Disable tasks when the local stack is offline.
- Use `-DryRun` and the log files under `storage/logs/` for safe validation before affecting live promotions.

See the diagram at `docs/diagrams/drop_scheduler_flow.svg` for a quick architecture view.

## Expected log output

```
[2025-11-15 11:55:00] Evaluating drop scheduler window: start 2025-11-15 12:00:00 / end 2025-11-16 12:00:00
[2025-11-15 11:55:00] Drop has not reached its start time yet.
```

```
[2025-11-15 12:00:08] Evaluating drop scheduler window: start 2025-11-15 12:00:00 / end 2025-11-16 12:00:00
[2025-11-15 12:00:08] Drop window is open for activation.
[2025-11-15 12:00:08] Activation result: {"status":"activated","state":{...}}
```

Use the logs to confirm the job is running at the cadence you expect.

## Logging and dry-run helpers

- `scripts/run_drop_scheduler.ps1` runs the PHP scheduler, captures stdout/stderr, and appends structured entries to `storage/logs/drop_scheduler.log`. Point Task Scheduler at this wrapper (instead of `php.exe` directly) if you want historical logs automatically.
- For dry-runs use the `-DryRun` switch on the wrapper or call `php scripts/drop_scheduler.php --dry-run` directly.

## Promotion snapshot verifier

Use `scripts/drop_promotion_snapshot.php` to confirm the current drop state (or enforce expectations in CI/CD):

```powershell
php scripts/drop_promotion_snapshot.php --expect-status=active --expect-slug=aurora-drop
```

Exit code is non-zero if the state disagrees with the expectations, making it safe for automation or alerts.

## Go/No-Go checklist

1. Open `admin/marketing.php` and confirm **Drop mode**, **countdown**, **waitlist**, and **promotion features** match the upcoming launch.
2. Run the dry-run helper once:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/run_drop_scheduler.ps1 -PhpPath "C:\\xampp\\php\\php.exe" -DryRun
```

3. Run the snapshot verifier to ensure the promotion is still idle before the window:

```powershell
php scripts/drop_promotion_snapshot.php --expect-status=idle
```

4. If you plan to use Task Scheduler registration, register tasks but leave them disabled and only `enable` when ready using `manage_drop_tasks.ps1`.
5. After the window opens, check the live storefront or run the synthetic probe (see `scripts/drop_probe.php`) to confirm the banner reports `data-drop-state="live"`.
6. Archive the state after launch with `php scripts/archive_drop_state.php` so you have a rollback artifact.
