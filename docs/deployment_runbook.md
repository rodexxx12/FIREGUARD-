## 🚀 Deployment Runbook

This document describes the **standard deployment process** for the Fire Detection System and the **post-deployment checks** you should perform.

### ✅ Pre-deployment checks

- **Code quality & readability**
  - Run: `composer check-readability`
- **Security & dependencies**
  - Run: `composer audit`
- **Database optimization (optional but recommended before big releases)**
  - Run: `php scripts/optimize-database.php`
- **Environment configuration**
  - Run: `php scripts/verify-environment.php`
- **Build & dependency verification**
  - Run: `php scripts/verify-build.php`
- **Tests**
  - Run: `composer test`
  - Optionally: `composer test-coverage`

### 📦 Deployment steps (application)

1. **Create a backup on the target server**
   - Run: `BACKUP_DIR=/backups APP_DIR=/var/www/html php scripts/create-backup.sh` (or the equivalent via shell).
2. **Update codebase**
   - Pull latest from the main branch (or deploy the prepared artifact package) into the application directory (e.g., `/var/www/html`).
3. **Install PHP dependencies**
   - From the project root: `composer install --no-dev --prefer-dist --no-interaction`
4. **Run database migrations or schema changes** (if applicable)
   - Apply any SQL migration scripts required for this release.
5. **Clear caches / opcode caches**
   - If not already handled by automation, reload PHP-FPM/Apache and ensure opcache is cleared.

### 🧪 Post-deployment checks

- **Application health**
  - Load main web UI and confirm:
    - Login and registration flows work.
    - User dashboard loads without PHP errors (check `logs/php_errors.log`).
  - Call the health endpoint and confirm it returns `200` with `"status": "ok"`:
    - `GET https://yourdomain.com/health.php`
- **Fire detection & alerting**
  - Confirm sensors data is being ingested and visible on dashboards.
  - Trigger a test alert (in a **non-production / staging** environment) to verify SMS/email pipelines.
- **Background services (FireML)**
  - Ensure the FireML Flask service is running and responding on its configured port.
  - Verify that `DEBUG_MODE` is **off** in production (`APP_ENV=production` / `FLASK_DEBUG` not set to `1`).
- **Monitoring & logs**
  - Confirm application logs are being written to `logs/` and rotated by your server tooling.
  - Configure an external uptime monitor (e.g., Pingdom, UptimeRobot, etc.) to check `https://yourdomain.com/health.php`.
  - Check server / uptime monitoring dashboards to ensure green status.

### 🔄 Rollback procedure (high level)

If a deployment causes issues:

1. **Initiate rollback on the server**
   - Run: `BACKUP_DIR=/backups APP_DIR=/var/www/html php scripts/create-rollback.sh` (or execute the shell equivalent).
2. **Verify restoration**
   - Re-check web UI, FireML health, and logs to confirm system is back to the previous stable state.


