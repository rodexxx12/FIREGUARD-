# Rollback Testing Script for Windows
# This script tests the rollback procedures in a safe manner
# It creates test backups, performs rollback, and verifies the results

param(
    [string]$BackupDir = "C:\backups\test",
    [string]$AppDir = "C:\xampp\htdocs\DEFENDED",
    [switch]$SkipDatabase = $false,
    [switch]$Verbose = $false
)

# Test configuration
$TestMarkerFile = "rollback_test_marker.txt"
$TestMarkerContent = "ROLLBACK_TEST_$(Get-Date -Format 'yyyyMMdd_HHmmss')"

# ANSI colors for output
$ColorRed = "`e[31m"
$ColorGreen = "`e[32m"
$ColorYellow = "`e[33m"
$ColorBlue = "`e[34m"
$ColorCyan = "`e[36m"
$ColorReset = "`e[0m"

# Test results
$TestResults = @{
    Total = 0
    Passed = 0
    Failed = 0
    Tests = @()
}

function Write-TestHeader {
    Write-Host "`n========================================" -ForegroundColor Cyan
    Write-Host "   ROLLBACK TESTING FRAMEWORK" -ForegroundColor Cyan
    Write-Host "========================================`n" -ForegroundColor Cyan
    Write-Host "Test Directory: $BackupDir" -ForegroundColor Yellow
    Write-Host "App Directory:  $AppDir" -ForegroundColor Yellow
    Write-Host "Skip Database:  $SkipDatabase" -ForegroundColor Yellow
    Write-Host ""
}

function Add-TestResult {
    param(
        [string]$TestName,
        [bool]$Passed,
        [string]$Message = ""
    )
    
    $TestResults.Total++
    if ($Passed) {
        $TestResults.Passed++
        Write-Host "✓ PASS: $TestName" -ForegroundColor Green
    } else {
        $TestResults.Failed++
        Write-Host "✗ FAIL: $TestName" -ForegroundColor Red
    }
    
    if ($Message) {
        Write-Host "  └─ $Message" -ForegroundColor Gray
    }
    
    $TestResults.Tests += @{
        Name = $TestName
        Passed = $Passed
        Message = $Message
        Timestamp = Get-Date
    }
}

function Test-Prerequisites {
    Write-Host "`n[1/7] Testing Prerequisites..." -ForegroundColor Cyan
    
    # Test backup script exists
    $backupScriptPath = Join-Path (Split-Path $PSScriptRoot -Parent) "scripts\create-backup.ps1"
    $backupExists = Test-Path $backupScriptPath
    Add-TestResult "Backup script exists" $backupExists $backupScriptPath
    
    # Test rollback script exists
    $rollbackScriptPath = Join-Path (Split-Path $PSScriptRoot -Parent) "scripts\create-rollback.ps1"
    $rollbackExists = Test-Path $rollbackScriptPath
    Add-TestResult "Rollback script exists" $rollbackExists $rollbackScriptPath
    
    # Test app directory exists
    $appExists = Test-Path $AppDir
    Add-TestResult "Application directory exists" $appExists $AppDir
    
    # Test write permissions
    try {
        $testFile = Join-Path $AppDir "test_write_permission.tmp"
        "test" | Out-File $testFile -ErrorAction Stop
        Remove-Item $testFile -ErrorAction SilentlyContinue
        Add-TestResult "Write permissions OK" $true
    } catch {
        Add-TestResult "Write permissions OK" $false $_.Exception.Message
    }
    
    return ($backupExists -and $rollbackExists -and $appExists)
}

function Test-BackupCreation {
    Write-Host "`n[2/7] Testing Backup Creation..." -ForegroundColor Cyan
    
    # Create test backup directory
    try {
        New-Item -ItemType Directory -Path $BackupDir -Force -ErrorAction Stop | Out-Null
        Add-TestResult "Backup directory created" $true $BackupDir
    } catch {
        Add-TestResult "Backup directory created" $false $_.Exception.Message
        return $false
    }
    
    # Create test marker file
    try {
        $markerPath = Join-Path $AppDir $TestMarkerFile
        $TestMarkerContent | Out-File $markerPath -Encoding UTF8
        Add-TestResult "Test marker file created" $true $markerPath
    } catch {
        Add-TestResult "Test marker file created" $false $_.Exception.Message
        return $false
    }
    
    # Run backup script
    try {
        $backupScript = Join-Path $PSScriptRoot "create-backup.ps1"
        $backupResult = & $backupScript -BackupDir $BackupDir -AppDir $AppDir 2>&1
        
        if ($LASTEXITCODE -eq 0 -or $backupResult -match "Backup complete") {
            Add-TestResult "Backup script executed" $true
        } else {
            Add-TestResult "Backup script executed" $false "Exit code: $LASTEXITCODE"
            return $false
        }
    } catch {
        Add-TestResult "Backup script executed" $false $_.Exception.Message
        return $false
    }
    
    # Verify backup was created
    $backups = Get-ChildItem -Path $BackupDir -Directory | Where-Object { $_.Name -match "backup_\d+" }
    if ($backups.Count -gt 0) {
        $latestBackup = $backups | Sort-Object LastWriteTime -Descending | Select-Object -First 1
        $script:TestBackupPath = $latestBackup.FullName
        Add-TestResult "Backup directory created" $true $script:TestBackupPath
    } else {
        Add-TestResult "Backup directory created" $false "No backup found in $BackupDir"
        return $false
    }
    
    # Verify backup contains files
    $backupFiles = Get-ChildItem -Path $script:TestBackupPath -Recurse -File
    $hasFiles = $backupFiles.Count -gt 0
    Add-TestResult "Backup contains files" $hasFiles "Files: $($backupFiles.Count)"
    
    return $hasFiles
}

