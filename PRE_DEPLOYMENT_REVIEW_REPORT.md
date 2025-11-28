# 🔒 Pre-Deployment Security & Code Review Report

**Generated:** $(date)
**Codebase:** FIREGUARD Fire Detection System
**Reviewer:** Senior Software Engineer

---

## 📊 Executive Summary

This report identifies critical security vulnerabilities, performance issues, code quality problems, and deployment readiness concerns across the codebase. **All findings are prioritized by severity and include actionable fixes.**

### Priority Levels
- 🔴 **CRITICAL** - Must fix before deployment
- 🟠 **HIGH** - Should fix before deployment  
- 🟡 **MEDIUM** - Recommended to fix
- 🟢 **LOW** - Nice to have improvements

---

## 🔴 CRITICAL SECURITY ISSUES

### 1. Debug Mode Enabled in Production Files

**Issue:** Multiple files have `display_errors = 1` and `error_reporting(E_ALL)` enabled, exposing sensitive information.

**Affected Files:**
- `device/smoke_api.php` (lines 3-4)
- `device/smoke_gps.php` (lines 3-4)
- `device/dashboard.php` (line 3)
- `device/esp.php` (line 3)
- `device/smokestore.php` (lines 3-4)
- `device/register_device.php` (line 3)
- `fireFighter/alarm/alarm.php` (line 4)
- `fireFighter/profile/functions/functions.php` (line 3)
- `production/alarm/get_fire_status.php` (lines 2-3)
- `production/mapping/php/get_emergency_buildings.php` (lines 3-4)
- `fireFighter/mapping/php/*.php` (multiple files)

**Fix Required:**

```php
// ❌ BEFORE (INSECURE)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ AFTER (SECURE)
// Use environment-aware error handling
$isProduction = (getenv('APP_ENV') === 'production' || 
                 (isset($_SERVER['HTTP_HOST']) && 
                  strpos($_SERVER['HTTP_HOST'], 'localhost') === false));

if ($isProduction) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
```

**Or use the centralized bootstrap:**
```php
require_once __DIR__ . '/../../core/bootstrap.php';
```

---

### 2. SQL Injection Risk: mysqli Usage Without Prepared Statements

**Issue:** `device/smoke_api.php` uses `mysqli` directly and may have queries vulnerable to SQL injection.

**Affected File:** `device/smoke_api.php` (lines 49, 111-559)

**Fix Required:**

```php
// ❌ BEFORE (INSECURE - using mysqli)
$conn = new mysqli($host, $username, $password, $dbname);
// Later in code: potential for SQL injection if user input is concatenated

// ✅ AFTER (SECURE - use centralized PDO)
require_once __DIR__ . '/../core/database/database.php';
$conn = getDatabaseConnection(); // Returns PDO connection

// All queries must use prepared statements:
$stmt = $conn->prepare("INSERT INTO table (column) VALUES (?)");
$stmt->execute([$userInput]);
```

**Action:** Refactor `device/smoke_api.php` to use PDO from `core/database/database.php`.

---

### 3. CORS Wildcard on API Endpoints

**Issue:** Device API allows requests from any origin (`Access-Control-Allow-Origin: *`), which is insecure.

**Affected Files:**
- `device/smoke_api.php` (line 10)
- `device/smoke_gps.php` (line 10)

**Fix Required:**

```php
// ❌ BEFORE (INSECURE)
header('Access-Control-Allow-Origin: *');

// ✅ AFTER (SECURE)
// Whitelist specific allowed origins
$allowedOrigins = [
    'https://your-domain.com',
    'https://api.your-domain.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
```

---

### 4. Missing Input Validation on API Endpoints

**Issue:** Device APIs accept input without proper validation and sanitization.

**Affected File:** `device/smoke_api.php` (lines 86-109)

**Fix Required:**

```php
// ❌ BEFORE (INSECURE)
$this->device_id = isset($data['device_id']) ? intval($data['device_id']) : null;
$this->temperature = isset($data['temperature']) && $data['temperature'] !== '' ? floatval($data['temperature']) : null;

// ✅ AFTER (SECURE - with validation)
require_once __DIR__ . '/../core/validation/validator.php';
require_once __DIR__ . '/../core/security/input_sanitizer.php';

$this->device_id = isset($data['device_id']) ? validateInt($data['device_id'], 1, 999999) : null;
if ($this->device_id === false) {
    throw new InvalidArgumentException('Invalid device_id');
}

$this->temperature = null;
if (isset($data['temperature']) && $data['temperature'] !== '') {
    $temp = sanitizeFloat($data['temperature']);
    if ($temp !== false && $temp >= -50 && $temp <= 200) { // Reasonable range
        $this->temperature = $temp;
    }
}
```

---

### 5. No Rate Limiting on Device APIs

