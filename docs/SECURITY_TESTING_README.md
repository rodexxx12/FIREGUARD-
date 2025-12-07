# 🔐 Security Testing Guide

**Purpose:** Ensure the application is secure against common vulnerabilities and follows security best practices.

---

## 📋 Pre-Deployment Security Checklist

### ✅ Input Validation
- [ ] All user inputs are sanitized using `sanitizeString()`, `sanitizeInt()`, `sanitizeEmail()`, etc.
- [ ] SQL queries use prepared statements (no direct queries with user input)
- [ ] File uploads are validated (type, size, extension)
- [ ] URL parameters are validated before use

**How to Test:**
```bash
# Check for unsanitized inputs
grep -r "\$_GET\[" --include="*.php" | grep -v "sanitize"
grep -r "\$_POST\[" --include="*.php" | grep -v "sanitize"
grep -r "\$_REQUEST\[" --include="*.php" | grep -v "sanitize"

# Run input validation tests
composer test tests/unit/Security/InputSanitizerTest.php
```

**Tools:**
- Manual code review
- PHPUnit tests (`tests/unit/Security/InputSanitizerTest.php`)
- OWASP ZAP for automated scanning

---

### ✅ Authentication & Authorization
- [ ] Passwords are hashed with `password_hash()` using `PASSWORD_DEFAULT`
- [ ] Session regeneration occurs on login
- [ ] RBAC (Role-Based Access Control) is enforced
- [ ] Failed login attempts are rate-limited

**How to Test:**
```bash
# Check password hashing
grep -r "password_hash" core/auth/
grep -r "PASSWORD_DEFAULT" core/auth/

# Check session regeneration
grep -r "session_regenerate_id" core/

# Test authentication
composer test tests/unit/Auth/AuthenticationTest.php
```

**Manual Tests:**
1. Try logging in with wrong password (should fail)
2. Try accessing admin pages without admin role (should be denied)
3. Try rapid login attempts (should be rate-limited)

---

### ✅ No Hardcoded Credentials
- [ ] No credentials in source code
- [ ] All credentials loaded from `.env` file
- [ ] Database connections use environment variables
- [ ] API keys are stored securely

**How to Test:**
```bash
# Search for potential hardcoded credentials
grep -ri "password\s*=\s*['\"]" --include="*.php" | grep -v "password_hash"
grep -ri "api_key\s*=\s*['\"]" --include="*.php"
grep -ri "secret\s*=\s*['\"]" --include="*.php"

# Verify environment variable usage
grep -r "getenv\|env(" core/config/
```

**Expected Result:** No hardcoded credentials found

---

### ✅ Encryption
- [ ] HTTPS enforced in production (`forceHttps()`)
- [ ] Passwords hashed (not encrypted)
- [ ] Sensitive data encrypted at rest (if applicable)
- [ ] Secure headers set (HSTS, X-Frame-Options, etc.)

**How to Test:**
```bash
# Check HTTPS enforcement
grep -r "forceHttps" core/bootstrap.php

# Check security headers
grep -r "setSecurityHeaders" core/security/headers.php

# Verify in browser
curl -I https://yourdomain.com | grep -i "strict-transport-security"
```

---

### ✅ Rate Limiting & Throttling
- [ ] Login endpoint rate-limited
- [ ] Registration endpoint rate-limited
- [ ] API endpoints rate-limited
- [ ] Rate limits are configurable

**How to Test:**
```bash
# Check rate limiting implementation
cat core/rate_limit/rate_limiter.php

# Manual test: Try rapid requests
for i in {1..20}; do curl -X POST https://yourdomain.com/login -d "user=test&pass=test"; done
```

**Expected Result:** Requests blocked after limit reached

---

### ✅ SQL Injection Protection
- [ ] All queries use prepared statements
- [ ] PDO with `ATTR_EMULATE_PREPARES => false`
- [ ] No string concatenation in queries
- [ ] Only safe queries use `query()` (schema inspection)

**How to Test:**
```bash
# Check for unsafe queries
grep -r "->query(" --include="*.php" | grep -v "SHOW TABLES\|SHOW COLUMNS"
grep -r "\$conn->query" --include="*.php"

# Look for string concatenation in queries
grep -r "SELECT.*\$" --include="*.php" | grep -v "prepare"

# Run SQL injection tests
composer test tests/unit/Database/DatabaseTest.php
```

**Manual Tests:**
1. Try SQL injection in login: `' OR '1'='1`
2. Try SQL injection in search: `'; DROP TABLE users; --`

**Expected Result:** All attempts sanitized/prevented

---

### ✅ XSS Protection
- [ ] All output is escaped with `htmlspecialchars()` or `escapeHtml()`
- [ ] JSON output uses `json_encode()`
- [ ] Context-aware escaping for JavaScript, URL, HTML attributes

