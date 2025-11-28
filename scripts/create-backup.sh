#!/bin/bash
# Backup Script
# Creates a complete backup of the application and database

set -e

BACKUP_DIR="${BACKUP_DIR:-/backups}"
APP_DIR="${APP_DIR:-/var/www/html}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="backup_${TIMESTAMP}"
BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"

echo "📦 Creating backup: $BACKUP_NAME"

# Create backup directory
mkdir -p "$BACKUP_PATH"

# Backup files
echo "💾 Backing up application files..."
tar -czf "$BACKUP_PATH/files.tar.gz" \
    --exclude='vendor' \
    --exclude='vendors' \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='*.log' \
    "$APP_DIR"

# Backup database
echo "🗄️  Backing up database..."
if [ -f "$APP_DIR/.env" ]; then
    source "$APP_DIR/.env"
    
    DB_NAME="${DB_NAME:-firedb}"
    DB_USER="${DB_USER:-root}"
    DB_PASS="${DB_PASS:-}"
    
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_PATH/database.sql"
    
    # Compress database backup
    gzip "$BACKUP_PATH/database.sql"
else
    echo "⚠️  .env file not found, skipping database backup"
fi

# Create backup info file
cat > "$BACKUP_PATH/backup_info.txt" <<EOF
Backup created: $(date)
Application directory: $APP_DIR
Database: ${DB_NAME:-not backed up}
Size: $(du -sh "$BACKUP_PATH" | cut -f1)
EOF

echo "✅ Backup complete: $BACKUP_PATH"
echo "📊 Backup size: $(du -sh "$BACKUP_PATH" | cut -f1)"



