# 📦 Deployment Readiness Testing Guide

**Purpose:** Ensure the application is properly configured and ready for production deployment.

---

## 📋 Pre-Deployment Checklist

### ✅ Remove Debug Code
- [ ] No `console.log()` in PHP templates
- [ ] No `print_r()` or `var_dump()` in production code
- [ ] No debug flags enabled (`display_errors = 0`)
- [ ] No test/debug files in production

**How to Test:**
```bash
# Check for console.log in PHP files (JavaScript console.log is OK)
grep -r "console\.log" --include="*.php"

# Check for debug output
grep -r "print_r\|var_dump\|var_export" --include="*.php" | grep -v "error_log"

# Check for debug flags
grep -r "display_errors.*1" --include="*.php"
grep -r "error_reporting(E_ALL)" --include="*.php"

# Check for test files
find . -name "*test*.php" -o -name "*Test*.php" | grep -v vendor | grep -v tests
```

**Expected Result:** 
- No console.log in PHP
- No print_r/var_dump outside error_log
- All error handling is environment-aware

**Status:** ✅ COMPLETE (All fixed Dec 3, 2024)

---

### ✅ Environment Variables
- [ ] `.env.example` exists and is up to date
- [ ] All required variables documented
- [ ] Production `.env` file created
- [ ] Sensitive values not in version control

**How to Test:**
```bash
# Check .env.example exists
test -f .env.example && echo "✅ .env.example exists" || echo "❌ Missing"

# Verify environment loader
cat core/config/config.php | grep "getenv"

# Test environment loading
php -r "
require 'core/config/config.php';
echo config('app.env') . PHP_EOL;
"

# Verify .gitignore excludes .env
grep "^\.env$" .gitignore
```

**Production `.env` Template:**
```bash
# Application
APP_NAME=FireGuard
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_HOST=localhost
DB_NAME=your_database
DB_USER=your_db_user
DB_PASS=your_secure_password
DB_CHARSET=utf8mb4

# Email
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password

# SMS
SMS_API_KEY=your_sms_api_key
SMS_API_SECRET=your_sms_secret

# Security
SESSION_LIFETIME=120
RATE_LIMIT_REQUESTS=60
RATE_LIMIT_MINUTES=1
```

---

### ✅ Build Verification
- [ ] Dependencies installed
- [ ] No dev dependencies in production
- [ ] Autoloader optimized
- [ ] Assets compiled and minified

**How to Test:**
```bash
# Run build verification script
php scripts/verify-build.php

# Check dependencies
composer install --no-dev --optimize-autoloader

# Verify vendor directory
test -d vendor && echo "✅ Dependencies installed"

# Check for dev dependencies (should be none)
composer show --installed | grep "dev"

# Verify minified assets
ls -lh build/css/*.min.css
ls -lh build/js/*.min.js
```

**Expected Result:**
- All dependencies installed
- No dev dependencies
- Autoloader optimized
- Assets minified

---

### ✅ Rollback Strategy
- [ ] Backup scripts exist
- [ ] Rollback scripts exist
- [ ] Backup tested
- [ ] Rollback tested

**How to Test:**
```bash
# Verify scripts exist
ls -la scripts/create-backup.*
ls -la scripts/create-rollback.*

# Test backup creation (dry run)
BACKUP_DIR=/tmp/test-backup APP_DIR=. bash scripts/create-backup.sh

# Verify backup contents
ls -la /tmp/test-backup/

# Test rollback procedure
BACKUP_DIR=/tmp/test-backup APP_DIR=. bash scripts/create-rollback.sh
```

**Rollback Test Checklist:**
```markdown
- [ ] Create backup of current version
- [ ] Deploy new version
- [ ] Verify new version works
- [ ] Trigger rollback
- [ ] Verify old version restored
- [ ] Test application functionality
- [ ] Check database integrity
```

---

### ✅ Deployment Documentation
- [ ] Deployment runbook exists
- [ ] Post-deployment checks documented
- [ ] Emergency contacts listed
- [ ] Known issues documented

**How to Test:**
```bash
# Verify documentation exists
test -f docs/deployment_runbook.md && echo "✅ Runbook exists"
test -f docs/ROLLBACK_PROCEDURES_RUNBOOK.md && echo "✅ Rollback docs exist"

# Review documentation
cat docs/deployment_runbook.md
```

**Required Documentation:**
1. `docs/deployment_runbook.md` ✅
2. `docs/ROLLBACK_PROCEDURES_RUNBOOK.md` ✅
3. `docs/GETTING_STARTED.md` ✅
4. `.env.example` ✅

