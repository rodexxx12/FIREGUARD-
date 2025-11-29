# Backup Script for Windows
# Creates a complete backup of the application and database

param(
    [string]$BackupDir = "C:\backups",
    [string]$AppDir = "C:\xampp\htdocs\DEFENDED"
)

$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$BackupName = "backup_$Timestamp"
$BackupPath = Join-Path $BackupDir $BackupName

Write-Host "📦 Creating backup: $BackupName" -ForegroundColor Cyan

# Create backup directory
New-Item -ItemType Directory -Path $BackupPath -Force | Out-Null

# Backup files
Write-Host "💾 Backing up application files..." -ForegroundColor Yellow
$FilesZip = Join-Path $BackupPath "files.zip"

$Exclude = @('vendor', 'vendors', 'node_modules', '.git', '*.log')
Compress-Archive -Path "$AppDir\*" -DestinationPath $FilesZip -CompressionLevel Optimal

# Backup database
Write-Host "🗄️  Backing up database..." -ForegroundColor Yellow

# Load environment variables from .env
$EnvFile = Join-Path $AppDir ".env"
if (Test-Path $EnvFile) {
    $EnvVars = @{}
    Get-Content $EnvFile | ForEach-Object {
        if ($_ -match '^([^#][^=]*)=(.*)$') {
            $EnvVars[$matches[1].Trim()] = $matches[2].Trim()
        }
    }
    
    $DbName = $EnvVars['DB_NAME'] ?? 'firedb'
    $DbUser = $EnvVars['DB_USER'] ?? 'root'
    $DbPass = $EnvVars['DB_PASS'] ?? ''
    
    $DbBackup = Join-Path $BackupPath "database.sql"
    $MySqlPath = "C:\xampp\mysql\bin\mysqldump.exe"
    
    if (Test-Path $MySqlPath) {
        & $MySqlPath -u $DbUser -p$DbPass $DbName > $DbBackup
        
        # Compress database backup
        Compress-Archive -Path $DbBackup -DestinationPath "$DbBackup.zip"
        Remove-Item $DbBackup
    } else {
        Write-Host "⚠️  MySQL not found, skipping database backup" -ForegroundColor Yellow
    }
} else {
    Write-Host "⚠️  .env file not found, skipping database backup" -ForegroundColor Yellow
}

# Create backup info file
$BackupInfo = @"
Backup created: $(Get-Date)
Application directory: $AppDir
Database: $($DbName ?? 'not backed up')
Size: $((Get-ChildItem -Path $BackupPath -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB) MB
"@

$BackupInfo | Out-File -FilePath (Join-Path $BackupPath "backup_info.txt") -Encoding UTF8

Write-Host "✅ Backup complete: $BackupPath" -ForegroundColor Green
$Size = (Get-ChildItem -Path $BackupPath -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB
Write-Host "📊 Backup size: $([math]::Round($Size, 2)) MB" -ForegroundColor Cyan






