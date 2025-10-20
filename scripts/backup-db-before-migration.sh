#!/bin/bash

# Backup DB before migrations script
# This script creates a backup of the database before running migrations

# Get current date and time
TIMESTAMP=$(date +"%Y%m%d%H%M")
BACKUP_FILE="database/backups/lowino-$TIMESTAMP.sql"

# Get database credentials from .env file
DB_CONNECTION=$(grep DB_CONNECTION .env | cut -d '=' -f2)
DB_HOST=$(grep DB_HOST .env | cut -d '=' -f2)
DB_PORT=$(grep DB_PORT .env | cut -d '=' -f2)
DB_DATABASE=$(grep DB_DATABASE .env | cut -d '=' -f2)
DB_USERNAME=$(grep DB_USERNAME .env | cut -d '=' -f2)
DB_PASSWORD=$(grep DB_PASSWORD .env | cut -d '=' -f2)

# Create backup directory if it doesn't exist
mkdir -p database/backups

echo "Creating database backup to $BACKUP_FILE..."

# Export the database using mysqldump
if [ "$DB_PASSWORD" != "" ]; then
    # If password is present
    mysqldump -h $DB_HOST -P $DB_PORT -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE > $BACKUP_FILE
else
    # If no password
    mysqldump -h $DB_HOST -P $DB_PORT -u $DB_USERNAME $DB_DATABASE > $BACKUP_FILE
fi

# Check if backup was successful
if [ $? -eq 0 ]; then
    echo "Backup completed successfully!"
    echo "Backup saved to: $BACKUP_FILE"
else
    echo "Backup failed!"
    exit 1
fi

echo "You can now run the migrations with:"
echo "php artisan migrate"
