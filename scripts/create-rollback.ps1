# Rollback Script for Windows
# This script helps rollback to a previous version

param(
    [string]$BackupDir = "C:\backups",
    [string]$AppDir = "C:\xampp\htdocs\DEFENDED",
    [string]$Timestamp = "latest"
)

Write-Host "🔄 Starting rollback process..." -ForegroundColor Cyan

# Find backup
if ($Timestamp -eq "latest") {
    $LatestBackup = Get-ChildItem -Path $BackupDir -Directory | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $LatestBackup) {
        Write-Host "❌ No backups found in $BackupDir" -ForegroundColor Red
        exit 1
    }
    $BackupPath = $LatestBackup.FullName
} else {
    $BackupPath = Join-Path $BackupDir $Timestamp
    if (-not (Test-Path $BackupPath)) {
        Write-Host "❌ Backup not found: $BackupPath" -ForegroundColor Red
        exit 1
    }
}

Write-Host "📦 Using backup: $BackupPath" -ForegroundColor Yellow

# Backup current version before rollback
Write-Host "💾 Creating backup of current version..." -ForegroundColor Cyan
$CurrentBackup = Join-Path $BackupDir "pre_rollback_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
New-Item -ItemType Directory -Path $CurrentBackup -Force | Out-Null

# Backup files
Copy-Item -Path "$AppDir\*" -Destination "$CurrentBackup\files" -Recurse -Force
Write-Host "✅ Current version backed up to: $CurrentBackup" -ForegroundColor Green

# Restore from backup
Write-Host "📥 Restoring from backup..." -ForegroundColor Cyan
$SourceFiles = Join-Path $BackupPath "files"
if (Test-Path $SourceFiles) {
    Copy-Item -Path "$SourceFiles\*" -Destination $AppDir -Recurse -Force
} else {
    Copy-Item -Path "$BackupPath\*" -Destination $AppDir -Recurse -Force -Exclude "database.sql"
}

# Restore database if backup exists
$DbBackup = Join-Path $BackupPath "database.sql"
if (Test-Path $DbBackup) {
    Write-Host "🗄️  Restoring database..." -ForegroundColor Cyan
    $env:DB_USER = $env:DB_USER ?? "root"
    $env:DB_PASS = $env:DB_PASS ?? ""
    $env:DB_NAME = $env:DB_NAME ?? "firedb"
    
    $mysqlPath = "C:\xampp\mysql\bin\mysql.exe"
    if (Test-Path $mysqlPath) {
        & $mysqlPath -u $env:DB_USER -p$env:DB_PASS $env:DB_NAME < $DbBackup
    } else {
        Write-Host "⚠️  MySQL not found, skipping database restore" -ForegroundColor Yellow
    }
}

Write-Host "✅ Rollback complete!" -ForegroundColor Green
Write-Host "📝 Current version backed up to: $CurrentBackup" -ForegroundColor Cyan






