# Merge all config.env files into centralized root-level .env
# This script reads all config.env files and merges them into .env

Write-Host "🔄 Merging config.env files into centralized .env..." -ForegroundColor Cyan
Write-Host ""

$rootEnv = ".env"
$configFiles = @(
    "production\db\config.env",
    "login\functions\config.env",
    "reg\config.env"
)

# Check if root .env exists
if (-not (Test-Path $rootEnv)) {
    Write-Host "❌ Root .env file not found. Please create it first." -ForegroundColor Red
    exit 1
}

Write-Host "📋 Reading existing config.env files..." -ForegroundColor Yellow

$mergedConfig = @{}
$allKeys = New-Object System.Collections.ArrayList

# Read root .env first
if (Test-Path $rootEnv) {
    Write-Host "  ✓ Reading root .env..." -ForegroundColor Green
    $rootContent = Get-Content $rootEnv
    foreach ($line in $rootContent) {
        $line = $line.Trim()
        if ($line -and -not $line.StartsWith("#") -and $line.Contains("=")) {
            $parts = $line.Split("=", 2)
            $key = $parts[0].Trim()
            $value = $parts[1].Trim()
            $mergedConfig[$key] = $value
            if (-not $allKeys.Contains($key)) {
                $allKeys.Add($key) | Out-Null
            }
        }
    }
}

# Read all config.env files
foreach ($configFile in $configFiles) {
    if (Test-Path $configFile) {
        Write-Host "  ✓ Reading $configFile..." -ForegroundColor Green
        $content = Get-Content $configFile
        foreach ($line in $content) {
            $line = $line.Trim()
            if ($line -and -not $line.StartsWith("#") -and $line.Contains("=")) {
                $parts = $line.Split("=", 2)
                $key = $parts[0].Trim()
                $value = $parts[1].Trim()
                
                # Remove quotes if present
                if ($value.StartsWith('"') -and $value.EndsWith('"')) {
                    $value = $value.Substring(1, $value.Length - 2)
                }
                if ($value.StartsWith("'") -and $value.EndsWith("'")) {
                    $value = $value.Substring(1, $value.Length - 2)
                }
                
                # Merge: Keep existing value unless it's a placeholder
                if (-not $mergedConfig.ContainsKey($key) -or 
                    $mergedConfig[$key] -eq "" -or 
                    $mergedConfig[$key] -match "your_|example\.|https://your-") {
                    $mergedConfig[$key] = $value
                    Write-Host "    → Added/Updated: $key" -ForegroundColor Gray
                }
                
                if (-not $allKeys.Contains($key)) {
                    $allKeys.Add($key) | Out-Null
                }
            }
        }
    } else {
        Write-Host "  ⚠ Skipping (not found): $configFile" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "📝 Merged configuration contains $($mergedConfig.Count) variables" -ForegroundColor Cyan
Write-Host ""
Write-Host "⚠️  Note: Since .env is protected, please manually update your .env file with:" -ForegroundColor Yellow
Write-Host ""
Write-Host "Variables to add/update:" -ForegroundColor Cyan

# Group by category
$categories = @{
    "Application" = @("APP_NAME", "APP_ENV", "APP_DEBUG", "APP_URL")
    "Database" = @("DB_HOST", "DB_NAME", "DB_USER", "DB_PASS")
    "Session" = @("SESSION_LIFETIME", "SESSION_SECURE", "SESSION_HTTPONLY", "SESSION_SAMESITE")
    "Cookie" = @("COOKIE_SECURE", "COOKIE_DOMAIN", "SESSION_COOKIE_SECURE")
    "Security" = @("CSRF_TOKEN_TTL", "CSRF_TOKEN_NAME", "RATE_LIMIT_ENABLED", "MAX_LOGIN_ATTEMPTS", "LOGIN_LOCKOUT_TIME")
    "SMTP" = @("SMTP_HOST", "SMTP_PORT", "SMTP_USER", "SMTP_USERNAME", "SMTP_PASS", "SMTP_PASSWORD", "SMTP_FROM_ADDRESS", "SMTP_FROM_EMAIL", "SMTP_FROM_NAME", "SMTP_ENCRYPTION", "SMTP_ALLOW_SELF_SIGNED")
    "reCAPTCHA" = @("RECAPTCHA_SITE_KEY", "RECAPTCHA_SECRET_KEY")
    "File Upload" = @("MAX_UPLOAD_SIZE", "ALLOWED_IMAGE_TYPES")
    "API" = @("API_KEY", "API_DEVICE_ID", "API_URL")
    "Feature Flags" = @("RATE_LIMIT_FAIL_OPEN", "ALLOW_HTTP_RESET")
}

foreach ($category in $categories.Keys) {
    $keys = $categories[$category]
    $found = $keys | Where-Object { $mergedConfig.ContainsKey($_) }
    if ($found) {
        Write-Host ""
        Write-Host "# $category Configuration" -ForegroundColor Magenta
        foreach ($key in $found) {
            $value = $mergedConfig[$key]
            if ($key -match "PASS|PASSWORD|SECRET|KEY" -and $value -ne "") {
                Write-Host "$key=***hidden***" -ForegroundColor Gray
            } else {
                Write-Host "$key=$value" -ForegroundColor Gray
            }
        }
    }
}

Write-Host ""
Write-Host "✅ Merge analysis complete!" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Next steps:" -ForegroundColor Cyan
Write-Host "  1. Open your root-level .env file" -ForegroundColor White
Write-Host "  2. Add/update variables shown above" -ForegroundColor White
Write-Host "  3. Delete old config.env files:" -ForegroundColor White
foreach ($configFile in $configFiles) {
    Write-Host "     - $configFile" -ForegroundColor Yellow
}
Write-Host ""
Write-Host "💡 Tip: See CENTRALIZE_ENV_GUIDE.md for the complete .env structure" -ForegroundColor Cyan

