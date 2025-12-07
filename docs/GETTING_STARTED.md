# 🚀 Getting Started - Fix Critical Issues

**Time Required:** 6-8 hours (1 working day)  
**Priority:** CRITICAL - Must complete before any deployment  
**Goal:** Fix all security vulnerabilities

---

## 🎯 Today's Mission: Make FIREGUARD Secure

You need to fix **4 critical security issues** before this code can go to production.

---

## ✅ Task 1: Remove Hardcoded Database Credentials (30 minutes)

### The Problem:
Your database password is visible in the code at `device/smoke_api.php` lines 21-24.

### What To Do:

**Step 1:** Create `.env` file in project root
```bash
# Copy the example
cp .env.example .env

# Edit with your credentials
nano .env
```

**Step 2:** Add these lines to `.env`:
```env
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_username
DB_PASS=your_password
```

**Step 3:** Fix `device/smoke_api.php`

Find this code (lines 21-24):
```php
// ❌ DELETE THIS
private static $host = "localhost";
private static $dbname = "u520834156_DBBagofire";
private static $username = "u520834156_userBagofire";
private static $password = "i[#[GQ!+=C9";
```

Replace with:
```php
// ✅ USE THIS INSTEAD
private static $host;
private static $dbname;
private static $username;
private static $password;

private static function init() {
    if (self::$host === null) {
        self::$host = env('DB_HOST');
        self::$dbname = env('DB_NAME');
        self::$username = env('DB_USER');
        self::$password = env('DB_PASS');
    }
}
```

**Step 4:** Test it works
```bash
# Test the device API
curl http://localhost/device/smoke_api.php
```

**Step 5:** Rotate your database password
```sql
-- Log into MySQL
mysql -u root -p

-- Change the password
ALTER USER 'your_username'@'localhost' IDENTIFIED BY 'new_secure_password';

-- Update .env with new password
```

✅ **Done!** Mark checkbox in README.md

---

## ✅ Task 2: Remove Debug Code (1 hour)

### The Problem:
Debug code in production exposes sensitive information.

### Files to Fix:

**File 1:** `production/mapping/php/components/scripts.php`
```php
// ❌ REMOVE ALL console.log() statements
console.log("Debug info here");

// ✅ If you need logging, use this:
logDebug('Debug info here', ['data' => $someData]);
```

**File 2:** `production/mapping/php/components/emergency_buildings_api_processor.php`
```php
// ❌ REMOVE THIS
ini_set('display_errors', 1);

// ✅ It's already set correctly in core/config/config.php
// No need to set it again
```

**File 3:** `userdashboard/profile/functions/profile_picture_handler.php`
```php
// ❌ REMOVE THESE
print_r($_POST);
print_r($_FILES);

// ✅ If you need to debug, use this:
logDebug('POST data', ['post' => $_POST]);
logDebug('FILES data', ['files' => $_FILES]);
```

**File 4:** `userdashboard/sensordata/php/get_user_fire_data.php`
```php
// ❌ REMOVE THIS
print_r($params);

// ✅ Use proper logging:
logDebug('Query params', ['params' => $params]);
```

**Quick Find & Remove:**
```bash
# Search for debug code
grep -r "console.log" --include="*.php" .
grep -r "print_r" --include="*.php" .
grep -r "var_dump" --include="*.php" .
grep -r 'ini_set.*display_errors.*1' --include="*.php" .
```

✅ **Done!** Mark checkbox in README.md

---

## ✅ Task 3: Add Input Validation (2-3 hours)

### The Problem:
User input is used directly without sanitization, allowing XSS and injection attacks.

### Files to Fix:

**File 1:** `superadmin/device/php/components/scripts.php` (line 20)

❌ **Before:**
```php
$search_term = $_POST['search_term'] ?? '';
```

✅ **After:**
```php
require_once __DIR__ . '/../../../../core/security/input_sanitizer.php';
$search_term = sanitizeString($_POST['search_term'] ?? '');
```

**File 2:** `production/mapping/php/components/building_api_processor.php` (line 9)

❌ **Before:**
```php
$buildingId = $_GET['id'] ?? 0;
```

✅ **After:**
```php
require_once __DIR__ . '/../../../../core/security/input_sanitizer.php';
$buildingId = sanitizeInt($_GET['id'] ?? 0);
```

**File 3:** `userdashboard/sensordata/php/get_user_fire_data.php`

❌ **Before:**
```php
$device_id = $_GET['device_id'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
```

