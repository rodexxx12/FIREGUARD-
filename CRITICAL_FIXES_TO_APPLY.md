# 🚨 CRITICAL FIXES - Apply These IMMEDIATELY

This document lists the most critical fixes that must be applied before deployment.

## Priority Order

### 1. Remove Hardcoded Plaintext Password (5 minutes) 🔴

**File:** `device/smoke_api.php` Line 223

**Current Code:**
```php
$conn->query("INSERT INTO users (user_id, username, email, password, first_name, last_name, phone) 
              VALUES (1, 'arduino_user', 'arduino@firedetection.com', 'password', 'Arduino', 'User', '+639318261972')
              ON DUPLICATE KEY UPDATE user_id = user_id");
```

**Fix:**
```php
// Remove this default user creation entirely, or require proper registration
// If absolutely necessary, use secure password:
$securePassword = bin2hex(random_bytes(32));
$hashedPassword = password_hash($securePassword, PASSWORD_BCRYPT);
// Store hash, not plaintext, and log securely for admin
```

---

### 2. Disable Debug Mode in Production (15 minutes) 🔴

**Files:** Multiple (see list in main report)

**Quick Fix Script:**
```bash
# Find all files with display_errors = 1
find . -name "*.php" -type f -exec grep -l "display_errors.*1" {} \;
```

Apply environment-aware error handling to each file.

---

### 3. Add Rate Limiting to Device APIs (20 minutes) 🟠

**Files:** `device/smoke_api.php`, `device/smoke_gps.php`

**Add at top of file:**
```php
require_once __DIR__ . '/../core/rate_limit/rate_limiter.php';

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateLimitResult = checkRateLimit($clientIp, 'device_api', 100, 60);

if (!$rateLimitResult['allowed']) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests']);
    exit;
}
```

---

### 4. Fix CORS Wildcard (10 minutes) 🟠

**Files:** `device/smoke_api.php`, `device/smoke_gps.php`

**Replace:**
```php
header('Access-Control-Allow-Origin: *');
```

**With:**
```php
$allowedOrigins = ['https://your-domain.com']; // Add your domains
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
```

---

## 📋 Testing After Fixes

After applying each fix:
1. Test the affected functionality
2. Verify no errors are exposed
3. Test rate limiting works
4. Verify CORS is properly configured
5. Check logs for any issues

---

**Apply these fixes in order, test after each one!**

