# Rollback Procedures Runbook

## Emergency Rollback Procedures

**Use this runbook when you need to rollback the system to a previous version.**

---

## When to Rollback

### Severity Levels

**CRITICAL - Immediate Rollback:**
- System completely down
- Data corruption detected
- Security breach confirmed
- Critical functionality broken

**HIGH - Plan Rollback Within 1 Hour:**
- Major features not working
- Performance severely degraded
- Multiple user-facing bugs
- Database issues affecting operations

**MEDIUM - Plan Rollback Within 4 Hours:**
- Minor features broken
- Non-critical bugs
- Performance slightly degraded
- Can be addressed with hotfix

**LOW - Consider Hotfix Instead:**
- UI issues
- Minor bugs
- Can wait for next release

---

## Pre-Rollback Checklist

### 1. Assess the Situation (5 minutes)

- [ ] Identify the issue and severity
- [ ] Document error messages/symptoms
- [ ] Check if hotfix is possible and faster
- [ ] Determine which backup to restore to
- [ ] Notify stakeholders

### 2. Prepare for Rollback (10 minutes)

- [ ] **CRITICAL:** Backup current state first
  ```powershell
  .\scripts\create-backup.ps1 -BackupDir "C:\backups\emergency"
  ```

- [ ] Identify target backup
  ```powershell
  Get-ChildItem C:\backups -Directory | Sort-Object LastWriteTime -Descending
  ```

- [ ] Verify backup integrity
  ```powershell
  Get-ChildItem "C:\backups\backup_YYYYMMDD_HHMMSS" -Recurse
  ```

- [ ] Put system in maintenance mode (if possible)
  ```powershell
  # Create maintenance flag
  "MAINTENANCE" > C:\xampp\htdocs\DEFENDED\.maintenance
  ```

- [ ] Stop background jobs/services
- [ ] Clear user sessions (optional)

### 3. Communication (5 minutes)

- [ ] Notify IT team
- [ ] Notify management
- [ ] Prepare user communication
- [ ] Document start time

---

## Rollback Execution

### Step 1: Final Backup (CRITICAL)

```powershell
# Windows
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
.\scripts\create-backup.ps1 -BackupDir "C:\backups" -AppDir "C:\xampp\htdocs\DEFENDED"

# Linux
timestamp=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=/backups APP_DIR=/var/www/html bash scripts/create-backup.sh
```

**WAIT for backup to complete before proceeding!**

### Step 2: Identify Target Backup

```powershell
# Windows - List available backups
Get-ChildItem C:\backups -Directory | 
    Where-Object { $_.Name -match "backup_\d+" } | 
    Sort-Object LastWriteTime -Descending | 
    Select-Object Name, LastWriteTime

# Select the backup BEFORE the problematic deployment
$targetBackup = "backup_20251203_120000"  # Replace with actual timestamp
```

```bash
# Linux - List available backups
ls -lt /backups | grep "backup_"

# Select the backup
target_backup="backup_20251203_120000"  # Replace with actual timestamp
```

### Step 3: Execute Rollback

```powershell
# Windows
.\scripts\create-rollback.ps1 `
    -BackupDir "C:\backups" `
    -AppDir "C:\xampp\htdocs\DEFENDED" `
    -Timestamp $targetBackup

