#!/bin/sh
set -e

SQLITE_DB="/var/www/database/database.sqlite"

echo "Checking SQLite database file..."
mkdir -p /var/www/database

if [ ! -f "$SQLITE_DB" ]; then
    echo "database.sqlite not found, creating..."
    touch "$SQLITE_DB"
fi

chown 1000:1000 ./database/database.sqlite
chmod 664 ./database/database.sqlite

echo "Running pre-start commands..."
php artisan migrate
php artisan storage:link
php artisan app:make-webhook

exec "$@"