---

### ✅ Post-Deployment Monitoring
- [ ] Health check endpoint exists
- [ ] Logging configured
- [ ] Error monitoring set up
- [ ] Uptime monitoring configured

**How to Test:**
```bash
# Test health endpoint
curl -I https://yourdomain.com/health.php

# Expected response:
# HTTP/1.1 200 OK
# Content-Type: application/json

curl https://yourdomain.com/health.php
# Expected: {"status":"ok","timestamp":"..."}

# Verify logging
test -d logs && echo "✅ Log directory exists"
test -f logs/php_errors.log && echo "✅ Error log exists"

# Check log rotation
cat core/logger/logger.php | grep "rotateLogFile"

# Verify error handler
cat core/error_handler.php
```

---

## 🚀 Deployment Process

### Pre-Deployment Steps

**1. Verify Environment (5 minutes)**
```bash
# Run environment verification
php scripts/verify-environment.php

# Check output for any errors
# Should show:
# ✅ PHP version OK
# ✅ Required extensions loaded
# ✅ File permissions OK
# ✅ Configuration valid
```

---

**2. Run All Tests (10 minutes)**
```bash
# Unit tests
composer test

# Security scan
composer audit

# Code quality check
composer check-readability

# All must pass!
```

---

**3. Create Backup (5 minutes)**
```bash
# On production server
cd /var/www/html
./scripts/create-backup.sh

# Verify backup created
ls -la /backups/
```

---

**4. Deploy Application (10 minutes)**
```bash
# Pull latest code
git pull origin main

# Install dependencies (production mode)
composer install --no-dev --optimize-autoloader --no-interaction

# Clear any caches
php scripts/clear-cache.php

# Set file permissions
chmod 644 *.php
chmod 755 scripts/
chmod 777 logs/
chmod 777 uploads/
```

---

**5. Database Migrations (if needed)**
```bash
# Run migrations
php scripts/run-migrations.php

# Or manually:
mysql -u user -p database < migrations/001-add-new-column.sql
```

---

### Post-Deployment Verification

**1. Smoke Tests (5 minutes)**
```bash
# Health check
curl https://yourdomain.com/health.php
# Expected: {"status":"ok"}

# Test login page
curl -I https://yourdomain.com/
# Expected: HTTP/1.1 200 OK

# Test API endpoint
curl https://yourdomain.com/device/smoke_api.php
# Should not error

# Check PHP errors
tail -50 /var/www/html/logs/php_errors.log
```

---

**2. Critical Path Testing (10 minutes)**

Test these manually in browser:

**User Flows:**
- [ ] Homepage loads
- [ ] Login works (test with real account)
- [ ] Registration works (test with new email)
- [ ] User dashboard loads
- [ ] Profile page works
- [ ] Logout works

**Admin Flows:**
- [ ] Admin login works
- [ ] Super admin dashboard loads
- [ ] Device management works
- [ ] User management works

**Device Flows:**
- [ ] Device API responds
- [ ] Fire data accepted
- [ ] GPS data accepted
- [ ] Alerts triggered

**Firefighter Flows:**
- [ ] Firefighter login works
- [ ] Map loads correctly
- [ ] Emergency buildings show
- [ ] Incident reports work

---

**3. Monitor System (30 minutes)**
```bash
# Watch error logs
tail -f logs/php_errors.log

# Watch system resources
top
htop

# Check database connections
mysql -u root -p -e "SHOW PROCESSLIST;"

# Monitor response times
while true; do
  time curl -s -o /dev/null https://yourdomain.com/
  sleep 5
done
```

---

## 🔄 Rollback Procedure

### When to Rollback:
- Critical errors in logs
- Key functionality broken
- Database corruption
- Security vulnerability discovered
- Performance degradation >50%

### How to Rollback (5 minutes):
```bash
# 1. Navigate to backup directory
cd /backups

# 2. List available backups
ls -la

# 3. Restore from backup
./restore-backup.sh backup-YYYY-MM-DD-HHMMSS.tar.gz

# 4. Verify restoration
curl https://yourdomain.com/health.php

# 5. Test critical functionality

# 6. Check logs
tail -f /var/www/html/logs/php_errors.log
```

---

## 📊 Deployment Checklist

### Pre-Deployment (30 minutes):
- [ ] All tests pass (`composer test`)
- [ ] Security audit clean (`composer audit`)
- [ ] Code review complete
- [ ] Environment variables configured
- [ ] Database backup created
- [ ] Application backup created
- [ ] Deployment window scheduled
- [ ] Team notified