**How to Test:**
```bash
# Check for unescaped output
grep -r "echo \$_" --include="*.php"
grep -r "print \$_" --include="*.php"

# Check XSS protection module
cat core/security/xss.php

# Run XSS tests
composer test tests/unit/Security/XSSTest.php
```

**Manual Tests:**
1. Try XSS in input: `<script>alert('XSS')</script>`
2. Try XSS in URL: `?name=<img src=x onerror=alert(1)>`

**Expected Result:** All scripts escaped/removed

---

### ✅ CSRF Protection
- [ ] CSRF tokens generated for all forms
- [ ] Tokens validated on POST requests
- [ ] Tokens expire after use or timeout

**How to Test:**
```bash
# Check CSRF implementation
cat core/security/csrf.php

# Look for CSRF token generation
grep -r "generateCsrfToken" --include="*.php"

# Run CSRF tests
composer test tests/unit/Security/CSRFTest.php
```

**Manual Tests:**
1. Submit form without CSRF token (should fail)
2. Submit form with invalid token (should fail)
3. Reuse same token twice (should fail on second attempt)

---

### ✅ HTTPS Enforcement
- [ ] Production environment forces HTTPS
- [ ] HSTS header set
- [ ] Mixed content warnings resolved

**How to Test:**
```bash
# Check HTTPS enforcement
grep -r "forceHttps" core/bootstrap.php

# Test redirect (should redirect HTTP to HTTPS)
curl -I http://yourdomain.com

# Check HSTS header
curl -I https://yourdomain.com | grep -i "strict-transport-security"
```

---

### ✅ Third-Party Libraries
- [ ] All dependencies audited for vulnerabilities
- [ ] Dependencies are up to date
- [ ] Only necessary libraries included

**How to Test:**
```bash
# Run Composer audit
composer audit

# Check for outdated packages
composer outdated

# List all dependencies
composer show
```

**Expected Result:** "No security vulnerability advisories found"

---

### ✅ Secure Error Handling
- [ ] Production mode hides error details
- [ ] Errors logged to files, not displayed
- [ ] No sensitive data in error messages
- [ ] Custom error pages for 404, 500

**How to Test:**
```bash
# Check error configuration
cat core/bootstrap.php | grep "display_errors"

# Verify error logging
cat core/error_handler.php

# Test error page (should show generic message)
curl https://yourdomain.com/nonexistent-page
```

---

### ✅ Least Privilege Principle
- [ ] Database user has minimal permissions
- [ ] User roles properly enforced
- [ ] File permissions are restrictive (644 for files, 755 for directories)

**How to Test:**
```bash
# Check file permissions
find . -type f -perm 0777
find . -type d -perm 0777

# Check RBAC implementation
cat core/auth/rbac.php
```

---

## 🛠️ Automated Security Testing Tools

### 1. **OWASP ZAP** (Recommended)
```bash
# Install ZAP
# Download from: https://www.zaproxy.org/download/

# Run baseline scan
docker run -t owasp/zap2docker-stable zap-baseline.py -t https://yourdomain.com
```

### 2. **Nikto** (Web Server Scanner)
```bash
nikto -h https://yourdomain.com
```

### 3. **SQLMap** (SQL Injection Testing)
```bash
sqlmap -u "https://yourdomain.com/login" --data="username=admin&password=test"
```

### 4. **XSStrike** (XSS Testing)
```bash
python3 xsstrike.py -u "https://yourdomain.com/search?q=test"
```

---

## 📊 Security Testing Report Template

```markdown
# Security Test Report

**Date:** YYYY-MM-DD
**Tester:** [Your Name]
**Environment:** [Production/Staging/Development]

## Test Results

### Input Validation: ✅ PASS / ❌ FAIL
- Details: [Description]

### Authentication: ✅ PASS / ❌ FAIL
- Details: [Description]

### SQL Injection: ✅ PASS / ❌ FAIL
- Details: [Description]

### XSS Protection: ✅ PASS / ❌ FAIL
- Details: [Description]

### CSRF Protection: ✅ PASS / ❌ FAIL
- Details: [Description]

## Issues Found
1. [Issue description]
2. [Issue description]

## Recommendations
1. [Recommendation]
2. [Recommendation]
```

---

## 🚨 Critical Security Items (Must Pass)

1. ✅ **No hardcoded credentials**
2. ✅ **SQL injection protected**
3. ✅ **Input validation implemented**
4. ✅ **XSS protection enabled**
5. ✅ **CSRF tokens validated**
6. ✅ **HTTPS enforced**
7. ✅ **Error handling secure**
8. ✅ **Dependencies audited**

**All items must pass before production deployment.**

---

## 📚 Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [CWE Top 25](https://cwe.mitre.org/top25/)

---

**Last Updated:** December 2024




