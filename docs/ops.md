<!-- Operations and scheduler documentation -->
# Ops: Scheduler, Task registration, and backups

This document describes operational procedures for the drop scheduler, Task Scheduler registration on Windows, safety controls, and backup/restore recommendations.

1) Purpose
- The scheduler (`scripts/drop_scheduler.php`) runs scheduled 'drops' (promotions) and updates `storage/drop_promotions_state.json`.
- Use the provided PowerShell wrappers in `scripts/` to register and run the scheduler safely.

2) Files of interest
- `scripts/run_drop_scheduler.ps1` — PowerShell wrapper with skip/suspend checks.
- `scripts/run_drop_scheduler_launcher.vbs` — VBS launcher used to hide console windows (Task Scheduler runs `wscript.exe` on this file).
- `scripts/force_deactivate.php` — token-guarded emergency helper to deactivate a drop and backup the state.
- `storage/drop_promotions_state.json` — scheduler state; backup before writes.
- `storage/scheduler_suspend.json` — optional boolean/state file that the wrapper respects to suspend runs.
- `docs/php_checksums.txt` — manifest of PHP source file hashes (used for integrity checks).

3) Register Task Scheduler (example)

Run PowerShell as Administrator. A typical register script (example) will:

- Create a task that runs: `wscript.exe "D:\XAMPP\htdocs\Sem_5_Project\scripts\run_drop_scheduler_launcher.vbs"`
- Schedule: daily/hourly, as needed.
- Run with highest privileges and configure for the appropriate Windows user.

If you have a helper script `scripts/register_drop_scheduler_task.ps1`, run it as admin:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass;
.\scripts\register_drop_scheduler_task.ps1
```

4) Hidden runs / Visible console avoidance
- On some Windows hosts Task Scheduler may briefly display a console. We use a VBS wrapper that launches PowerShell in the background to avoid visible windows:

- Action: `wscript.exe "D:\XAMPP\htdocs\Sem_5_Project\scripts\run_drop_scheduler_launcher.vbs"`
- The VBS file calls `WScript.Shell.Run` with window style to hide the spawned process.

5) Safety controls (must review before scheduling)
- `storage/scheduler_suspend.json`: create or flag this file to pause scheduled runs. The wrapper checks for this file to avoid running when maintenance is ongoing.
- Process checks: `run_drop_scheduler.ps1` checks for presence of `httpd`/`mysqld` processes (or alternate service checks) and will skip runs if services are down.
- Environment override: `DROP_SCHEDULER_FORCE_RUN=1` can be set to bypass suspend checks — use with caution (ops-only).
- Dry-run: the scheduler supports dry-run flags (check wrapper script) — use dry-run before live activation.

6) Emergency deactivation
- If a scheduled promotion must be immediately disabled:

```powershell
php scripts/force_deactivate.php --token=<TOKEN>
```

- `scripts/force_deactivate.php` creates a timestamped backup of `storage/drop_promotions_state.json` and appends an audit record. Keep the token securely stored (secrets manager or OS protected credentials store).

7) Backups & restore
- Backup DB before running promotions that change inventory/fulfillment state.
- Backup `storage/*.json` files regularly and before scheduled runs:

```powershell
Copy-Item -Path storage\drop_promotions_state.json -Destination storage\backups\drop_promotions_state.$((Get-Date).ToString('yyyyMMdd_HHmmss')).json
```

- To restore, stop scheduler task, copy the backup to `storage/drop_promotions_state.json`, then re-enable the task.

8) Checksums
- The file `docs/php_checksums.txt` contains SHA256 hashes for PHP sources. Regenerate after any intentional code change and store the new manifest.

Regenerate (PowerShell example):

```powershell
Get-ChildItem -Recurse -Include *.php | Sort-Object FullName | ForEach-Object {
  $h = (Get-FileHash $_.FullName -Algorithm SHA256).Hash
  "$h  $($_.FullName -replace '^.*Sem_5_Project\\','')"
} > docs/php_checksums.txt
```

9) Scheduling checklist before publishing a drop
- Run DB backup.
- Verify `docs/php_checksums.txt` matches sources.
- Run scheduler in dry-run mode and inspect logs.
- Ensure `storage/scheduler_suspend.json` is absent.
- Schedule the task and monitor the first execution (logs, storage file changes).

10) Troubleshooting tips
- If the VBS launcher shows parse errors, open it in a text editor and ensure the script uses `Chr(34)` for safe quoting.
- If Task Scheduler shows the task as "Running" for a long time, check logs and the `storage/drop_promotions_state.json` backup files for partial writes.

Contact & escalation
- Ops owner: update this section to list your on-call contact, or reference `SECURITY.md` for emergency contact instructions.
