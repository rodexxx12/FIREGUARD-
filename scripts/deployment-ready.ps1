# Deployment-Ready System Setup Script (PowerShell)
# This script automates the deployment checklist fixes

Write-Host "🚀 Starting Deployment-Ready System Setup..." -ForegroundColor Cyan
Write-Host ""

# Step 1: Remove old backup files with hardcoded credentials
Write-Host "📁 Step 1: Removing old backup files with hardcoded credentials..." -ForegroundColor Yellow
$filesToRemove = @(
    "device\smoke_api_old.php",
    "device\smoke_old.php"
)

foreach ($file in $filesToRemove) {
    if (Test-Path $file) {
        Remove-Item $file -Force
        Write-Host "✓ Removed: $file" -ForegroundColor Green
    } else {
        Write-Host "⚠  Not found: $file (already removed or doesn't exist)" -ForegroundColor Yellow
    }
}

# Step 2: Check for remaining hardcoded credentials
Write-Host ""
Write-Host "🔐 Step 2: Checking for remaining hardcoded credentials..." -ForegroundColor Yellow
$found = Select-String -Path "*.php" -Pattern 'password\s*=\s*["''][^"''']{8,}["'']' -Recurse | Where-Object { $_.Path -notmatch '(\.md|example|SECURITY_FIXES)' }
if ($found) {
    foreach ($match in $found) {
        Write-Host "✗ Found hardcoded credential: $($match.Path):$($match.LineNumber)" -ForegroundColor Red
    }
} else {
    Write-Host "✓ No hardcoded credentials found in active files" -ForegroundColor Green
}

# Step 3: Create .env file from example if it doesn't exist
Write-Host ""
Write-Host "⚙️  Step 3: Setting up environment configuration..." -ForegroundColor Yellow
if (-not (Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
        Write-Host "✓ Created .env file from .env.example" -ForegroundColor Green
        Write-Host "⚠  Please edit .env file with your actual credentials!" -ForegroundColor Yellow
    } else {
        Write-Host "✗ .env.example not found. Please create .env manually." -ForegroundColor Red
    }
} else {
    Write-Host "✓ .env file already exists" -ForegroundColor Green
}

# Step 4: Ensure logs directory exists
Write-Host ""
Write-Host "📝 Step 4: Setting up log directories..." -ForegroundColor Yellow
if (-not (Test-Path "logs")) {
    New-Item -ItemType Directory -Path "logs" -Force | Out-Null
}
Write-Host "✓ Log directory created/verified" -ForegroundColor Green

# Step 5: Ensure uploads directory exists
Write-Host ""
Write-Host "📤 Step 5: Setting up upload directories..." -ForegroundColor Yellow
if (-not (Test-Path "uploads")) {
    New-Item -ItemType Directory -Path "uploads" -Force | Out-Null
}
Write-Host "✓ Upload directory created/verified" -ForegroundColor Green

# Step 6: Set file permissions (basic - PowerShell on Windows has limited permission control)
Write-Host ""
Write-Host "🔒 Step 6: File permissions..." -ForegroundColor Yellow
if (Test-Path ".env") {
    # Remove read permissions for everyone except owner (Windows)
    icacls ".env" /inheritance:r /grant "${env:USERNAME}:F" | Out-Null
    Write-Host "✓ Set .env permissions (owner only)" -ForegroundColor Green
}

# Step 7: Verify core modules exist
Write-Host ""
Write-Host "📦 Step 7: Verifying core modules..." -ForegroundColor Yellow
$coreModules = @(
    "core\bootstrap.php",
    "core\config\config.php",
    "core\database\database.php",
    "core\security\security.php",
    "core\session\session.php"
)

foreach ($module in $coreModules) {
    if (Test-Path $module) {
        Write-Host "✓ Found: $module" -ForegroundColor Green
    } else {
        Write-Host "✗ Missing: $module" -ForegroundColor Red
    }
}

# Step 8: Check for debug code
Write-Host ""
Write-Host "🐛 Step 8: Checking for debug code..." -ForegroundColor Yellow
$debugPatterns = @("console\.log", "var_dump", "print_r")
Write-Host "Scanning for debug patterns..."
foreach ($pattern in $debugPatterns) {
    $found = Select-String -Path "*.php","*.js" -Pattern $pattern -Recurse | Measure-Object
    if ($found.Count -gt 0) {
        Write-Host "⚠  Found $($found.Count) instances of: $pattern" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "✨ Deployment-Ready Setup Complete!" -ForegroundColor Cyan
Write-Host ""
Write-Host "📋 Next Steps:"
Write-Host "  1. Edit .env file with your actual credentials"
Write-Host "  2. Test database connection: php scripts\test_connection.php"
Write-Host "  3. Review DEPLOYMENT_CHECKLIST.md for remaining items"
Write-Host "  4. Run security tests"
Write-Host ""

