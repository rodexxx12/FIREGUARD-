#!/bin/bash
# Rollback Script
# This script helps rollback to a previous version

set -e

BACKUP_DIR="${BACKUP_DIR:-/backups}"
APP_DIR="${APP_DIR:-/var/www/html}"
TIMESTAMP="${1:-latest}"

echo "🔄 Starting rollback process..."

# Find backup
if [ "$TIMESTAMP" = "latest" ]; then
    LATEST_BACKUP=$(ls -t "$BACKUP_DIR" | head -n 1)
    if [ -z "$LATEST_BACKUP" ]; then
        echo "❌ No backups found in $BACKUP_DIR"
        exit 1
    fi
    BACKUP_PATH="$BACKUP_DIR/$LATEST_BACKUP"
else
    BACKUP_PATH="$BACKUP_DIR/$TIMESTAMP"
    if [ ! -d "$BACKUP_PATH" ]; then
        echo "❌ Backup not found: $BACKUP_PATH"
        exit 1
    fi
fi

echo "📦 Using backup: $BACKUP_PATH"

# Backup current version before rollback
echo "💾 Creating backup of current version..."
CURRENT_BACKUP="$BACKUP_DIR/pre_rollback_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$CURRENT_BACKUP"

# Backup files
cp -r "$APP_DIR" "$CURRENT_BACKUP/files"
echo "✅ Current version backed up to: $CURRENT_BACKUP"

# Restore from backup
echo "📥 Restoring from backup..."
cp -r "$BACKUP_PATH/files/"* "$APP_DIR/"

# Restore database if backup exists
if [ -f "$BACKUP_PATH/database.sql" ]; then
    echo "🗄️  Restoring database..."
    mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "$BACKUP_PATH/database.sql"
fi

# Clear opcache
echo "🧹 Clearing opcache..."
php -r "opcache_reset();" || echo "⚠️  Could not clear opcache"

echo "✅ Rollback complete!"
echo "📝 Current version backed up to: $CURRENT_BACKUP"






















