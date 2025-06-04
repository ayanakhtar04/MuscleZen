#!/bin/bash

# Deployment script
echo "Starting deployment..."

# Check if running with correct permissions
if [ "$(id -u)" != "0" ]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

# Configuration
APP_DIR="/var/www/musclezen"
BACKUP_DIR="/var/backups/musclezen"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup
echo "Creating backup..."
mkdir -p $BACKUP_DIR
tar -czf "$BACKUP_DIR/backup_$DATE.tar.gz" $APP_DIR

# Update code from repository
echo "Updating code..."
cd $APP_DIR
git pull origin main

# Install/update dependencies
echo "Updating dependencies..."
composer install --no-dev --optimize-autoloader

# Clear cache
echo "Clearing cache..."
rm -rf tmp/cache/*

# Update permissions
echo "Setting permissions..."
chown -R www-data:www-data $APP_DIR
find $APP_DIR -type f -exec chmod 644 {} \;
find $APP_DIR -type d -exec chmod 755 {} \;
chmod -R 777 $APP_DIR/uploads
chmod -R 777 $APP_DIR/logs

# Run database migrations
echo "Running database migrations..."
php scripts/migrate.php

echo "Deployment complete!"
