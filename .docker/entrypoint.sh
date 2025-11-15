#!/bin/sh

# Exit immediately if a command exits with a non-zero status
set -e

echo "Running pre-start commands..."
php artisan storage:link
php artisan make:webhook

# Execute the CMD instruction (the main command)
exec "$@"
