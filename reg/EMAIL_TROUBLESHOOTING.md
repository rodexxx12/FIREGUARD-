# Email Troubleshooting Guide

## Overview
This guide helps resolve email verification code sending issues in the Fire Detection System.

## Current Configuration
- **SMTP Server**: smtp.hostinger.com
- **Email**: fireguard@bccbsis.com
- **Password**: 1j/EIh?7Q
- **Ports**: 465 (SSL) and 587 (STARTTLS)

## Quick Fix Steps

### 1. Test Email Configuration
Run the test script to diagnose issues:
```
http://your-domain.com/reg/test_email.php
```

### 2. Check Common Issues

#### A. Port Blocking
- **Port 465 (SSL)**: Most secure, recommended
- **Port 587 (STARTTLS)**: Alternative if 465 is blocked
- **Port 25**: Usually blocked by hosting providers

#### B. Firewall Issues
- Contact your hosting provider to ensure outbound SMTP is allowed
- Check if your hosting plan includes email functionality

#### C. Credentials
- Verify email and password are correct
- Ensure the email account is active and not suspended
- Check if 2FA is enabled (may require app-specific password)

### 3. Alternative Solutions

#### Option 1: Use Different Port
If port 465 doesn't work, the system automatically tries port 587.

#### Option 2: Contact Hosting Provider
Ask them to:
- Enable outbound SMTP connections
- Verify email account status
- Check for any email sending restrictions

#### Option 3: Use Different Email Service
If Hostinger continues to have issues, consider:
- Gmail SMTP (requires app password)
- SendGrid
- Mailgun
- Amazon SES

## Testing Your Configuration

### Step 1: Run Test Script
1. Navigate to `/reg/test_email.php`
2. Review the output for each configuration
3. Note which configurations work/fail

### Step 2: Check Error Logs
- Review the error log section in the test script
- Check your server's error logs
- Look for specific error messages

### Step 3: Test with Real Email
1. Use the test form in the test script
2. Enter your actual email address
3. Try different configurations

## Error Messages and Solutions

### "SMTP connect() failed"
- **Cause**: Connection to SMTP server failed
- **Solution**: Check firewall, port blocking, or server status

### "Authentication failed"
- **Cause**: Wrong username/password
- **Solution**: Verify credentials, check account status

### "Connection timeout"
- **Cause**: Server response too slow
- **Solution**: Try different port or contact hosting provider

### "SSL certificate error"
- **Cause**: SSL/TLS verification issues
- **Solution**: Current config bypasses this (verify_peer = false)

## Configuration Files

### email_config.php
Contains all SMTP configurations and helper functions.

### send_verification_code.php
Main email sending script that tries multiple configurations.

### test_email.php
Diagnostic script to test and troubleshoot email issues.

## Manual Testing

### Test SMTP Connection
```bash
telnet smtp.hostinger.com 465
telnet smtp.hostinger.com 587
```

### Test with Command Line
```bash
php -r "
require 'vendor/autoload.php';
\$mail = new PHPMailer\PHPMailer\PHPMailer(true);
\$mail->isSMTP();
\$mail->Host = 'smtp.hostinger.com';
\$mail->SMTPAuth = true;
\$mail->Username = 'fireguard@bccbsis.com';
\$mail->Password = '1j/EIh?7Q';
\$mail->SMTPSecure = 'ssl';
\$mail->Port = 465;
echo 'Configuration loaded successfully';
"
```

## Support

### If Issues Persist
1. Run the test script and note all error messages
2. Check server error logs
3. Contact your hosting provider
4. Provide them with:
   - Error messages from test script
   - Server error logs
   - SMTP configuration details

### Contact Information
- **Hosting Provider**: Check your hosting control panel
- **Email Provider**: Hostinger support for email issues
- **System Admin**: For application-specific issues

## Updates and Maintenance

### Regular Checks
- Test email functionality monthly
- Monitor error logs
- Update PHPMailer when available
- Review hosting provider email policies

### Backup Plan
Keep alternative email configurations ready in case primary fails.

---

**Last Updated**: <?php echo date('Y-m-d H:i:s'); ?>
**Version**: 1.0