# Linux
BACKUP_DIR=/backups APP_DIR=/var/www/html bash scripts/create-rollback.sh $target_backup
```

**Expected Output:**
```
🔄 Starting rollback process...
📦 Using backup: C:\backups\backup_20251203_120000
💾 Creating backup of current version...
✅ Current version backed up to: C:\backups\pre_rollback_20251203_143000
📥 Restoring from backup...
🗄️  Restoring database...
✅ Rollback complete!
```

### Step 4: Immediate Verification (5 minutes)

**Critical Checks:**

1. **Application Loads:**
   ```powershell
   # Test homepage
   Invoke-WebRequest http://localhost/DEFENDED
   ```

2. **Database Connection:**
   ```powershell
   # Try to connect
   mysql -u root -p firedb -e "SELECT COUNT(*) FROM users;"
   ```

3. **Critical Files Present:**
   ```powershell
   $criticalFiles = @("index.php", "composer.json", "core/config/config.php")
   foreach ($file in $criticalFiles) {
       if (Test-Path "C:\xampp\htdocs\DEFENDED\$file") {
           Write-Host "✓ $file" -ForegroundColor Green
       } else {
           Write-Host "✗ $file MISSING!" -ForegroundColor Red
       }
   }
   ```

4. **No Fatal Errors:**
   ```powershell
   # Check error logs
   Get-Content C:\xampp\htdocs\DEFENDED\logs\php_errors.log -Tail 50
   ```

### Step 5: Restart Services

```powershell
# Restart Apache in XAMPP Control Panel
# Or via command line:
net stop Apache2.4
net start Apache2.4

# Clear cache
Remove-Item C:\xampp\htdocs\DEFENDED\cache\* -Recurse -Force
```

---

## Post-Rollback Verification

### Comprehensive Verification (30 minutes)

#### Level 1: System Access (5 min)
- [ ] Homepage loads without errors
- [ ] SSL certificate valid (if HTTPS)
- [ ] No maintenance mode active
- [ ] Correct version displayed

#### Level 2: Authentication (5 min)
- [ ] Login page accessible
- [ ] Test user can log in
- [ ] Session management working
- [ ] Logout works
- [ ] Password reset functional

#### Level 3: Core Functionality (10 min)
- [ ] Dashboard displays correctly
- [ ] Device list loads
- [ ] Device data updating
- [ ] Alerts generating
- [ ] Map/location features work
- [ ] Historical data accessible

#### Level 4: Database (5 min)
- [ ] All tables present
- [ ] User accounts intact
- [ ] Device records correct
- [ ] Recent data preserved
- [ ] No corruption detected

#### Level 5: Integrations (5 min)
- [ ] Email notifications work (if applicable)
- [ ] SMS alerts functional (if applicable)
- [ ] API endpoints responding
- [ ] Third-party integrations OK

### Verification Commands

```powershell
# Windows - Quick verification script
$checks = @{
    "Homepage" = { Test-NetConnection -ComputerName localhost -Port 80 }
    "Index File" = { Test-Path "C:\xampp\htdocs\DEFENDED\index.php" }
    "Config File" = { Test-Path "C:\xampp\htdocs\DEFENDED\core\config\config.php" }
    "Database File" = { Test-Path "C:\xampp\htdocs\DEFENDED\core\database\database.php" }
}

