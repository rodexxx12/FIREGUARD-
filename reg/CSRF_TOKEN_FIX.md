# CSRF Token Fix - Confirmation Form

## Issue
**Error:** "Invalid security token. Please refresh the page and try again."

**Cause:** The confirmation form was missing the CSRF (Cross-Site Request Forgery) token that all other forms have.

## What Was Fixed

### Before
```php
<form method="POST" action="" id="confirm-form">
    <div style="background: #fff3cd; ...">
        <!-- Important notice -->
    </div>
    <!-- Submit button -->
</form>
```

### After
```php
<form method="POST" action="" id="confirm-form">
    <?php 
    $csrf_token = generate_csrf_token();
    $honeypot_field = add_honeypot_field();
    ?>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="text" name="<?php echo htmlspecialchars($honeypot_field); ?>" style="display:none;visibility:hidden;" tabindex="-1" autocomplete="off">
    
    <div style="background: #fff3cd; ...">
        <!-- Important notice -->
    </div>
    <!-- Submit button -->
</form>
```

## What Was Added

1. **CSRF Token Generation**
   ```php
   $csrf_token = generate_csrf_token();
   ```
   - Generates a unique security token
   - Stored in session
   - Expires after 2 hours

2. **CSRF Token Hidden Field**
   ```php
   <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
   ```
   - Sent with form submission
   - Validated on server-side

3. **Honeypot Field**
   ```php
   $honeypot_field = add_honeypot_field();
   <input type="text" name="<?php echo htmlspecialchars($honeypot_field); ?>" style="display:none;visibility:hidden;" tabindex="-1" autocomplete="off">
   ```
   - Hidden field to catch bots
   - Real users won't fill it
   - Bots typically fill all fields

## How CSRF Protection Works

### 1. Token Generation
- When form loads, server generates unique token
- Token stored in PHP session
- Token embedded in form as hidden field

### 2. Form Submission
- User fills out form and clicks submit
- Hidden CSRF token sent with form data

### 3. Server Validation
```php
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($csrf_token)) {
    $errors['security'] = "Invalid security token. Please refresh the page and try again.";
}
```

### 4. Token Validation
- Server checks if token matches session
- Ensures request came from legitimate form
- Protects against cross-site attacks

## Security Features

### CSRF Token
- **Purpose:** Prevent cross-site request forgery attacks
- **Lifetime:** 2 hours
- **Regeneration:** Every 30 minutes
- **Validation:** Uses timing-safe comparison (`hash_equals`)

### Honeypot Field
- **Purpose:** Catch automated bots
- **Behavior:** 
  - Hidden from real users
  - Bots fill it automatically
  - If filled, submission is rejected

## All Forms Now Protected

✅ **Personal Information Form** - Has CSRF token  
✅ **Location Form** - Has CSRF token  
✅ **Device Form** - Has CSRF token  
✅ **Credentials Form** - Has CSRF token  
✅ **Confirmation Form** - Has CSRF token (FIXED!)

## Testing

### Normal Registration
1. Go through all registration steps
2. Submit each form normally
3. Should work without errors

### Security Test
Try submitting without token (developer test):
```javascript
// Remove token from form
document.querySelector('input[name="csrf_token"]').remove();
// Try to submit
document.getElementById('confirm-form').submit();
// Result: Should get "Invalid security token" error
```

## Error Messages

### Valid Submission
- No error message
- Form processes normally

### Invalid Token
```
Invalid security token. Please refresh the page and try again.
```
- **Cause:** Token missing, expired, or doesn't match
- **Solution:** Refresh page and try again

### Token Expired
```
Invalid security token. Please refresh the page and try again.
```
- **Cause:** Token older than 2 hours
- **Solution:** Refresh page to get new token

## Troubleshooting

### Issue: Still getting CSRF error
**Solutions:**
1. Clear browser cookies
2. Clear PHP sessions: `rm /tmp/sess_*` (Linux) or restart XAMPP
3. Check if sessions are working: `session_status() === PHP_SESSION_ACTIVE`
4. Verify `session_start()` is called before forms load

### Issue: Error on every form submission
**Solutions:**
1. Check if cookies are enabled in browser
2. Verify session configuration in `php.ini`
3. Check file permissions on session directory
4. Look for JavaScript errors blocking form submission

### Issue: Token expires too quickly
**Solution:** Adjust token lifetime in `security_functions.php`:
```php
// Current: 2 hours (7200 seconds)
if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 7200) {
    // Change 7200 to desired seconds
}
```

## Files Modified

- **registration.php** - Added CSRF token to confirmation form

## Related Files

- **security_functions.php** - Contains CSRF functions
  - `generate_csrf_token()` - Generates tokens
  - `validate_csrf_token()` - Validates tokens
  - `add_honeypot_field()` - Generates honeypot field names

## Best Practices

### Always Include CSRF Tokens
Every form that modifies data should have:
1. CSRF token generation
2. Hidden token field
3. Server-side validation

### Token Lifecycle
1. **Generate:** When form loads
2. **Send:** Hidden field in POST data
3. **Validate:** On form submission
4. **Regenerate:** Periodically for security

### Security Tips
- Never expose tokens in URLs (GET parameters)
- Always use POST for data-modifying operations
- Validate tokens server-side (never trust client)
- Use timing-safe comparison to prevent timing attacks

---

**Status:** ✅ Fixed  
**Date:** 2024  
**Impact:** All registration forms now properly protected







