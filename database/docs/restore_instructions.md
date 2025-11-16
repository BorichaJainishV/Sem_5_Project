# Restore Instructions — Mystic Clothing

This document describes how to import a SQL dump, run migrations (if needed), and verify the database state after restore.

Prerequisites
- MySQL or MariaDB server running (XAMPP or equivalent).
- `mysql.exe` available (e.g., `C:\xampp\mysql\bin\mysql.exe`).
- A backup SQL file produced by `mysqldump` (e.g., `mystic_dump_YYYYMMDD_HHMMSS.sql`).

1) Importing a SQL dump

Warning: importing will modify the target database. Run on a staging/test server first.

```powershell
# Create the target DB (if not exists)
& 'C:\\xampp\\mysql\\bin\\mysql.exe' -u <user> -p -e "CREATE DATABASE IF NOT EXISTS mystic_clothing CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

# Import the dump
& 'C:\\xampp\\mysql\\bin\\mysql.exe' -u <user> -p mystic_clothing < D:\backups\mystic_dump_YYYYMMDD_HHMMSS.sql
```

2) Post-restore verification

- Confirm critical tables exist (example: `flash_banners`):

```powershell
& 'C:\\xampp\\mysql\\bin\\mysql.exe' -u <user> -p -e "USE mystic_clothing; SHOW TABLES LIKE 'flash_banners'; DESCRIBE flash_banners;"
```

- Run a quick count on important tables to ensure rows were restored:

```powershell
& 'C:\\xampp\\mysql\\bin\\mysql.exe' -u <user> -p -e "USE mystic_clothing; SELECT COUNT(*) FROM customer; SELECT COUNT(*) FROM orders;"
```

- If your app verifies promotion state with `scripts/drop_promotion_snapshot.php`, run it to confirm expected status:

```powershell
php scripts/drop_promotion_snapshot.php --expect-status=idle
```

3) Running migrations (if you prefer migrations over a raw dump)

If you prefer to recreate schema using migration scripts instead of a large dump, apply migrations in chronological order. Example CLI (pseudo):

```powershell
# Using mysql client to run a migration file
& 'C:\\xampp\\mysql\\bin\\mysql.exe' -u <user> -p mystic_clothing < database/migrations/2025_11_14_add_drop_banner_fields.sql
```

Repeat for each migration file as required.

4) Redaction & sensitive data

If sharing dumps externally, remove or anonymize PII (emails, addresses, phone numbers). Simple redaction approach (example):

```sql
UPDATE customer SET email = CONCAT('user+', customer_id, '@example.local');
UPDATE admin SET email = CONCAT('admin+', admin_id, '@example.local');
```

Run these statements on a copy of the DB before exporting for external sharing.

5) Rollback & restoring previous state

- Keep previous dumps archived (timestamped). To rollback, import the older dump file with the import command above.
- If you performed schema migrations that are destructive, you may need to restore both schema + data together from an earlier full dump.

6) Troubleshooting
- If import fails due to foreign key checks, try wrapping import with:
  ```sql
  SET FOREIGN_KEY_CHECKS=0; -- import -- SET FOREIGN_KEY_CHECKS=1;
  ```
- If charset/collation errors occur, ensure the dump and target DB use utf8mb4 and proper collation.

If you want, I can add a small `docs/restore_checklist.md` with checklist items and exact commands tailored to your environment (CI/CD, staging, production). 
