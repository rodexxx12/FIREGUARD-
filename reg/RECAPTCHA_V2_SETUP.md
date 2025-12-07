# reCAPTCHA v2 Setup Guide

## What Changed

Your registration form has been updated to use **reCAPTCHA v2** (checkbox version) instead of v3 (invisible).

## What You'll See Now

### ✅ Visible Checkbox
Users will now see the familiar **"I'm not a robot"** checkbox that they need to click before submitting the registration form.

### Visual Example
```
┌─────────────────────────────────┐
│  □ I'm not a robot              │
│     reCAPTCHA                   │
│     Privacy - Terms             │
└─────────────────────────────────┘
```

## Setup Requirements

### 1. Get reCAPTCHA v2 Keys

If you haven't already, you need to register your site at:
**https://www.google.com/recaptcha/admin**

1. Click "+" to create a new site
2. Enter a label (e.g., "FireGuard Registration")
3. Select **"reCAPTCHA v2"** → **"I'm not a robot" Checkbox**
4. Add your domains:
   - `localhost` (for development)
   - Your production domain (e.g., `yourdomain.com`)
5. Accept the terms and click "Submit"
6. Copy the **Site Key** and **Secret Key**

### 2. Add Keys to .env File

Open your `.env` file (in the project root: `C:\xampp\htdocs\DEFENDED\.env`) and add:

```env
RECAPTCHA_SITE_KEY=6LcXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
RECAPTCHA_SECRET_KEY=6LcYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYY
```

**Important:** Make sure you're using **v2 keys**, not v3 keys!

### 3. Test the Registration

1. Navigate to: `http://localhost/DEFENDED/reg/registration.php`
2. Fill out all registration steps
3. On the final "Credentials" step, you should see the reCAPTCHA checkbox
4. Click the checkbox and complete any additional verification if prompted
5. Click "Complete Registration"

## How It Works

### For Users

1. Fill out the registration form
2. On the final step, **check the "I'm not a robot" box**
3. May need to complete additional verification (select images, etc.)
4. Click "Complete Registration"
5. Form submits successfully

### For You (Admin)

- **Front-end:** Shows visible checkbox widget using Google's API
- **Back-end:** Validates the response token with Google's servers
- **Security:** Protects against bots and automated attacks

## User Experience

### What Users See

1. **Info Box:**
   ```
   ℹ Security Verification Required
   
   Please check the "I'm not a robot" box above before 
   clicking "Complete Registration". This verifies you're 
   human and helps protect our system from spam and bots.
   ```

2. **The Checkbox:**
   - Visible checkbox with reCAPTCHA branding
   - May show additional challenges (image selection)

3. **Submit Button:**
   ```
   🛡 Complete Registration
   ```

### If Users Forget to Check

They'll see an alert:
```
Please complete the reCAPTCHA verification by checking 
the "I'm not a robot" box before submitting.
```

The page will automatically scroll to the checkbox and highlight it.

## Troubleshooting

### Issue: Checkbox doesn't appear

**Solutions:**
1. Check that `RECAPTCHA_SITE_KEY` is set in `.env`
2. Verify the key is for **v2**, not v3
3. Check browser console (F12) for errors
4. Disable ad blockers
5. Check internet connection

### Issue: "Invalid site key" error

**Solutions:**
1. Verify you copied the correct site key from Google
2. Make sure the domain is registered in reCAPTCHA admin panel
3. For localhost, ensure `localhost` is in the domains list

### Issue: "ERROR for site owner: Invalid domain"

**Solutions:**
1. Go to reCAPTCHA admin panel
2. Edit your site
3. Add the domain you're testing on (e.g., `localhost`, `127.0.0.1`)

### Issue: Form submits without checking box

**Solution:** Clear browser cache and refresh the page

## Differences: v2 vs v3

| Feature | v2 (Current) | v3 (Previous) |
|---------|--------------|---------------|
| **Visibility** | Visible checkbox | Invisible |
| **User Action** | Must click checkbox | No action needed |
| **Challenges** | May show image selection | None |
| **Keys** | Separate v2 keys | Separate v3 keys |
| **User Friction** | Medium (checkbox + possible challenges) | Low (invisible) |
| **Bot Detection** | Good | Better |

## Testing Checklist

- [ ] reCAPTCHA checkbox appears on credentials step
- [ ] Checkbox is clickable and responsive
- [ ] Submitting without checking shows error
- [ ] Submitting with check succeeds
- [ ] Error message is clear and helpful
- [ ] Expired verification is handled (try waiting 2 minutes)
- [ ] Works on mobile devices
- [ ] Works on different browsers (Chrome, Firefox, Edge)

## Backend Validation

The server-side validation remains the same:

```php
// Server checks the response token
$recaptcha_token = $_POST['g-recaptcha-response'] ?? '';
$recaptcha_result = verify_recaptcha($recaptcha_token);

if (!$recaptcha_result['success']) {
    $errors['recaptcha'] = "Please complete the security verification.";
}
```

This validates the token with Google's API and ensures it's legitimate.

## Security Benefits

✅ **Bot Protection:** Blocks automated registration attempts  
✅ **Spam Prevention:** Reduces spam accounts  
✅ **Fraud Detection:** Identifies suspicious behavior  
✅ **Easy for Humans:** Simple checkbox for real users  
✅ **Hard for Bots:** Complex challenges for automated scripts

## Privacy & Data

- reCAPTCHA is provided by Google
- Collects: IP address, browser info, mouse movements
- Uses: Behavior analysis to detect bots
- Privacy policy: https://policies.google.com/privacy

## Need Help?

1. **Check Console:** Press F12 → Console tab for errors
2. **Test Keys:** Use check script: `check_recaptcha_config.php`
3. **Google Docs:** https://developers.google.com/recaptcha/docs/display
4. **Check Logs:** `C:\xampp\htdocs\DEFENDED\logs\php_errors.log`

## Quick Commands

### Test Configuration
```
http://localhost/DEFENDED/reg/check_recaptcha_config.php
```

### View Logs
```
tail -f C:\xampp\htdocs\DEFENDED\logs\php_errors.log
```

### Clear Cache (if needed)
1. Browser: Ctrl + Shift + Delete
2. Server: Restart Apache in XAMPP

---

**Last Updated:** 2024  
**Version:** reCAPTCHA v2 (Checkbox)  
**Status:** ✅ Configured and Ready







