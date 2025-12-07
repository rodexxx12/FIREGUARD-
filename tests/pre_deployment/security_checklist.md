## 🔐 Security Checklist

- [✅] Validate all user inputs (sanitize, escape, whitelist where possible).
- [✅] Use secure authentication and authorization mechanisms.
- [✅] Avoid hardcoded credentials, secrets, or API keys in the codebase.
- [✅] Ensure proper encryption for sensitive data at rest (e.g., hashed passwords) and in transit (HTTPS).
- [✅] Implement rate limiting and throttling to prevent abuse and brute-force attacks.
- [✅] Check for SQL injection, XSS, CSRF, and other common vulnerabilities.
- [✅] Enforce HTTPS for all external and internal web communications (excluding `device` and `FireML` modules as required).
- [✅] Review third-party libraries and dependencies for known vulnerabilities (via `composer audit` / `php scripts/audit-dependencies.php`).
- [✅] Ensure secure error handling (no sensitive info in logs or error messages).
- [ ] Apply least privilege principle for all access control (DB users, app roles, system permissions).

