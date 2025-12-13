#!/bin/sh
set -e

SQLITE_DB="/var/www/database/database.sqlite"

echo "Checking SQLite database file..."
mkdir -p /var/www/database

if [ ! -f "$SQLITE_DB" ]; then
    echo "database.sqlite not found, creating..."
    touch "$SQLITE_DB"
fi

chown appuser:appgroup "$SQLITE_DB"
chmod 664 "$SQLITE_DB"

echo "Running pre-start commands..."
php artisan migrate
php artisan storage:link
php artisan app:make-webhook

exec "$@"
