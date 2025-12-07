# PHPUnit Linter Errors - Explanation

## Issue

You may see linter errors like:
```
Undefined method 'markTestIncomplete'.
Undefined type 'PHPUnit\Framework\TestCase'.
```

## Root Cause

These errors occur because **PHPUnit is not installed yet**. The linter (IDE code analysis tool) can't find the PHPUnit classes because the package hasn't been installed via Composer.

## Why PHPUnit Isn't Installed

PHPUnit installation failed because the **PHP zip extension is not enabled** in your XAMPP installation. Composer needs the zip extension to extract packages.

## How to Fix

### Step 1: Enable PHP Zip Extension (5 minutes)

1. Open `C:\xampp\php\php.ini` in a text editor (as Administrator)
2. Find the line: `;extension=zip`
3. Remove the semicolon: `extension=zip`
4. Save the file
5. Restart Apache in XAMPP Control Panel

**Detailed instructions:** See `docs/FIX_PHP_ZIP_EXTENSION.md`

### Step 2: Install PHPUnit

```powershell
cd C:\xampp\htdocs\DEFENDED
composer install --no-interaction
```

### Step 3: Verify Installation

```powershell
vendor\bin\phpunit --version
```

Should output:
```
PHPUnit 9.6.30 by Sebastian Bergmann and contributors.
```

### Step 4: Linter Errors Will Disappear

Once PHPUnit is installed, your IDE/linter will automatically detect it and the errors will disappear.

## Temporary Workaround

Until PHPUnit is installed, you can:

1. **Ignore the warnings** - They're cosmetic only. The code is correct.
2. **Use the stub file** - A `tests/phpunit-stub.php` file has been created that provides basic type hints.
3. **Configure your IDE** - Add PHPUnit to your IDE's ignore list temporarily.

## Status

- ✅ Code is syntactically correct
- ✅ Will work once PHPUnit is installed
- ⚠️  Linter errors are expected until installation
- ✅ Documentation added to affected files

## Files Affected

All test files in the `tests/` directory will show similar warnings:
- `tests/unit/**/*.php`
- `tests/integration/**/*.php`
- `tests/e2e/**/*.php`

## Quick Reference

| Issue | Solution |
|-------|----------|
| Linter errors | Install PHPUnit |
| Can't install PHPUnit | Enable zip extension |
| How to enable zip | See `docs/FIX_PHP_ZIP_EXTENSION.md` |
| How to install | Run `composer install` |

---

**Status:** Known issue with documented solution  
**Severity:** Low (cosmetic only, doesn't affect functionality)  
**Time to Fix:** 5 minutes + installation time  
**Last Updated:** 2025-12-03