foreach ($check in $checks.GetEnumerator()) {
    $result = & $check.Value
    if ($result) {
        Write-Host "✓ $($check.Key)" -ForegroundColor Green
    } else {
        Write-Host "✗ $($check.Key)" -ForegroundColor Red
    }
}
```

---

## Post-Rollback Actions

### Immediate (Within 1 Hour)

1. **Notify Stakeholders**
   - Rollback completed
   - System status
   - Next steps

2. **Remove Maintenance Mode**
   ```powershell
   Remove-Item C:\xampp\htdocs\DEFENDED\.maintenance -Force
   ```

3. **Monitor System**
   - Watch error logs
   - Check user activity
   - Monitor performance

4. **Document Incident**
   - What caused the rollback
   - Backup used
   - Issues encountered
   - Resolution steps

### Short Term (Within 24 Hours)

1. **Root Cause Analysis**
   - Identify what went wrong
   - Review deployment process
   - Check testing procedures

2. **Plan Fix**
   - Create action plan
   - Fix underlying issue
   - Plan for re-deployment

3. **Update Procedures**
   - Document lessons learned
   - Update runbooks
   - Improve testing

### Long Term (Within 1 Week)

1. **Implement Fix**
   - Develop proper solution
   - Test thoroughly in staging
   - Plan new deployment

2. **Process Improvement**
   - Review deployment process
   - Enhance testing procedures
   - Update monitoring

3. **Team Review**
   - Discuss incident
   - Share learnings
   - Update documentation

---

## Rollback Decision Matrix

| Issue | Severity | Action | Timeframe |
|-------|----------|--------|-----------|
| Complete system down | CRITICAL | Immediate rollback | 0-15 min |
| Data corruption | CRITICAL | Immediate rollback | 0-15 min |
| Security breach | CRITICAL | Immediate rollback | 0-15 min |
| Major feature broken | HIGH | Rollback within 1 hour | 15-60 min |
| Performance degraded >50% | HIGH | Rollback within 1 hour | 15-60 min |
| Minor features broken | MEDIUM | Consider hotfix or rollback | 1-4 hours |
| UI issues only | LOW | Hotfix instead | 4+ hours |

---

## Emergency Contacts

### On-Call Rotation
- **Primary:** [Name] - [Phone] - [Email]
- **Secondary:** [Name] - [Phone] - [Email]
- **Manager:** [Name] - [Phone] - [Email]

### Escalation Path
1. System Administrator
2. Development Lead
3. IT Manager
4. CTO/CIO

---

## Common Issues During Rollback

### Issue: Rollback Script Fails

**Error:** "Copy-Item: Access denied"

**Solution:**
```powershell
# Stop Apache
net stop Apache2.4

# Try rollback again
.\scripts\create-rollback.ps1 -Timestamp $targetBackup

# Start Apache
net start Apache2.4
```

### Issue: Database Won't Restore

**Error:** "MySQL not found"

**Solution:**
```powershell
# Manual database restore
$mysqlPath = "C:\xampp\mysql\bin\mysql.exe"
$backupPath = "C:\backups\backup_20251203_120000\database.sql"
& $mysqlPath -u root -p firedb < $backupPath
```

### Issue: Pre-Rollback Backup Fails

**Error:** "Insufficient disk space"

**Solution:**
```powershell
# Skip pre-rollback backup (ONLY in emergency)
# Edit create-rollback.ps1 temporarily
# Or free up disk space first
Get-ChildItem C:\backups | Sort-Object LastWriteTime | Select-Object -First 5 | Remove-Item -Recurse
```

### Issue: Wrong Backup Selected

**Error:** System still broken after rollback

**Solution:**
```powershell
# Rollback again to earlier backup
$earlierBackup = "backup_20251203_110000"  # Even earlier timestamp
.\scripts\create-rollback.ps1 -Timestamp $earlierBackup
```

---

## Rollback Testing Schedule

- **Weekly:** Automated rollback tests in staging
- **Monthly:** Full manual rollback test
- **Quarterly:** Disaster recovery drill
- **Before Major Releases:** Complete rollback verification

---

## Quick Reference Card

### Critical Commands

**Backup Now:**
```powershell
.\scripts\create-backup.ps1
```

**List Backups:**
```powershell
Get-ChildItem C:\backups -Directory | Sort-Object LastWriteTime -Descending
```

**Rollback to Latest:**
```powershell
.\scripts\create-rollback.ps1 -Timestamp "latest"
```

**Rollback to Specific:**
```powershell
.\scripts\create-rollback.ps1 -Timestamp "backup_20251203_120000"
```

**Test Rollback:**
```powershell
.\scripts\test-rollback.ps1
```

### Verification Commands

**Test Homepage:**
```powershell
Invoke-WebRequest http://localhost/DEFENDED
```

**Check Database:**
```powershell
mysql -u root -p firedb -e "SHOW TABLES;"
```

**View Errors:**
```powershell
Get-Content logs\php_errors.log -Tail 50
```

---

**Status:** ✅ Ready for Production Use  
**Last Updated:** 2025-12-03  
**Version:** 1.0.0  
**Review:** Quarterly or after major incidents