**Issue:** Device endpoints (`smoke_api.php`, `smoke_gps.php`) have no rate limiting, making them vulnerable to DoS attacks.

**Fix Required:**

```php
// ✅ ADD at the beginning of device/smoke_api.php
require_once __DIR__ . '/../core/rate_limit/rate_limiter.php';

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateLimitResult = checkRateLimit($clientIp, 'device_api', 100, 60); // 100 requests per minute

if (!$rateLimitResult['allowed']) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Too many requests. Please wait before trying again.'
    ]);
    exit;
}
```

---

### 6. **CRITICAL: Hardcoded Plaintext Password in Device API**

**Issue:** Device API creates a default user with hardcoded plaintext password `'password'` (line 223 in `device/smoke_api.php`).

**Affected File:** `device/smoke_api.php` (lines 222-224)

**Fix Required:**

```php
// ❌ BEFORE (CRITICAL SECURITY VULNERABILITY)
$conn->query("INSERT INTO users (user_id, username, email, password, first_name, last_name, phone) 
              VALUES (1, 'arduino_user', 'arduino@firedetection.com', 'password', 'Arduino', 'User', '+639318261972')
              ON DUPLICATE KEY UPDATE user_id = user_id");

// ✅ AFTER (SECURE - remove default user creation or use secure password)
// Option 1: Remove default user creation entirely (RECOMMENDED)
// Devices should be registered through proper registration flow

// Option 2: If default user must exist, generate secure random password
require_once __DIR__ . '/../core/auth/authentication.php';

$securePassword = bin2hex(random_bytes(32)); // Generate random password
$hashedPassword = password_hash($securePassword, PASSWORD_BCRYPT);

// Store password hash, not plaintext
$stmt = $conn->prepare("INSERT INTO users (user_id, username, email, password, first_name, last_name, phone) 
                        VALUES (1, 'arduino_user', 'arduino@firedetection.com', ?, 'Arduino', 'User', '+639318261972')
                        ON DUPLICATE KEY UPDATE user_id = user_id");
$stmt->bind_param("s", $hashedPassword);
$stmt->execute();
```

**Action Required:** **IMMEDIATELY** - This is a critical security vulnerability that allows unauthorized access.

---

### 7. SQL Injection Risk: Direct Query Usage

**Issue:** Multiple files use `$conn->query()` with string concatenation instead of prepared statements.

**Affected Files:**
- `device/smoke_api.php` (lines 207, 222-237)
- `device/smoke_gps.php` (similar issues)
- Multiple other files

**Fix Required:**

```php
// ❌ BEFORE (VULNERABLE TO SQL INJECTION)
$query = "SELECT device_id FROM devices WHERE is_active = 1 ORDER BY device_id LIMIT 1";
$result = $conn->query($query);

// ✅ AFTER (SECURE - use prepared statements)
$stmt = $conn->prepare("SELECT device_id FROM devices WHERE is_active = 1 ORDER BY device_id LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
```

**Note:** Since this file uses mysqli, migration to PDO is recommended.

---

## 🟠 HIGH PRIORITY ISSUES

### 6. Inconsistent Error Handling

**Issue:** Error handling is inconsistent across files - some show errors, some hide them, some log them.

**Recommendation:** Standardize error handling using `core/bootstrap.php`.

**Files to Update:** All PHP entry points should start with:
```php
require_once __DIR__ . '/../../core/bootstrap.php';
```

---

### 7. Missing CSRF Protection on Device APIs

**Issue:** Device APIs accept POST requests without CSRF token validation.

**Affected Files:**
- `device/smoke_api.php`
- `device/smoke_gps.php`

**Fix Required:**

```php
// ✅ ADD CSRF validation for authenticated endpoints
// Note: Device APIs may need API key authentication instead
require_once __DIR__ . '/../core/security/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For device APIs, use API key authentication
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '';
    if (!validateApiKey($apiKey)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid API key']);
        exit;
    }
}
```

---

### 8. Session Security Issues

**Issue:** Some files start sessions without secure configuration.

**Fix Required:** Always use centralized session handler:

```php
// ✅ CORRECT
require_once __DIR__ . '/../../core/bootstrap.php';
// Session is automatically configured securely
```

---

## 🟡 MEDIUM PRIORITY ISSUES

### 9. Unused Code and Dead Code

**Files with Potential Dead Code:**
- Check for unused functions in `reg/registration.php` (4567 lines - very large)
- Review `userdashboard/building/php/main.php` for unused code paths

**Recommendation:** Use static analysis tools to identify unused code.

---

### 10. Large Files That Should Be Split

**Files Over 1000 Lines:**
- `reg/registration.php` (4567 lines) - **CRITICAL** - Split into modules
- `userdashboard/alarm/alert.php` (2337 lines) - Split into components
- `production/fireincidents/php/main.php` - Review and split