✅ **After:**
```php
require_once __DIR__ . '/../../../../core/security/input_sanitizer.php';
require_once __DIR__ . '/../../../../core/validation/validator.php';

$device_id = sanitizeString($_GET['device_id'] ?? '');
$start_date = sanitizeString($_GET['start_date'] ?? '');
$end_date = sanitizeString($_GET['end_date'] ?? '');

// Validate dates
if (!validateDate($start_date, 'Y-m-d')) {
    die(json_encode(['error' => 'Invalid start date']));
}
if (!validateDate($end_date, 'Y-m-d')) {
    die(json_encode(['error' => 'Invalid end date']));
}
```

**Quick Check:**
```bash
# Find files using $_GET or $_POST directly
grep -r '\$_GET\[' --include="*.php" . | grep -v 'sanitize'
grep -r '\$_POST\[' --include="*.php" . | grep -v 'sanitize'
```

✅ **Done!** Mark checkbox in README.md

---

## ✅ Task 4: Fix SQL Injection Vulnerabilities (2-3 hours)

### The Problem:
Some queries use `query()` instead of prepared statements.

### Files to Fix:

**File 1:** `userdashboard/sensordata/php/add_sample_device.php`

❌ **Before:**
```php
$result = $pdo->query("SELECT * FROM devices WHERE id = $device_id");
```

✅ **After:**
```php
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$device_id]);
$result = $stmt->fetchAll();
```

**File 2:** `userdashboard/sensordata/php/add_sample_data.php`

Same fix as above - replace all `query()` with `prepare()` and `execute()`.

**File 3:** `userdashboard/phone/php/UserPhone.php`

❌ **Before:**
```php
$result = $db->query("UPDATE users SET phone = '$phone' WHERE id = $id");
```

✅ **After:**
```php
$stmt = $db->prepare("UPDATE users SET phone = ? WHERE id = ?");
$stmt->execute([$phone, $id]);
```

**Pattern to Follow:**

```php
// ❌ NEVER DO THIS
$query = "SELECT * FROM table WHERE column = $value";
$result = $pdo->query($query);

// ✅ ALWAYS DO THIS
$query = "SELECT * FROM table WHERE column = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$value]);
$result = $stmt->fetchAll();
```

**Quick Audit:**
```bash
# Find all direct query() calls
grep -r "->query(" --include="*.php" . | grep -v vendor
```

✅ **Done!** Mark checkbox in README.md

---

## 🎉 You're Done with Critical Security!

### What You Just Accomplished:

✅ Removed hardcoded credentials (30 min)  
✅ Removed debug code (1 hour)  
✅ Added input validation (2-3 hours)  
✅ Fixed SQL injection (2-3 hours)  

**Total Time:** 6-8 hours ✅

---

## 📋 Next Steps

Now that security is fixed, you can:

1. **Run Security Audit**
```bash
composer audit
```

2. **Test Everything**
- Test login/registration
- Test device API
- Test fire alerts
- Check error logs

3. **Move to HIGH Priority Items**
- Write unit tests
- Write integration tests
- Update dependencies

4. **Review README.md**
- Update all checkboxes
- Update progress tracking
- Update change log

---

## 🆘 Need Help?

### Common Issues:

**"Cannot connect to database after .env change"**
- Check .env file exists in project root
- Verify credentials are correct
- Test MySQL connection: `mysql -u username -p`

**"Function env() not found"**
- Make sure you include bootstrap: `require_once __DIR__ . '/core/bootstrap.php';`
- Or include env.php directly: `require_once __DIR__ . '/core/config/env.php';`

**"Input validation breaking forms"**
- Test with valid input first
- Check sanitization isn't too aggressive
- Use appropriate sanitizer (sanitizeInt vs sanitizeString)

**"Prepared statements not working"**
- Check PDO connection is active
- Verify SQL syntax (use ? for placeholders)
- Execute with array: `$stmt->execute([$value])`

---

## ✅ Checklist Summary

Update these in README.md:

- [ ] Item 1: Remove Hardcoded Credentials ✅
- [ ] Item 2: Fix Direct Query Usage ✅
- [ ] Item 3: Remove Debug Code ✅
- [ ] Item 4: Implement Input Validation ✅
- [ ] Update Progress Tracking section
- [ ] Update Change Log with today's date
- [ ] Mark status as "In Progress" → "Completed"

---

## 🎯 Success Criteria

Your code is ready when:

✅ No credentials in source code  
✅ No debug output (console.log, print_r, var_dump)  
✅ All inputs sanitized  
✅ All queries use prepared statements  
✅ `composer audit` shows no critical issues  
✅ All tests pass  

---

**Good luck! You've got this! 💪**

**Estimated Completion:** End of today  
**Next Milestone:** Write tests (tomorrow)  
**Production Ready:** 4-6 days from now










