# Fix: Enable PHP Zip Extension for XAMPP

## Problem

Composer cannot install PHPUnit and other dependencies because the PHP zip extension is not enabled:

```
Failed to download ... from dist: The zip extension and unzip/7z commands are both missing
```

## Solution

Follow these steps to enable the zip extension in XAMPP on Windows:

### Step 1: Locate php.ini

Find your PHP configuration file:
```
C:\xampp\php\php.ini
```

### Step 2: Edit php.ini

1. **Right-click on `php.ini`** and select **"Edit with Notepad"** or your preferred text editor
2. **Run as Administrator** to ensure you can save changes

### Step 3: Enable Zip Extension

1. Press `Ctrl+F` to open Find dialog
2. Search for: `extension=zip`
3. You should find a line that looks like:
   ```ini
   ;extension=zip
   ```
4. Remove the semicolon (`;`) at the beginning:
   ```ini
   extension=zip
   ```
5. Save the file (`Ctrl+S`)

### Step 4: Verify Other Common Extensions

While you have `php.ini` open, verify these extensions are also enabled (remove `;` if present):

```ini
extension=mbstring
extension=mysqli
extension=pdo_mysql
extension=openssl
extension=curl
extension=fileinfo
extension=gd
extension=zip
```

### Step 5: Restart Apache

**Option A: Using XAMPP Control Panel**
1. Open XAMPP Control Panel
2. Click "Stop" next to Apache
3. Wait for Apache to stop completely
4. Click "Start" next to Apache

**Option B: Using Command Line**
```powershell
# Navigate to XAMPP directory
cd C:\xampp

# Stop Apache
.\apache_stop.bat

# Wait a few seconds, then start Apache
.\apache_start.bat
```

### Step 6: Verify Zip Extension is Loaded

Open PowerShell and run:

```powershell
php -m | findstr zip
```

**Expected Output:**
```
zip
```

If you see `zip` in the output, the extension is successfully enabled!

### Step 7: Install Composer Dependencies

Now you can install PHPUnit and other dependencies:

```powershell
cd C:\xampp\htdocs\DEFENDED
composer install --no-interaction
```

**Expected Output:**
```
Installing dependencies from lock file (including require-dev)
Verifying lock file contents can be installed on current platform.
Package operations: 28 installs, 0 updates, 0 removals
  - Installing sebastian/version (3.0.2): Extracting archive
  - Installing sebastian/type (3.2.1): Extracting archive
  ...
  - Installing phpunit/phpunit (9.6.30): Extracting archive
Generating autoload files
```

### Step 8: Verify PHPUnit Installation

```powershell
# Check if PHPUnit is available
vendor\bin\phpunit --version
```

**Expected Output:**
```
PHPUnit 9.6.30 by Sebastian Bergmann and contributors.
```

## Alternative: Enable All Recommended Extensions

For optimal performance, enable all recommended PHP extensions. In `php.ini`, uncomment (remove `;` from) these lines:

```ini
; Core Extensions
extension=bz2
extension=curl
extension=fileinfo
extension=gd
extension=gettext
extension=mbstring
extension=exif
extension=mysqli
extension=openssl
extension=pdo_mysql
extension=pdo_sqlite
extension=soap
extension=sockets
extension=sodium
extension=xmlrpc
extension=xsl
extension=zip

; Optional but useful
extension=intl
extension=ldap
extension=ftp
```

## Troubleshooting

### Issue: "php.ini changes not taking effect"

**Solution:** Make sure you're editing the correct `php.ini` file:

```powershell
# Check which php.ini file PHP is using
php --ini
```

**Output will show:**
```
Configuration File (php.ini) Path: C:\xampp\php
Loaded Configuration File:         C:\xampp\php\php.ini
```

### Issue: "Cannot save php.ini file"

**Solution:** Run your text editor as Administrator:
1. Right-click Notepad (or your editor)
2. Select "Run as administrator"
3. Open `C:\xampp\php\php.ini`
4. Make changes and save

### Issue: "Apache won't start after changes"

**Solution:** There may be a syntax error in `php.ini`
1. Restore `php.ini` from backup: `C:\xampp\php\php.ini.bak`
2. Carefully re-apply changes
3. Check Apache error logs: `C:\xampp\apache\logs\error.log`

### Issue: "zip extension still not showing"

**Solution:** Verify the PHP zip DLL exists:
1. Check if `php_zip.dll` exists in `C:\xampp\php\ext\`
2. If missing, reinstall XAMPP or download the DLL
3. Ensure the `extension_dir` setting in `php.ini` points to the correct directory:
   ```ini
   extension_dir = "C:\xampp\php\ext"
   ```

## Verification Checklist

- [ ] php.ini file edited (`;extension=zip` → `extension=zip`)
- [ ] File saved successfully
- [ ] Apache restarted
- [ ] `php -m | findstr zip` shows "zip"
- [ ] `composer install` runs without zip extension errors
- [ ] `vendor\bin\phpunit --version` shows PHPUnit version

## Next Steps

After enabling the zip extension and installing dependencies:

1. **Run tests:**
   ```powershell
   composer test
   ```

2. **Generate coverage reports:**
   ```powershell
   composer test-coverage
   ```

3. **Check coverage thresholds:**
   ```powershell
   composer test-coverage-check
   ```

4. **View HTML coverage report:**
   ```powershell
   start coverage\html\index.html
   ```

See [TEST_COVERAGE_SETUP.md](TEST_COVERAGE_SETUP.md) for full documentation.

---

**Status:** ✅ Solution Provided
**Platform:** Windows / XAMPP
**Last Updated:** 2025-12-03