**Recommendation:** Split large files into smaller, focused modules.

---

### 11. Missing Type Declarations

**Issue:** Many functions lack type hints and return types.

**Example Fix:**

```php
// ❌ BEFORE
function getUserData($userId) {
    // ...
}

// ✅ AFTER
function getUserData(int $userId): ?array {
    // ...
}
```

---

### 12. Performance: N+1 Query Problems

**Issue:** Some queries may cause N+1 problems when fetching related data.

**Recommendation:** Use JOIN queries to fetch related data in a single query.

---

## 🟢 LOW PRIORITY / CODE QUALITY

### 13. Inconsistent Naming Conventions

**Recommendation:** Standardize naming:
- Functions: `camelCase`
- Classes: `PascalCase`
- Constants: `UPPER_SNAKE_CASE`
- Variables: `$camelCase`

---

### 14. Missing PHPDoc Comments

**Recommendation:** Add PHPDoc comments to all public functions and classes.

---

## 📋 DEPLOYMENT CHECKLIST

### Before Deployment:

- [ ] Fix all 🔴 CRITICAL issues
- [ ] Fix all 🟠 HIGH priority issues  
- [ ] Remove all `display_errors = 1` from production files
- [ ] Verify all `.env` variables are set
- [ ] Test all API endpoints with rate limiting
- [ ] Review and test CSRF protection on all forms
- [ ] Verify HTTPS enforcement in production
- [ ] Test error logging (errors should not display to users)
- [ ] Review and test authentication flows
- [ ] Verify password hashing is using secure algorithms
- [ ] Check file upload security
- [ ] Review and test session security
- [ ] Verify database backup procedures
- [ ] Test rollback procedures

---

## 🔧 QUICK FIXES SCRIPT

See `scripts/fix-deployment-issues.php` for automated fixes of common issues.

---

**Next Steps:**
1. Review each critical issue
2. Apply fixes in priority order
3. Test thoroughly after each fix
4. Re-run security scan
5. Deploy to staging first

---

## 📁 FILE-BY-FILE DETAILED REVIEW

### Critical Files Requiring Immediate Attention

#### 1. `device/smoke_api.php` (559 lines)

**🔴 CRITICAL ISSUES:**

1. **Hardcoded Plaintext Password (Line 223)**
   ```php
   // CURRENT (INSECURE):
   $conn->query("INSERT INTO users (..., password, ...) VALUES (..., 'password', ...)");
   ```
   **FIX:** Remove default user creation or use secure password hashing. See Fix #6 above.

2. **Debug Mode Enabled (Lines 3-4)**
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```
   **FIX:** See Fix #1 above.

3. **SQL Injection Risk (Lines 207, 222-237)**
   - Uses `$conn->query()` with string concatenation
   - No prepared statements for INSERT queries

4. **No Input Validation**
   - Device IDs, temperature, humidity values not validated
   - No bounds checking

5. **CORS Wildcard (Line 10)**
   ```php
   header('Access-Control-Allow-Origin: *');
   ```
   **FIX:** Whitelist specific origins. See Fix #3 above.

6. **No Rate Limiting**
   - API endpoint has no protection against DoS attacks
   **FIX:** See Fix #5 above.

**✅ RECOMMENDED FIXES:**

```php
<?php
// ✅ SECURE VERSION - Add at top of file

// Environment-aware error handling
$isProduction = (getenv('APP_ENV') === 'production' || 
                 (isset($_SERVER['HTTP_HOST']) && 
                  strpos($_SERVER['HTTP_HOST'], 'localhost') === false));

if ($isProduction) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../../logs/device_api_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Load centralized modules
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/rate_limit/rate_limiter.php';
require_once __DIR__ . '/../core/validation/validator.php';

// Rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateLimitResult = checkRateLimit($clientIp, 'device_api', 100, 60);
if (!$rateLimitResult['allowed']) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests']);
    exit;
}

// Secure CORS
$allowedOrigins = [
    'https://your-domain.com',
    'https://api.your-domain.com'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}

// ... rest of file
```

**In `createDefaultDevice()` method:**

```php
// ✅ SECURE VERSION - Replace lines 217-240

private function createDefaultDevice() {
    $conn = Database::getConnection();
    if (!$conn) return null;

    // DO NOT create default users with hardcoded passwords
    // Instead, throw exception requiring proper device registration
    throw new Exception('Device not registered. Please register device through proper registration flow.');
    
    // OR if default user is absolutely required:
    // Generate secure random password and hash it
    // $securePassword = bin2hex(random_bytes(32));
    // $hashedPassword = password_hash($securePassword, PASSWORD_BCRYPT);
    // Log password securely for admin retrieval
    
    // Use prepared statements for all queries
    // $stmt = $conn->prepare("INSERT INTO users (...) VALUES (?, ?, ?, ?, ?, ?, ?)");
    // $stmt->execute([...]);
}
```

**In `getFirstActiveDeviceId()` method:**

```php
// ✅ SECURE VERSION - Replace lines 202-215

