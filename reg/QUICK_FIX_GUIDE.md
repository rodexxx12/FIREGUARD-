# Quick Fix Guide - Email Verification Issue

## 🚨 **IMMEDIATE ACTION REQUIRED**

Your registration is working, but the verification email is not being sent. Here's how to fix it:

## 🔧 **Step 1: Test Email Configuration**

Run this test script to diagnose the issue:
```
http://your-domain.com/reg/simple_email_test.php
```

## 🔍 **Step 2: Check Common Issues**

### **A. Hostinger SMTP Restrictions**
- **Problem**: Some Hostinger hosting plans don't allow outbound SMTP
- **Solution**: Contact Hostinger support to enable outbound SMTP on ports 465/587

### **B. Firewall Blocking**
- **Problem**: Server firewall blocking SMTP connections
- **Solution**: Ask hosting provider to whitelist SMTP ports

### **C. Email Account Issues**
- **Problem**: Email account suspended or credentials wrong
- **Solution**: Verify email account status at https://mail.hostinger.com/

## 🚀 **Step 3: Quick Fixes**

### **Option 1: Enable Debug Mode**
Edit `reg/send_verification_code.php` and change:
```php
$mail->SMTPDebug = SMTP::DEBUG_OFF;
```
to:
```php
$mail->SMTPDebug = SMTP::DEBUG_SERVER;
```

### **Option 2: Try Different Port**
The system automatically tries port 465 first, then 587. If both fail, the issue is server-side.

### **Option 3: Check Error Logs**
Look for email errors in:
- `reg/php_errors.log`
- Server error logs
- Browser console

## 📧 **Step 4: Alternative Solutions**

### **If Hostinger SMTP Fails:**
1. **Gmail SMTP** (requires app password)
2. **SendGrid** (free tier available)
3. **Mailgun** (free tier available)
4. **Amazon SES** (very cheap)

### **Gmail Setup Example:**
```php
$mail->Host = 'smtp.gmail.com';
$mail->Port = 587;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password'; // Generate in Gmail settings
```

## 🧪 **Step 5: Testing**

### **Test 1: Basic Connection**
```bash
telnet smtp.hostinger.com 465
telnet smtp.hostinger.com 587
```

### **Test 2: PHP Test**
```php
<?php
require 'vendor/autoload.php';
$mail = new PHPMailer\PHPMailer\PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.hostinger.com';
$mail->SMTPAuth = true;
$mail->Username = 'fireguard@bccbsis.com';
$mail->Password = '1j/EIh?7Q';
$mail->SMTPSecure = 'ssl';
$mail->Port = 465;
echo 'Configuration loaded successfully';
?>
```

## 📞 **Step 6: Contact Support**

### **What to Tell Hostinger:**
1. "I need outbound SMTP enabled on ports 465 and 587"
2. "My application needs to send emails via SMTP"
3. "Please check if my hosting plan includes email functionality"
4. "Verify my email account status"

### **What to Provide:**
- Error messages from test scripts
- Server error logs
- SMTP configuration details

## 🔄 **Step 7: Fallback Plan**

If Hostinger continues to have issues:

1. **Switch to Gmail SMTP** (most reliable)
2. **Use SendGrid** (professional email service)
3. **Consider changing hosting provider** (if email is critical)

## 📋 **Checklist**

- [ ] Run `simple_email_test.php`
- [ ] Check error logs
- [ ] Test SMTP connection
- [ ] Contact Hostinger support
- [ ] Try alternative email services
- [ ] Verify email account status

## ⚡ **Emergency Fix**

If you need emails working immediately:

1. **Use Gmail SMTP** (most reliable)
2. **Generate app password** in Gmail settings
3. **Update email configuration** in the code
4. **Test immediately**

## 📚 **Additional Resources**

- **Hostinger Support**: https://support.hostinger.com/
- **Gmail App Passwords**: https://myaccount.google.com/apppasswords
- **SendGrid Free Tier**: https://sendgrid.com/free/
- **Mailgun Free Tier**: https://www.mailgun.com/pricing

---

**Priority**: HIGH - Email verification is critical for user registration
**Estimated Fix Time**: 1-4 hours (depending on hosting provider response)
**Impact**: Users cannot complete registration without email verification

**Last Updated**: <?php echo date('Y-m-d H:i:s'); ?>
**Status**: ACTIVE ISSUE - REQUIRES IMMEDIATE ATTENTION
