# reCAPTCHA Setup Guide

## Quick Fix: Registration Without reCAPTCHA

**Good news!** The system now works without reCAPTCHA configured. You can register immediately, but it's recommended to set up reCAPTCHA for security.

### Current Status
- ✅ Registration will work without reCAPTCHA (development mode)
- ⚠️ No bot protection until reCAPTCHA is configured
- 📝 You'll see a warning message on the registration form

---

## How to Setup reCAPTCHA (Recommended for Production)

### Step 1: Get reCAPTCHA Keys from Google

1. **Visit Google reCAPTCHA Admin Console:**
   - Go to: https://www.google.com/recaptcha/admin

2. **Sign in with your Google account**

3. **Register a new site:**
   - **Label:** FireGuard Registration (or any name you prefer)
   - **reCAPTCHA type:** Select **reCAPTCHA v3**
   - **Domains:** Add your domain(s):
     - For local development: `localhost`
     - For production: `yourdomain.com`
   - **Accept terms** and click **Submit**

4. **Copy your keys:**
   - **Site Key** (starts with `6L...`)
   - **Secret Key** (starts with `6L...`)

### Step 2: Add Keys to .env File

1. **Open your .env file:**
   ```
   C:\xampp\htdocs\DEFENDED\.env
   ```

2. **Add these lines** (or update if they exist):
   ```env
   RECAPTCHA_SITE_KEY=your_site_key_here
   RECAPTCHA_SECRET_KEY=your_secret_key_here
   ```

3. **Replace** `your_site_key_here` and `your_secret_key_here` with the actual keys from Google

4. **Save the file**

### Step 3: Verify Configuration

1. **Run the configuration checker:**
   - Navigate to: `http://localhost/DEFENDED/reg/check_recaptcha_config.php`
   - This will verify your setup

2. **Check for:**
   - ✅ All checks should show "PASS"
   - ✅ reCAPTCHA script should load successfully
   - ✅ Test execution should complete

### Step 4: Test Registration

1. Go to the registration page
2. Fill out all steps
3. On the final step, you should see:
   - Blue info box: "Security Verification"
   - "Verifying security..." message when submitting
4. Registration should complete successfully

---

## Example .env Configuration

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=your_database
DB_USER=root
DB_PASS=your_password

# reCAPTCHA Configuration
RECAPTCHA_SITE_KEY=6LcXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
RECAPTCHA_SECRET_KEY=6LcYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYY

# Email Configuration (if needed)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
```

---

## Troubleshooting

### Issue: "Please complete the security verification" Error

**Possible Causes:**
1. reCAPTCHA keys not configured
2. Wrong keys (site key vs secret key swapped)
3. Domain not added to reCAPTCHA admin console
4. Ad blocker blocking reCAPTCHA
5. Internet connection issues

**Solutions:**
1. Run `check_recaptcha_config.php` to diagnose
2. Verify keys in .env file are correct
3. Add your domain to Google reCAPTCHA admin
4. Disable ad blockers
5. Check browser console (F12) for errors

### Issue: reCAPTCHA Not Loading

**Check:**
1. Internet connection
2. Browser console for errors (F12 → Console)
3. Ad blockers or browser extensions
4. Firewall/security software

**Try:**
1. Different browser
2. Incognito/private window
3. Disable extensions temporarily

### Issue: Keys Not Working

**Verify:**
1. Keys are copied correctly (no extra spaces)
2. Using reCAPTCHA v3 keys (not v2)
3. Domain is added in reCAPTCHA admin console
4. .env file is in the correct location

---

## Development vs Production

### Development (localhost)
- reCAPTCHA is **optional**
- System will work without it
- Warning message will appear
- Recommended to set up for testing

### Production (live server)
- reCAPTCHA is **strongly recommended**
- Protects against spam and bots
- Required for security compliance
- Must use your actual domain in reCAPTCHA admin

---

## Testing reCAPTCHA

### Quick Test
1. Open browser console (F12)
2. Go to registration page (credentials step)
3. Check console for:
   ```
   reCAPTCHA script loaded successfully
   reCAPTCHA is ready
   ```

### Full Test
1. Complete registration form
2. Click "Complete Registration"
3. Watch for "Verifying security..." message
4. Registration should complete
5. Check logs for any errors

---

## Security Notes

- **Never commit .env file to git** (it's in .gitignore)
- **Keep secret key private** (never share or expose)
- **Use different keys** for development and production
- **Monitor reCAPTCHA admin console** for abuse reports
- **Rotate keys periodically** for security

---

## Support Tools

1. **Configuration Checker:**
   - `http://localhost/DEFENDED/reg/check_recaptcha_config.php`
   - Diagnoses setup issues

2. **User Guide:**
   - `SECURITY_VERIFICATION_GUIDE.md`
   - Explains reCAPTCHA to end users

3. **Error Logs:**
   - `C:\xampp\htdocs\DEFENDED\logs\php_errors.log`
   - Check for reCAPTCHA errors

---

## FAQ

**Q: Do I need a Google account?**  
A: Yes, to create reCAPTCHA keys in the admin console.

**Q: Is reCAPTCHA free?**  
A: Yes, Google reCAPTCHA is free for most websites.

**Q: Can I use the same keys for multiple domains?**  
A: Yes, add all domains in the reCAPTCHA admin console.

**Q: What's the difference between v2 and v3?**  
A: v3 is invisible and automatic. v2 shows checkboxes. We use v3.

**Q: Can I skip reCAPTCHA setup?**  
A: Yes, for development. But it's required for production security.

**Q: How do I know if it's working?**  
A: Run `check_recaptcha_config.php` - all checks should pass.

---

## Need Help?

1. **Run the checker:** `check_recaptcha_config.php`
2. **Check error logs:** Look for reCAPTCHA errors
3. **Browser console:** Check for JavaScript errors
4. **Contact support:** Provide error messages and configuration status

---

**Last Updated:** 2024  
**Version:** reCAPTCHA v3  
**Status:** Optional for development, Recommended for production