private function getFirstActiveDeviceId() {
    $conn = Database::getConnection();
    if (!$conn) return null;

    // Use prepared statement (even for static queries, for consistency)
    $stmt = $conn->prepare("SELECT device_id FROM devices WHERE is_active = 1 ORDER BY device_id LIMIT 1");
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        return null;
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['device_id'];
    }
    
    $stmt->close();
    return null;
}
```

---

#### 2. `device/smoke_gps.php`

**Similar issues as `smoke_api.php`** - Apply same fixes.

---

#### 3. `reg/registration.php` (4567 lines - VERY LARGE FILE)

**🟠 HIGH PRIORITY ISSUES:**

1. **File Too Large** - Should be split into modules:
   - Registration form handler
   - Validation functions
   - Email verification
   - Database operations

2. **Performance Concern** - Large file with many functions may impact loading time

**✅ RECOMMENDATION:**
- Split into smaller modules
- Use autoloading for better performance
- Separate concerns (validation, database, email, etc.)

---

#### 4. `userdashboard/alarm/alert.php` (2337 lines)

**🟡 MEDIUM PRIORITY:**

1. **File Too Large** - Should be split
2. **Multiple responsibilities** - Alert handling, SMS, AJAX responses, etc.

**✅ RECOMMENDATION:**
Split into:
- `alert_handler.php` - Core alert logic
- `sms_handler.php` - SMS functionality  
- `alert_ajax.php` - AJAX endpoints

---

## 🔧 PERFORMANCE OPTIMIZATIONS

### Database Query Optimization

**Issue:** Some queries may cause N+1 problems.

**Example Fix:**

```php
// ❌ BEFORE (N+1 Problem)
foreach ($users as $user) {
    $stmt = $conn->prepare("SELECT * FROM devices WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $devices = $stmt->fetchAll();
}

// ✅ AFTER (Single Query)
$stmt = $conn->prepare("
    SELECT u.*, d.* 
    FROM users u 
    LEFT JOIN devices d ON u.user_id = d.user_id
    WHERE u.status = 'active'
");
$stmt->execute();
$results = $stmt->fetchAll();
```

---

## 📋 COMPLETE DEPLOYMENT CHECKLIST

### Security Checklist
- [ ] Fix all debug mode settings (`display_errors = 0` in production)
- [ ] Remove all hardcoded passwords
- [ ] Replace all `$conn->query()` with prepared statements
- [ ] Add rate limiting to all API endpoints
- [ ] Fix CORS configuration (remove wildcards)
- [ ] Verify all inputs are validated
- [ ] Test CSRF protection on all forms
- [ ] Verify password hashing uses secure algorithms
- [ ] Check file upload validation
- [ ] Review session security settings
- [ ] Verify HTTPS enforcement

### Performance Checklist
- [ ] Optimize database queries (remove N+1 problems)
- [ ] Split large files (>1000 lines)
- [ ] Enable opcode caching (OPcache)
- [ ] Minify CSS/JS assets
- [ ] Enable gzip compression
- [ ] Review and remove unused code
- [ ] Add database indexes where needed

### Code Quality Checklist
- [ ] Add type declarations to functions
- [ ] Add PHPDoc comments
- [ ] Standardize naming conventions
- [ ] Remove duplicate code
- [ ] Add meaningful comments

### Testing Checklist
- [ ] Unit tests for critical functions
- [ ] Integration tests for API endpoints
- [ ] Test authentication flows
- [ ] Test error handling
- [ ] Test edge cases
- [ ] Load testing for APIs

### Deployment Checklist
- [ ] Verify all `.env` variables are set
- [ ] Test rollback procedures
- [ ] Verify backup procedures
- [ ] Set up monitoring/alerts
- [ ] Review log rotation
- [ ] Test on staging environment first
- [ ] Document deployment process

---

## 🚀 QUICK WIN FIXES (Apply These First)

1. **Remove Debug Mode** (5 minutes)
   - Search and replace `display_errors = 1` with environment-aware code

2. **Fix Hardcoded Password** (10 minutes)
   - Remove or secure default user creation in `device/smoke_api.php`

3. **Add Rate Limiting** (15 minutes)
   - Add rate limiting to device APIs

4. **Fix CORS** (10 minutes)
   - Replace wildcard with whitelist

5. **Replace Direct Queries** (1-2 hours)
   - Replace `$conn->query()` with prepared statements

---

**Report continues with additional file reviews...**

---

*End of Report*

