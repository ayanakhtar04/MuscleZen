#!/bin/bash

# Backup script
echo "Starting backup..."

# Configuration
BACKUP_DIR="/var/backups/musclezen"
APP_DIR="/var/www/musclezen"
DB_USER="root"
DB_PASS="Mustafa786."
DB_NAME="gym_db"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p "$BACKUP_DIR/database"
mkdir -p "$BACKUP_DIR/files"

# Backup database
echo "Backing up database..."
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME > "$BACKUP_DIR/database/db_backup_$DATE.sql"

# Backup files
echo "Backing up files..."
tar -czf "$BACKUP_DIR/files/files_backup_$DATE.tar.gz" $APP_DIR

# Remove old backups (keep last 7 days)
find $BACKUP_DIR -type f -mtime +7 -exec rm {} \;

echo "Backup complete!"