function Test-MarkerModification {
    Write-Host "`n[3/7] Testing Marker Modification..." -ForegroundColor Cyan
    
    # Modify the marker file to simulate changes
    try {
        $markerPath = Join-Path $AppDir $TestMarkerFile
        $modifiedContent = "MODIFIED_VERSION_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
        $modifiedContent | Out-File $markerPath -Encoding UTF8
        Add-TestResult "Marker file modified" $true
        
        # Verify modification
        $currentContent = Get-Content $markerPath -Raw
        $isModified = $currentContent -ne $TestMarkerContent
        Add-TestResult "Marker content changed" $isModified
        
        $script:ModifiedMarkerContent = $modifiedContent
        return $isModified
    } catch {
        Add-TestResult "Marker file modified" $false $_.Exception.Message
        return $false
    }
}

function Test-RollbackExecution {
    Write-Host "`n[4/7] Testing Rollback Execution..." -ForegroundColor Cyan
    
    if (-not $script:TestBackupPath) {
        Add-TestResult "Rollback execution" $false "No backup path available"
        return $false
    }
    
    # Get the backup timestamp from the path
    $backupTimestamp = (Split-Path $script:TestBackupPath -Leaf)
    
    try {
        $rollbackScript = Join-Path $PSScriptRoot "create-rollback.ps1"
        $rollbackResult = & $rollbackScript -BackupDir $BackupDir -AppDir $AppDir -Timestamp $backupTimestamp 2>&1
        
        if ($LASTEXITCODE -eq 0 -or $rollbackResult -match "Rollback complete") {
            Add-TestResult "Rollback script executed" $true
        } else {
            Add-TestResult "Rollback script executed" $false "Exit code: $LASTEXITCODE"
            return $false
        }
    } catch {
        Add-TestResult "Rollback script executed" $false $_.Exception.Message
        return $false
    }
    
    # Verify pre-rollback backup was created
    $preRollbackBackups = Get-ChildItem -Path $BackupDir -Directory | Where-Object { $_.Name -match "pre_rollback_\d+" }
    $hasPreRollbackBackup = $preRollbackBackups.Count -gt 0
    Add-TestResult "Pre-rollback backup created" $hasPreRollbackBackup
    
    return $true
}

function Test-RollbackVerification {
    Write-Host "`n[5/7] Testing Rollback Verification..." -ForegroundColor Cyan
    
    # Verify marker file was restored to original content
    try {
        $markerPath = Join-Path $AppDir $TestMarkerFile
        
        if (Test-Path $markerPath) {
            $restoredContent = Get-Content $markerPath -Raw
            $isRestored = $restoredContent.Trim() -eq $TestMarkerContent.Trim()
            Add-TestResult "Marker file restored" $isRestored "Content: $($restoredContent.Trim().Substring(0, [Math]::Min(50, $restoredContent.Length)))"
        } else {
            Add-TestResult "Marker file restored" $false "Marker file not found"
            return $false
        }
    } catch {
        Add-TestResult "Marker file verification" $false $_.Exception.Message
        return $false
    }
    
    # Verify file count is reasonable
    try {
        $currentFileCount = (Get-ChildItem -Path $AppDir -Recurse -File | Measure-Object).Count
        $hasFiles = $currentFileCount -gt 0
        Add-TestResult "Application files exist" $hasFiles "Files: $currentFileCount"
    } catch {
        Add-TestResult "Application files exist" $false $_.Exception.Message
        return $false
    }
    
    # Verify critical files exist
    $criticalFiles = @(
        "index.php",
        "composer.json",
        "core\config\config.php"
    )
    
    foreach ($file in $criticalFiles) {
        $filePath = Join-Path $AppDir $file
        $exists = Test-Path $filePath
        Add-TestResult "Critical file exists: $file" $exists $filePath
    }
    
    return $true
}

