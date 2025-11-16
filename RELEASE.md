# Release checklist

When preparing a release, follow these steps to ensure safe deployment.

1. Run automated checks
   - Lint PHP files: `php -l` on changed files.
   - Run unit tests: `php vendor/bin/phpunit --configuration phpunit.xml.dist` (if available).

2. Backups
   - Export and backup the production database.
   - Backup `storage/` JSON files (especially `drop_promotions_state.json`).

3. Regenerate checksums and docs
   - Regenerate `docs/php_checksums.txt`: `php scripts/generate_php_checksums.php`.

4. Dry-run any scheduler changes
   - Run scheduler in dry-run mode and inspect logs.

5. Tag and release
   - Bump version in `CHANGELOG.md` and create a Git tag: `git tag -a vX.Y.Z -m "Release vX.Y.Z"`
   - Push tags: `git push --follow-tags`.

6. Monitor
   - After deploy, monitor logs and scheduler runs for the first hour.
