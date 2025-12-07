# Rollback Testing Guide

## ✅ Status: IMPLEMENTED & TESTED

This guide provides comprehensive instructions for testing rollback procedures in the Fire Detection System.

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Test Framework](#test-framework)
4. [Running Tests](#running-tests)
5. [Staging Environment Testing](#staging-environment-testing)
6. [Manual Testing Procedures](#manual-testing-procedures)
7. [Verification Checklist](#verification-checklist)
8. [Troubleshooting](#troubleshooting)

---

## Overview

### What is Rollback Testing?

Rollback testing verifies that the system can safely revert to a previous version in case of:
- Failed deployments
- Critical bugs in production
- Data corruption
- Security incidents

### What Gets Tested?

1. **Backup Creation** - Verify backups are complete and valid
2. **Rollback Execution** - Test the rollback process works correctly
3. **Data Integrity** - Ensure data is preserved during rollback
4. **System Functionality** - Confirm system works after rollback
5. **Pre-Rollback Safety** - Verify current state is backed up before rollback

---

## Prerequisites

### System Requirements

#### Windows (XAMPP)
- PowerShell 5.1 or higher
- XAMPP installed and running
- Write permissions to backup directory
- MySQL command-line tools available

#### Linux
- Bash shell
- MySQL/MariaDB client installed
- Write permissions to backup directory
- sudo access (if needed)

### Before Testing

1. **Backup Production Data**
   ```powershell
   # Windows
   .\scripts\create-backup.ps1 -BackupDir "C:\backups\production"
   
   # Linux
   BACKUP_DIR=/backups/production bash scripts/create-backup.sh
   ```

2. **Ensure Test Environment**
   - Use staging or test environment, NOT production
   - Have adequate disk space (2-3x application size)
   - Database is accessible

3. **Review Scripts**
   - `scripts/create-backup.ps1` / `.sh` - Creates backups
   - `scripts/create-rollback.ps1` / `.sh` - Performs rollback
   - `scripts/test-rollback.ps1` / `.sh` - Automated testing

---

## Test Framework

### Automated Test Script

The automated test framework (`test-rollback.ps1` / `test-rollback.sh`) performs:

1. **Prerequisites Check**
   - Verifies scripts exist
   - Checks directory permissions
   - Validates environment

2. **Backup Creation Test**
   - Creates test marker file
   - Runs backup script
   - Verifies backup integrity

3. **Rollback Simulation**
   - Modifies test marker file
   - Executes rollback script
   - Verifies restoration

4. **Verification**
   - Checks file integrity
   - Validates critical files
   - Confirms data restoration

5. **Cleanup**
   - Removes test artifacts
   - Preserves test backups for review

### Test Output

```
========================================
   ROLLBACK TESTING FRAMEWORK
========================================

Test Directory: C:\backups\test
App Directory:  C:\xampp\htdocs\DEFENDED

[1/7] Testing Prerequisites...
✓ PASS: Backup script exists
✓ PASS: Rollback script exists
✓ PASS: Application directory exists
✓ PASS: Write permissions OK

[2/7] Testing Backup Creation...
✓ PASS: Backup directory created
✓ PASS: Test marker file created
✓ PASS: Backup script executed
✓ PASS: Backup directory created
✓ PASS: Backup contains files

[... continues ...]

========================================
         TEST RESULTS SUMMARY
========================================

Total Tests:  24
Passed:       24
Failed:       0
Pass Rate:    100%

========================================

✓ ALL TESTS PASSED!
  Rollback procedures are working correctly.
```

---

## Running Tests

### Automated Testing

#### Windows (PowerShell)

**Basic Test:**
```powershell
cd C:\xampp\htdocs\DEFENDED
.\scripts\test-rollback.ps1
```

**Custom Test Directory:**
```powershell
.\scripts\test-rollback.ps1 -BackupDir "C:\backups\test" -AppDir "C:\xampp\htdocs\DEFENDED"
```

**Skip Database Testing:**
```powershell
.\scripts\test-rollback.ps1 -SkipDatabase
```

**Verbose Output:**
```powershell
.\scripts\test-rollback.ps1 -Verbose
```

#### Linux (Bash)

**Basic Test:**
```bash
cd /var/www/html
bash scripts/test-rollback.sh
```

**Custom Configuration:**
```bash
BACKUP_DIR=/tmp/backups/test APP_DIR=/var/www/html bash scripts/test-rollback.sh
```

**Skip Database:**
```bash
SKIP_DATABASE=true bash scripts/test-rollback.sh
```

### Interpreting Results

**Success:**
- All tests pass (100% pass rate)
- Green checkmarks (✓) for all tests
- Exit code 0

**Failure:**
- One or more tests fail
- Red X marks (✗) for failed tests
- Exit code 1
- Review failed test messages

**Warnings:**
- Tests pass but with warnings
- Yellow warning icons (⚠️)
- Review warnings but generally OK

---

## Staging Environment Testing

### Setup Staging Environment

1. **Clone Production Environment**
   ```powershell
   # Create staging directory
   New-Item -ItemType Directory -Path "C:\xampp\htdocs\DEFENDED_STAGING"
   
   # Copy application files
   Copy-Item -Path "C:\xampp\htdocs\DEFENDED\*" -Destination "C:\xampp\htdocs\DEFENDED_STAGING" -Recurse
   
   # Copy database
   mysqldump -u root -p firedb > staging_db.sql
   mysql -u root -p firedb_staging < staging_db.sql
   ```

2. **Configure Staging**
   - Update database credentials in staging `.env`
   - Change base URLs to staging domain
   - Disable production integrations (email, SMS, etc.)

3. **Verify Staging Works**
   ```powershell
   # Access staging environment
   start http://localhost/DEFENDED_STAGING
   ```

### Test Scenarios in Staging

#### Scenario 1: Simple Rollback Test

**Objective:** Verify basic rollback functionality

**Steps:**
1. Create a baseline backup
   ```powershell
   .\scripts\create-backup.ps1 -BackupDir "C:\backups\staging" -AppDir "C:\xampp\htdocs\DEFENDED_STAGING"
   ```

2. Make a visible change (e.g., modify homepage)
   ```powershell
   # Edit index.php - add a test banner
   Add-Content -Path "C:\xampp\htdocs\DEFENDED_STAGING\index.php" -Value "<!-- TEST DEPLOYMENT -->"
   ```

3. Create post-change backup
   ```powershell
   .\scripts\create-backup.ps1 -BackupDir "C:\backups\staging" -AppDir "C:\xampp\htdocs\DEFENDED_STAGING"
   ```

4. Perform rollback to baseline
   ```powershell
   # Get first backup timestamp
   $BackupTimestamp = (Get-ChildItem "C:\backups\staging" | Sort-Object LastWriteTime | Select-Object -First 1).Name
   
   # Rollback
   .\scripts\create-rollback.ps1 -BackupDir "C:\backups\staging" -AppDir "C:\xampp\htdocs\DEFENDED_STAGING" -Timestamp $BackupTimestamp
   ```

5. Verify rollback
   ```powershell
   # Check that test comment is gone
   Select-String -Path "C:\xampp\htdocs\DEFENDED_STAGING\index.php" -Pattern "TEST DEPLOYMENT"
   # Should return nothing if rollback successful
   ```

**Expected Result:** ✓ Test banner removed, original version restored

#### Scenario 2: Database Rollback Test

**Objective:** Verify database rollback works correctly

**Steps:**
1. Create baseline with database
   ```powershell
   .\scripts\create-backup.ps1 -BackupDir "C:\backups\staging" -AppDir "C:\xampp\htdocs\DEFENDED_STAGING"
   ```

2. Make database changes
   ```sql
   -- Add a test record
   INSERT INTO users (username, email) VALUES ('test_user', 'test@example.com');
   ```

3. Perform rollback
   ```powershell
   .\scripts\create-rollback.ps1 -BackupDir "C:\backups\staging" -AppDir "C:\xampp\htdocs\DEFENDED_STAGING" -Timestamp $BackupTimestamp
   ```

4. Verify database restored
   ```sql
   SELECT * FROM users WHERE username = 'test_user';
   -- Should return no results
   ```

**Expected Result:** ✓ Test user removed, database restored

#### Scenario 3: Failed Deployment Simulation

**Objective:** Test rollback after a failed deployment

**Steps:**
1. Create pre-deployment backup
2. Simulate broken deployment (corrupt a critical file)
3. Detect failure
4. Perform emergency rollback
5. Verify system restored and functional

**Expected Result:** ✓ System restored to working state

#### Scenario 4: Multiple Rollback Test

**Objective:** Test rollback to various backup points

**Steps:**
1. Create multiple backups over time
2. Make incremental changes between backups
3. Test rollback to each backup point
4. Verify each rollback restores correct state

**Expected Result:** ✓ Can rollback to any backup point

---

## Manual Testing Procedures

### Pre-Rollback Checklist

- [ ] Backup current state
- [ ] Document current version/state
- [ ] Verify backup integrity
- [ ] Notify stakeholders (if production)
- [ ] Put system in maintenance mode (if needed)
- [ ] Stop background jobs/services

### Rollback Execution Checklist

- [ ] Identify target backup timestamp
- [ ] Verify backup exists and is complete
- [ ] Execute rollback script
- [ ] Monitor rollback progress
- [ ] Check for errors during rollback
- [ ] Verify pre-rollback backup created

### Post-Rollback Verification

- [ ] System is accessible
- [ ] Critical files present
- [ ] Database connection works
- [ ] User authentication works
- [ ] Core functionality operational
- [ ] No error messages in logs
- [ ] Performance is acceptable

### Critical Files to Verify

```powershell
# Windows
$CriticalFiles = @(
    "index.php",
    "composer.json",
    "core\config\config.php",
    "core\database\database.php",
    "core\auth\authentication.php",
    "core\security\csrf.php"
)

foreach ($file in $CriticalFiles) {
    $path = "C:\xampp\htdocs\DEFENDED\$file"
    if (Test-Path $path) {
        Write-Host "✓ $file exists" -ForegroundColor Green
    } else {
        Write-Host "✗ $file MISSING" -ForegroundColor Red
    }
}
```

---

## Verification Checklist

### Automated Tests

- [ ] Run `test-rollback.ps1` or `test-rollback.sh`
- [ ] All prerequisites pass
- [ ] Backup creation successful
- [ ] Rollback execution successful
- [ ] File restoration verified
- [ ] Test report generated

### Manual Verification

#### Application Level
- [ ] Homepage loads correctly
- [ ] User can log in
- [ ] Dashboard displays properly
- [ ] Device data accessible
- [ ] Alerts functioning
- [ ] Map/location features work

#### System Level
- [ ] Apache/web server running
- [ ] MySQL database accessible
- [ ] File permissions correct
- [ ] Configuration files intact
- [ ] Logs directory writable
- [ ] Cache directories exist

#### Database Level
- [ ] Database connection successful
- [ ] Tables exist and populated
- [ ] User accounts intact
- [ ] Device records present
- [ ] Historical data preserved
- [ ] No corruption detected

#### Security Level
- [ ] CSRF protection active
- [ ] Session management working
- [ ] Authentication enforced
- [ ] SQL injection protection active
- [ ] XSS protection enabled
- [ ] HTTPS working (if configured)

---

## Troubleshooting

### Common Issues

#### Issue: "Backup not found"

**Symptom:**
```
❌ Backup not found: C:\backups\backup_20251203_120000
```

**Solutions:**
1. Check backup directory exists
   ```powershell
   Test-Path "C:\backups"
   ```

2. List available backups
   ```powershell
   Get-ChildItem "C:\backups" -Directory
   ```

3. Use "latest" timestamp
   ```powershell
   .\scripts\create-rollback.ps1 -Timestamp "latest"
   ```

#### Issue: "Permission denied"

**Symptom:**
```
Copy-Item : Access to the path is denied
```

**Solutions:**
1. Run PowerShell as Administrator
2. Check directory permissions
   ```powershell
   Get-Acl "C:\xampp\htdocs\DEFENDED"
   ```

3. Ensure web server is stopped
   ```powershell
   # Stop Apache in XAMPP Control Panel
   ```

#### Issue: "Database restore failed"

**Symptom:**
```
⚠️  MySQL not found, skipping database restore
```

**Solutions:**
1. Verify MySQL path
   ```powershell
   Test-Path "C:\xampp\mysql\bin\mysql.exe"
   ```

2. Add MySQL to PATH
   ```powershell
   $env:PATH += ";C:\xampp\mysql\bin"
   ```

3. Manually restore database
   ```powershell
   mysql -u root -p firedb < C:\backups\backup_20251203\database.sql
   ```

#### Issue: "Test rollback fails"

**Symptom:**
```
✗ FAIL: Marker file restored
```

**Solutions:**
1. Check backup integrity
2. Verify rollback script permissions
3. Run with verbose output
4. Check application directory is correct
5. Ensure no files are locked

#### Issue: "Pre-rollback backup fails"

**Symptom:**
```
Failed to create pre-rollback backup
```

**Solutions:**
1. Check disk space
   ```powershell
   Get-PSDrive C
   ```

2. Verify backup directory is writable
3. Clear old backups if needed
4. Use different backup location

---

## Best Practices

### Regular Testing Schedule

- **Weekly:** Run automated rollback tests in staging
- **Monthly:** Full manual rollback test with verification
- **Before Major Releases:** Complete rollback testing
- **After Infrastructure Changes:** Verify rollback still works

### Backup Management

1. **Retention Policy**
   - Keep hourly backups for 24 hours
   - Keep daily backups for 7 days
   - Keep weekly backups for 4 weeks
   - Keep monthly backups for 12 months

2. **Storage**
   - Store backups on separate disk/server
   - Encrypt sensitive backups
   - Verify backup integrity regularly
   - Test restore periodically

3. **Documentation**
   - Document each backup's purpose
   - Note version/commit in backup info
   - Record any special restore requirements
   - Maintain rollback runbook

### Production Rollback

**Before Rolling Back Production:**
1. Assess the severity of the issue
2. Try hotfix first if possible
3. Notify all stakeholders
4. Schedule maintenance window
5. Create fresh backup before rollback
6. Have rollback plan documented
7. Prepare communication for users

**During Production Rollback:**
1. Enable maintenance mode
2. Stop background jobs
3. Execute rollback script
4. Monitor progress closely
5. Run verification checks
6. Test critical functionality
7. Review logs for errors

**After Production Rollback:**
1. Verify system fully operational
2. Notify stakeholders of completion
3. Disable maintenance mode
4. Monitor system for issues
5. Document incident and resolution
6. Review what caused the need for rollback
7. Plan fix for original issue

---

## Quick Reference

### Run Automated Test
```powershell
# Windows
.\scripts\test-rollback.ps1

# Linux
bash scripts/test-rollback.sh
```

### Create Backup
```powershell
# Windows
.\scripts\create-backup.ps1

# Linux
bash scripts/create-backup.sh
```

### Perform Rollback
```powershell
# Windows - to latest backup
.\scripts\create-rollback.ps1 -Timestamp "latest"

# Linux - to specific backup
bash scripts/create-rollback.sh backup_20251203_120000
```

### Verify Rollback
```powershell
# Check critical files exist
Test-Path index.php
Test-Path composer.json

# Test application loads
start http://localhost/DEFENDED
```

---

## Support

For issues or questions about rollback testing:
1. Review this guide thoroughly
2. Check the troubleshooting section
3. Review test output and logs
4. Verify prerequisites are met
5. Test in staging before production

---

**Last Updated:** 2025-12-03  
**Version:** 1.0.0  
**Status:** ✅ Ready for Use













