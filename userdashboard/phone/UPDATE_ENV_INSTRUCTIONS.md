# 📝 How to Update .env File with New SMS Credentials

## Current Status
- ❌ **API Key is INVALID** (Error 406)
- ✅ **Format is CORRECT** (no quotes, no spaces)
- ⚠️ **Need NEW credentials from PageNet**

## Step-by-Step Instructions

### Step 1: Get New Credentials from PageNet
1. Contact PageNet support
2. Request: "I need a new SMS API key - my current key is being rejected with Error 406"
3. They will provide:
   - New `SMS_API_KEY` (a long string of letters/numbers)
   - New `SMS_DEVICE_ID` (usually a shorter string)

### Step 2: Open .env File
**Location:** `C:\xampp\htdocs\DEFENDED\.env`

**Recommended Editor:** Notepad++ or VS Code (not regular Notepad)

### Step 3: Find Lines 76-80
Look for this section:
```env
# SMS API Configuration
SMS_API_KEY=6PLX3NFL2A2FLQ81RI7X6C4PJP68ANLJNYQ7XAR6
SMS_DEVICE_ID=d8d8e6131b00f1a4
SMS_API_URL=https://sms.pagenet.info/api/v1/sms/send
```

### Step 4: Update the Values
Replace the values with your NEW credentials from PageNet:

```env
# SMS API Configuration
SMS_API_KEY=PASTE_NEW_API_KEY_HERE
SMS_DEVICE_ID=PASTE_NEW_DEVICE_ID_HERE
SMS_API_URL=https://sms.pagenet.info/api/v1/sms/send
```

**Important:**
- ❌ NO quotes: `SMS_API_KEY="key"` ❌
- ✅ NO quotes: `SMS_API_KEY=key` ✅
- ❌ NO spaces: `SMS_API_KEY = key` ❌
- ✅ NO spaces: `SMS_API_KEY=key` ✅
- ✅ Each value on ONE line
- ✅ No trailing spaces

### Step 5: Save the File
- Press `Ctrl+S` to save
- Make sure the file is saved as `.env` (not `.env.txt`)

### Step 6: Test Your Credentials
1. Open: `http://localhost/DEFENDED/userdashboard/phone/diagnose_sms.php`
2. The tool will test your new API key
3. Look for: ✅ **SUCCESS!** message

### Step 7: Try Again
1. Refresh the phone management page
2. Try adding/verifying a phone number
3. SMS should now work! ✅

## Example of Correct Format

**✅ CORRECT:**
```env
SMS_API_KEY=ABCD1234EFGH5678IJKL9012MNOP3456QRST7890
SMS_DEVICE_ID=abc123def456
SMS_API_URL=https://sms.pagenet.info/api/v1/sms/send
```

**❌ WRONG (has quotes):**
```env
SMS_API_KEY="ABCD1234EFGH5678IJKL9012MNOP3456QRST7890"
```

**❌ WRONG (has spaces):**
```env
SMS_API_KEY = ABCD1234EFGH5678IJKL9012MNOP3456QRST7890
```

**❌ WRONG (multiple lines):**
```env
SMS_API_KEY=ABCD1234EFGH5678
IJKL9012MNOP3456QRST7890
```

## Troubleshooting

### Still Getting Error 406?
1. Double-check you copied the API key correctly (no extra spaces)
2. Verify the API key from PageNet is active
3. Check if PageNet has IP whitelisting requirements
4. Contact PageNet again to verify the key is correct

### File Won't Save?
- Make sure you have write permissions
- Try running your editor as Administrator
- Check if the file is read-only (right-click → Properties → uncheck Read-only)

### Can't Find .env File?
- It's in the root directory: `C:\xampp\htdocs\DEFENDED\.env`
- Make sure "Show hidden files" is enabled in Windows Explorer
- The file might be named `.env` (with the dot at the start)

## Quick Reference

**File Location:** `C:\xampp\htdocs\DEFENDED\.env`  
**Lines to Update:** 77-78  
**Format:** `KEY=value` (no quotes, no spaces)  
**Test Tool:** `diagnose_sms.php`

---

**Need Help?** Check `SMS_FIX_GUIDE.md` for more detailed troubleshooting.






