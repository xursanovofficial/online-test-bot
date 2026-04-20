#!/bin/sh
set -e

SQLITE_DB="/var/www/database/database.sqlite"

echo "Checking SQLite database file..."
mkdir -p /var/www/database

if [ ! -f "$SQLITE_DB" ]; then
    echo "database.sqlite not found, creating..."
    touch "$SQLITE_DB"
fi

chmod 664 "$SQLITE_DB" 2>/dev/null || true

echo "Running pre-start commands..."
php artisan migrate --force
php artisan storage:link
php artisan app:make-webhook

exec "$@"