### During Deployment (20 minutes):
- [ ] Maintenance mode enabled (optional)
- [ ] Code deployed
- [ ] Dependencies installed
- [ ] Database migrations run
- [ ] File permissions set
- [ ] Cache cleared
- [ ] Services restarted
- [ ] Maintenance mode disabled

### Post-Deployment (60 minutes):
- [ ] Health check passes
- [ ] Smoke tests pass
- [ ] Critical paths tested
- [ ] Error logs checked (< 10 errors/hour)
- [ ] Performance acceptable (response time < 2s)
- [ ] Database queries normal
- [ ] Monitoring active
- [ ] Team notified of success

---

## 🛠️ Deployment Tools

### 1. **Deployment Script** (Automated)
```bash
#!/bin/bash
# scripts/deploy-production.sh

set -e  # Exit on error

echo "🚀 Starting deployment..."

# 1. Create backup
echo "📦 Creating backup..."
./scripts/create-backup.sh

# 2. Pull latest code
echo "📥 Pulling latest code..."
git pull origin main

# 3. Install dependencies
echo "📚 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Run migrations (if any)
if [ -d "migrations/pending" ]; then
    echo "🗃️ Running migrations..."
    php scripts/run-migrations.php
fi

# 5. Clear caches
echo "🧹 Clearing caches..."
rm -rf cache/*
php scripts/clear-cache.php

# 6. Restart services
echo "🔄 Restarting services..."
sudo systemctl restart php7.4-fpm
sudo systemctl restart apache2

# 7. Health check
echo "🏥 Running health check..."
curl -f https://yourdomain.com/health.php || exit 1

echo "✅ Deployment complete!"
```

---

### 2. **Health Check Endpoint**
Already exists: `/health.php`

```bash
# Test it
curl https://yourdomain.com/health.php

# Expected response:
{
  "status": "ok",
  "timestamp": "2024-12-04T10:30:00Z",
  "database": "connected",
  "php_version": "7.4.33"
}
```

---

### 3. **Monitoring Setup**

**UptimeRobot Configuration:**
```
Monitor Type: HTTPS
URL: https://yourdomain.com/health.php
Interval: 5 minutes
Alert Contacts: [Your email/SMS]
```

**Pingdom Configuration:**
```
Check Type: HTTP Check
URL: https://yourdomain.com/health.php
Check Interval: 1 minute
Alert Threshold: Down for 2 minutes
```

---

## 📊 Deployment Report Template

```markdown
# Deployment Report

**Date:** YYYY-MM-DD HH:MM
**Version:** [Version/Commit]
**Deployed By:** [Your Name]
**Environment:** Production

## Pre-Deployment
- [ ] All tests passed
- [ ] Security audit clean
- [ ] Backup created: [backup-file.tar.gz]

## Deployment
- **Start Time:** HH:MM
- **End Time:** HH:MM
- **Duration:** [X minutes]
- **Downtime:** [X minutes] or None

## Changes Deployed
1. [Feature/Fix description]
2. [Feature/Fix description]

## Post-Deployment Verification
- [ ] Health check: ✅ PASS
- [ ] Smoke tests: ✅ PASS
- [ ] Critical paths: ✅ PASS
- [ ] Error rate: [X errors/hour]
- [ ] Response time: [Xms average]

## Issues Encountered
- None / [Issue description and resolution]

## Rollback Status
- [ ] Not needed
- [ ] Performed at [HH:MM]

## Sign-off
- [x] Deployment successful
- [x] All checks passed
- [x] Monitoring active
- [x] Team notified

**Next Steps:**
- Monitor for next 24 hours
- [Any follow-up actions]
```

---

## 🚨 Deployment Red Flags

⚠️ **STOP deployment if:**
- Any tests fail
- Security vulnerabilities found
- No backup created
- Production `.env` not configured
- Health check fails
- Database migration errors
- Critical functionality broken in staging

---

## 📚 Resources

- [Deployment Runbook](./deployment_runbook.md) ✅
- [Rollback Procedures](./ROLLBACK_PROCEDURES_RUNBOOK.md) ✅
- [Getting Started Guide](./GETTING_STARTED.md) ✅

---

## ✅ Quick Deployment Checklist

**5 Minutes Before:**
- [ ] All tests pass
- [ ] Team notified
- [ ] Backup created

**During Deployment:**
- [ ] Code deployed
- [ ] Dependencies installed
- [ ] Services restarted

**5 Minutes After:**
- [ ] Health check passes
- [ ] Smoke tests pass
- [ ] Error logs clean

---

**Last Updated:** December 2024