function Test-BackupIntegrity {
    Write-Host "`n[6/7] Testing Backup Integrity..." -ForegroundColor Cyan
    
    if (-not $script:TestBackupPath) {
        Add-TestResult "Backup integrity check" $false "No backup path available"
        return $false
    }
    
    # Check backup info file
    $backupInfoPath = Join-Path $script:TestBackupPath "backup_info.txt"
    $hasInfo = Test-Path $backupInfoPath
    Add-TestResult "Backup info file exists" $hasInfo $backupInfoPath
    
    # Check backup structure
    $expectedFiles = @("files.zip")
    foreach ($file in $expectedFiles) {
        $filePath = Join-Path $script:TestBackupPath $file
        $exists = Test-Path $filePath
        Add-TestResult "Backup file exists: $file" $exists
    }
    
    # Verify backup size
    try {
        $backupSize = (Get-ChildItem -Path $script:TestBackupPath -Recurse -File | Measure-Object -Property Length -Sum).Sum
        $hasSize = $backupSize -gt 0
        Add-TestResult "Backup has content" $hasSize "Size: $([math]::Round($backupSize / 1MB, 2)) MB"
    } catch {
        Add-TestResult "Backup size verification" $false $_.Exception.Message
    }
    
    return $true
}

function Test-Cleanup {
    Write-Host "`n[7/7] Testing Cleanup..." -ForegroundColor Cyan
    
    # Remove test marker file
    try {
        $markerPath = Join-Path $AppDir $TestMarkerFile
        if (Test-Path $markerPath) {
            Remove-Item $markerPath -Force
            Add-TestResult "Test marker file removed" $true
        } else {
            Add-TestResult "Test marker file removed" $true "Already removed"
        }
    } catch {
        Add-TestResult "Test marker file removed" $false $_.Exception.Message
    }
    
    # Optionally remove test backups
    Write-Host "`n⚠️  Test backups are preserved in: $BackupDir" -ForegroundColor Yellow
    Write-Host "   You can safely delete this directory after reviewing the results." -ForegroundColor Gray
    
    return $true
}

function Show-TestResults {
    Write-Host "`n========================================" -ForegroundColor Cyan
    Write-Host "         TEST RESULTS SUMMARY" -ForegroundColor Cyan
    Write-Host "========================================`n" -ForegroundColor Cyan
    
    $passRate = if ($TestResults.Total -gt 0) { 
        [math]::Round(($TestResults.Passed / $TestResults.Total) * 100, 2) 
    } else { 
        0 
    }
    
    Write-Host "Total Tests:  $($TestResults.Total)" -ForegroundColor White
    Write-Host "Passed:       $($TestResults.Passed)" -ForegroundColor Green
    Write-Host "Failed:       $($TestResults.Failed)" -ForegroundColor $(if ($TestResults.Failed -eq 0) { "Green" } else { "Red" })
    Write-Host "Pass Rate:    $passRate%" -ForegroundColor $(if ($passRate -ge 90) { "Green" } elseif ($passRate -ge 70) { "Yellow" } else { "Red" })
    
    Write-Host "`n========================================`n" -ForegroundColor Cyan
    
    if ($TestResults.Failed -eq 0) {
        Write-Host "✓ ALL TESTS PASSED!" -ForegroundColor Green
        Write-Host "  Rollback procedures are working correctly." -ForegroundColor Green
    } else {
        Write-Host "✗ SOME TESTS FAILED" -ForegroundColor Red
        Write-Host "  Review the failed tests above and fix issues." -ForegroundColor Red
        Write-Host "`nFailed Tests:" -ForegroundColor Yellow
        foreach ($test in $TestResults.Tests | Where-Object { -not $_.Passed }) {
            Write-Host "  - $($test.Name): $($test.Message)" -ForegroundColor Red
        }
    }
    
    Write-Host ""
    
    # Create test report
    $reportPath = Join-Path $BackupDir "rollback_test_report.txt"
    $report = @"
ROLLBACK TEST REPORT
Generated: $(Get-Date)
========================================

Test Configuration:
- Backup Directory: $BackupDir
- Application Directory: $AppDir
- Skip Database: $SkipDatabase

Test Results:
- Total Tests: $($TestResults.Total)
- Passed: $($TestResults.Passed)
- Failed: $($TestResults.Failed)
- Pass Rate: $passRate%

Detailed Results:
"@
    
    foreach ($test in $TestResults.Tests) {
        $status = if ($test.Passed) { "PASS" } else { "FAIL" }
        $report += "`n[$status] $($test.Name)"
        if ($test.Message) {
            $report += "`n        $($test.Message)"
        }
    }
    
    try {
        $report | Out-File $reportPath -Encoding UTF8
        Write-Host "Test report saved: $reportPath" -ForegroundColor Cyan
    } catch {
        Write-Host "Warning: Could not save test report: $($_.Exception.Message)" -ForegroundColor Yellow
    }
    
    return ($TestResults.Failed -eq 0)
}

# Main execution
Write-TestHeader

$allTestsPassed = $true

# Run test suite
$allTestsPassed = $allTestsPassed -and (Test-Prerequisites)
$allTestsPassed = $allTestsPassed -and (Test-BackupCreation)
$allTestsPassed = $allTestsPassed -and (Test-MarkerModification)
$allTestsPassed = $allTestsPassed -and (Test-RollbackExecution)
$allTestsPassed = $allTestsPassed -and (Test-RollbackVerification)
$allTestsPassed = $allTestsPassed -and (Test-BackupIntegrity)
Test-Cleanup | Out-Null

# Show results
$success = Show-TestResults

# Exit with appropriate code
exit $(if ($success) { 0 } else { 1 })













