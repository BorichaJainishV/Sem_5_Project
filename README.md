<!-- Project README for Mystic Clothing (Sem_5_Project) -->
# Mystic Clothing (Sem_5_Project)

Short description
- Admin + storefront PHP application used in the semester project for managing product drops, orders, customers, and support workflows.

Quick start (Windows, XAMPP)

1. Install XAMPP (Apache + PHP + MySQL) and start Apache & MySQL.
2. Import the database:

   - Using phpMyAdmin or MySQL client, import `database/mystic_clothing.sql` into a database (e.g. `mystic_clothing`).

3. Configure DB connection:

   - Copy `db_connection.php.example` (if present) to `db_connection.php` and update credentials, or edit `db_connection.php` with your MySQL settings.

4. Serve the app locally:

   - Place the project in your web root (example already at `d:/XAMPP/htdocs/Sem_5_Project`) and open `http://localhost/Sem_5_Project/`.
   - Or run a quick PHP dev server (for ad-hoc testing):

```powershell
php -S localhost:8000 -t d:/XAMPP/htdocs/Sem_5_Project
# then open http://localhost:8000/admin/dashboard.php
```

Scheduler / Ops
- This project contains a drop scheduler and helper scripts under `scripts/`. See `docs/ops.md` for how to register the Windows Task Scheduler tasks, VBS launcher usage (hidden runs), and safety controls.

Testing & linting
- Lint a PHP file:

```powershell
php -l path/to/file.php
```

- Run unit tests (if dependencies installed via Composer):

```powershell
php vendor/bin/phpunit --configuration phpunit.xml.dist
```

Important files & locations
- App root: project files (PHP, assets)
- Admin pages: `admin/` (shared header/footer are `admin/_header.php` and `admin/_footer.php`)
- Scripts: `scripts/` (scheduler, utilities)
- Storage: `storage/` (JSON statefiles: `drop_promotions_state.json`, `support_tickets.json`, etc.)
- Ops docs: `docs/ops.md`
- Checksums: `docs/php_checksums.txt` (maintain if you want to verify sources haven't changed)

Security & emergency
- For emergency deactivation of scheduled drops, see `scripts/force_deactivate.php` (token-guarded). See `SECURITY.md`.

Contributing
- See `CONTRIBUTING.md` for the basic workflow and PR checklist.

License
- This repository does not include a license file by default. Add `LICENSE` if you want to open-source the project.
