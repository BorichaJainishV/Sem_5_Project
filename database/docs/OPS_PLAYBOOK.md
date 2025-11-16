Operations Playbook — Mystic Clothing

This playbook contains one-line commands and procedures for common operational actions: enabling/disabling automation, forcing deactivation of promotions, collecting logs for postmortem, and database restore guidance.

## 1) Quick task control
Check status of the three scheduler-related tasks:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action status
```

Disable all tasks immediately (safe during incidents):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action disable
```

Enable tasks when ready:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action enable
```

Remove all tasks if you want to clean up:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/manage_drop_tasks.ps1 -Action remove
```

## 2) Force deactivate a promotion (CLI)
If a promotion is stuck active and you need to force it down, run the CLI deactivation helper (this is safer than editing DB directly):

```powershell
php scripts/drop_promotion_sync.php --deactivate
# Or run the scheduler with an override to deactivate current state
php scripts/drop_scheduler.php --force-env --dry-run
```

If a helper is not available, use an admin script that calls `drop_promotion_deactivate()` (ask me and I can create a tiny admin PHP endpoint to run in a pinch).

## 3) Collect logs & state for a postmortem
Gather logs and the current storage snapshot into a single archive for analysis:

```powershell
# Make a timestamped folder
$t = Get-Date -Format yyyyMMdd_HHmmss
New-Item -ItemType Directory -Path "storage\logs\postmortem_$t" | Out-Null

# Copy logs and storage artifacts
Copy-Item storage\logs\* "storage\logs\postmortem_$t\" -Recurse -Force
Copy-Item storage\drop_waitlists.json "storage\logs\postmortem_$t\" -ErrorAction SilentlyContinue
Copy-Item storage\drop_promotions_state.json "storage\logs\postmortem_$t\" -ErrorAction SilentlyContinue

# Zip the folder (requires PowerShell 5+)
Compress-Archive -Path "storage\logs\postmortem_$t\*" -DestinationPath "storage\logs\postmortem_$t.zip"
```

Attach `postmortem_$t.zip` when filing the incident or sharing with the team.

## 4) Database export / import (recommended)

Short answer to your question: The authoritative data is the running MySQL/MariaDB database — export that (a SQL dump). The `database/migrations/` folder contains migration scripts (source) and should be included in your repository for schema recreation, but you do not "export" the migration folder as part of a DB dump. For backups and transfers, do both: a full SQL dump (data + schema) and include your migrations directory and `mystic_clothing.sql` files.

Typical export (Windows + XAMPP):

```powershell
# Create a backups folder first
New-Item -ItemType Directory -Path D:\backups -ErrorAction SilentlyContinue

& 'C:\\xampp\\mysql\\bin\\mysqldump.exe' -u root -p mystic > "D:\backups\mystic_dump_$(Get-Date -Format yyyyMMdd_HHmmss).sql"
```

Notes:
- Replace `root` with the database user. `-p` will prompt for the password.
- To export schema only: add `--no-data`.
- To export a single table: append the table name(s) after the DB name.

Import into a target server:

```powershell
& 'C:\\xampp\\mysql\\bin\\mysql.exe' -u root -p mystic < "D:\backups\mystic_dump_20251116_120000.sql"
```

If you prefer GUI tools, use `phpMyAdmin` or MySQL Workbench to Export / Import the database.

## 5) What to include when shipping a DB snapshot to another team/environment
- Full SQL dump (data + schema). This is the primary backup for reproducing the exact state.
- `database/migrations/` directory and any `mystic_clothing.sql` artifact — useful when setting up a new instance from migrations instead of a single dump.
- `storage/` JSON files (e.g., `drop_waitlists.json`, `drop_promotions_state.json`) if you need runtime artifacts preserved.
- A small README describing what the dump contains and any sensitive data redaction performed.

## 6) Generating a PNG of the diagram (if you want a raster image)

Preferred: use Inkscape (CLI) or ImageMagick if installed. Example (Inkscape):

```powershell
# Using Inkscape (if installed)
& 'C:\\Program Files\\Inkscape\\inkscape.exe' docs/diagrams/drop_scheduler_flow.svg --export-type=png --export-filename=docs/diagrams/drop_scheduler_flow.png

# Or using ImageMagick (magick)
magick convert docs/diagrams/drop_scheduler_flow.svg docs/diagrams/drop_scheduler_flow.png
```

If neither tool is available on your machine, you can open the SVG in a browser and take a screenshot, or use an online SVG-to-PNG converter.

## 7) Post-incident tasks
- Add a short postmortem note to `docs/postmortems/<incident-timestamp>.md` summarizing cause, impact, and corrective steps.
- Update `docs/project_documentation.md` and `docs/OPS_PLAYBOOK.md` with any improved commands discovered during the incident.

---

If you want, I can add a tiny `scripts/force_deactivate.php` admin script that calls the same deactivation helper and logs the action; it would let ops run a single PHP file to deactivate promotions without touching the codebase.
