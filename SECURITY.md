# Security

If you discover a security vulnerability, please follow responsible disclosure procedures and contact the project maintainers immediately. Replace the placeholder contact below with an appropriate address or escalation contact for your team.

Reporting
- Email: security@example.com  (replace with real ops/security contact)
- Provide: steps to reproduce, affected files (stack traces), and suggested mitigation if known.

Emergency mitigation
1. Run the emergency deactivation helper to stop scheduled drops immediately (project root):

```powershell
php scripts/force_deactivate.php --token=<TOKEN>
```

2. Backup `storage/` and the database, then escalate to the on-call engineer.

Secrets & token handling
- Keep the `DROP_FORCE_DEACTIVATE_TOKEN` or other tokens in a secure secrets store (Windows Credential Manager, environment variables configured securely, or a vault).
- Rotate tokens on a regular schedule and after any suspected exposure.

Disclosure policy
- Allow 90 days for coordinated disclosure before public posting, unless otherwise agreed.
