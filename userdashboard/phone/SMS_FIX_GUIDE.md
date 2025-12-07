# 🔧 SMS API Error 406 - Fix Guide

## ❌ Error Message
```
SMS API authentication failed. Error Code: 406 (API Key Rejected)
```

## 🔍 What This Means

**Error 406** = "API Key mismatch" or "API key not acceptable"

This means:
- Your API key is **invalid** or **expired**
- The API key has been **revoked** by PageNet
- The API key format is correct, but the key itself is wrong

## ✅ Your .env File Format is CORRECT

Your `.env` file (lines 76-80) is properly formatted:
```
# SMS API Configuration
SMS_API_KEY=6PLX3NFL2A2FLQ81RI7X6C4PJP68ANLJNYQ7XAR6
SMS_DEVICE_ID=d8d8e6131b00f1a4
SMS_API_URL=https://sms.pagenet.info/api/v1/sms/send
```

✅ No quotes  
✅ No extra spaces  
✅ Correct format  

**The problem is the API key itself, not the format.**

## 🚀 How to Fix

### Step 1: Contact PageNet
1. **Call or email PageNet support**
2. Tell them: "My API key is being rejected with Error 406"
3. Request:
   - A **new, valid API key**
   - A **new device ID** (if needed)
   - Verification that your account is active

### Step 2: Update Your .env File
Once you have the new credentials:

1. Open: `C:\xampp\htdocs\DEFENDED\.env`
2. Go to lines 76-80
3. Replace the values:
   ```
   SMS_API_KEY=your_new_api_key_from_pagenet
   SMS_DEVICE_ID=your_new_device_id_from_pagenet
   SMS_API_URL=https://sms.pagenet.info/api/v1/sms/send
   ```
4. **Save the file**

### Step 3: Test Your Credentials
1. Open: `http://localhost/DEFENDED/userdashboard/phone/diagnose_sms.php`
2. The tool will automatically test your new API key
3. Look for: ✅ **SUCCESS!** message

### Step 4: Try Again
1. Refresh the phone management page
2. Try adding/verifying a phone number again
3. SMS should now work!

## 🛠️ Diagnostic Tools

### 1. SMS Diagnostic Tool
**URL:** `http://localhost/DEFENDED/userdashboard/phone/diagnose_sms.php`

**What it does:**
- Shows which API key is being used
- Tests the API key directly with PageNet
- Shows detailed error information
- Compares .env vs device/config.php

### 2. .env Validator
**URL:** `http://localhost/DEFENDED/userdashboard/phone/validate_env.php`

**What it does:**
- Validates your .env file format
- Checks for common formatting issues
- Shows current values

### 3. SMS Test Script
**URL:** `http://localhost/DEFENDED/userdashboard/phone/test_sms_api.php`

**What it does:**
- Tests SMS API with a sample message
- Shows detailed response from PageNet

## 📋 Quick Checklist

- [ ] Contacted PageNet for new API key
- [ ] Received new API key and device ID
- [ ] Updated .env file (lines 76-80)
- [ ] Tested credentials using diagnose_sms.php
- [ ] Verified test shows ✅ SUCCESS
- [ ] Tried adding phone number again

## ⚠️ Common Mistakes

1. **Adding quotes:** ❌ `SMS_API_KEY="key"` → ✅ `SMS_API_KEY=key`
2. **Adding spaces:** ❌ `SMS_API_KEY = key` → ✅ `SMS_API_KEY=key`
3. **Using old/expired key:** Always get fresh credentials from PageNet
4. **Not saving .env file:** Make sure to save after editing

## 📞 Need Help?

If you've followed all steps and still get Error 406:
1. Double-check the API key from PageNet (copy-paste carefully)
2. Verify your PageNet account is active and paid
3. Check if PageNet has IP whitelisting requirements
4. Contact PageNet again to verify the API key is correct

## 🔗 Related Files

- `.env` - Main configuration file (root directory)
- `device/config.php` - Fallback configuration
- `userdashboard/phone/config/config.php` - SMS config loader
- `userdashboard/phone/php/UserPhone.php` - SMS sending code

---

**Last Updated:** 2025-12-07  
**Error Code:** 406 (API Key Rejected)  
**Status:** Waiting for valid API key from PageNet






