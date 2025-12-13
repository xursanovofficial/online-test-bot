#!/bin/sh

# Exit immediately if a command exits with a non-zero status
set -e

# SQLite fayl manzili
SQLITE_DB="/var/www/database/database.sqlite"

echo "Checking SQLite database file..."

# Agar fayl mavjud bo'lmasa yaratamiz
if [ ! -f "$SQLITE_DB" ]; then
    echo "database.sqlite not found, creating..."
    # katalog mavjud ekanini ham tekshirib yaratib qo'yamiz
    mkdir -p /var/www/database
    touch "$SQLITE_DB"
    chmod 664 "$SQLITE_DB"
else
    echo "database.sqlite already exists. Skipping..."
fi

echo "Running pre-start commands..."
php artisan migrate
php artisan storage:link
php artisan app:make-webhook

# Execute the CMD instruction (the main command)
exec "$@"
