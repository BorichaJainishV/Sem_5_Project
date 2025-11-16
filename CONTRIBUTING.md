# Contributing to Mystic Clothing (Sem_5_Project)

Thanks for contributing. This document explains the simple workflow, branch naming, and checks to run before opening a pull request.

Branching and workflow
- Create a feature branch from `main` using the pattern: `feature/<short-desc>` or `fix/<short-desc>`.
- Make small, focused commits with clear messages.

Commit message style
- Use concise prefixes (examples): `feat:`, `fix:`, `chore:`, `docs:`.
- Example: `feat: add centralized admin header and sidebar`

Testing and linting
- Lint PHP files before committing:

```powershell
php -l path/to/file.php
```

- Run tests (if dependencies are installed):

```powershell
php vendor/bin/phpunit --configuration phpunit.xml.dist
```

PR checklist
- Provide a clear description of the change and include screenshots if UI-related.
- Add tests or explain why tests are not required.
- Run `php -l` on modified files.
- Update `docs/php_checksums.txt` if PHP sources changed (see `docs/ops.md`).
- Add a changelog entry (see `CHANGELOG.md`).

Code style
- Prefer readable, minimal changes. Follow PSR-12-like conventions where practical (4-space indentation, meaningful function names). The repo does not enforce a formal linter by default.

Reviewers
- Assign at least one reviewer. Reviewers should verify database migrations, statefile changes, and that ops steps are documented for releases affecting scheduler behavior.
