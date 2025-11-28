# 🚀 Deployment Script Guide

## Quick Deployment

### Automated Deployment Script

Create a deployment script based on your hosting environment:

```bash
#!/bin/bash
# deploy.sh - Automated deployment script

set -e

echo "🚀 Starting deployment..."

# 1. Backup current version
echo "📦 Creating backup..."
./scripts/create-backup.sh

# 2. Pull latest code
echo "📥 Pulling latest code..."
git pull origin main

# 3. Update dependencies
echo "📚 Updating dependencies..."
if [ -f composer.json ]; then
    composer install --no-dev --optimize-autoloader
fi

# 4. Run tests
echo "🧪 Running tests..."
./vendor/bin/phpunit --configuration tests/phpunit.xml

# 5. Clear caches
echo "🧹 Clearing caches..."
php -r "opcache_reset();" || echo "OpCache reset skipped"

# 6. Set permissions
echo "🔐 Setting permissions..."
chmod 755 .
chmod 600 .env
chmod -R 755 logs/
chmod 644 logs/.htaccess

# 7. Database migrations (if needed)
# php scripts/migrate.php

# 8. Verify deployment
echo "✅ Verifying deployment..."
curl -I https://yourdomain.com

echo "✅ Deployment complete!"
```

### Windows Deployment Script

```powershell
# deploy.ps1 - Windows deployment script

Write-Host "🚀 Starting deployment..." -ForegroundColor Cyan

# Backup
Write-Host "📦 Creating backup..." -ForegroundColor Yellow
.\scripts\create-backup.ps1

# Pull code
Write-Host "📥 Pulling latest code..." -ForegroundColor Yellow
git pull origin main

# Update dependencies
Write-Host "📚 Updating dependencies..." -ForegroundColor Yellow
if (Test-Path composer.json) {
    composer install --no-dev --optimize-autoloader
}

# Run tests
Write-Host "🧪 Running tests..." -ForegroundColor Yellow
.\vendor\bin\phpunit --configuration tests\phpunit.xml

# Clear caches
Write-Host "🧹 Clearing caches..." -ForegroundColor Yellow
# Clear PHP opcache if available

# Set permissions
Write-Host "🔐 Setting permissions..." -ForegroundColor Yellow
# Windows permissions handled differently

Write-Host "✅ Deployment complete!" -ForegroundColor Green
```

## Manual Deployment Steps

1. **Backup Current Version**
   ```bash
   ./scripts/create-backup.sh
   ```

2. **Update Code**
   ```bash
   git pull origin main
   ```

3. **Install Dependencies**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

4. **Run Tests**
   ```bash
   ./vendor/bin/phpunit
   ```

5. **Update Environment**
   ```bash
   cp .env.example .env
   # Edit .env with production values
   ```

6. **Set Permissions**
   ```bash
   chmod 600 .env
   chmod 755 logs/
   ```

7. **Clear Caches**
   ```bash
   php -r "opcache_reset();"
   ```

8. **Verify**
   ```bash
   curl -I https://yourdomain.com
   ```

## Rollback Procedure

If deployment fails:

```bash
# Quick rollback to previous version
./scripts/create-rollback.sh latest

# Or specific backup
./scripts/create-rollback.sh 20241128_120000
```

