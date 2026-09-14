#!/bin/bash

# ============================================================
# TURIMA FRAM — Script UPDATE aplikasi (jalankan setelah git push)
# Jalankan di VPS: bash update.sh
# ============================================================

set -e

APP_DIR="/var/www/turima_fram"

echo "🔄 Update TURIMA FRAM dari GitHub..."

cd "$APP_DIR"
sudo chown -R $USER:$USER "$APP_DIR"

git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm install
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo chown -R www-data:www-data "$APP_DIR"
sudo chmod -R 775 "$APP_DIR/storage"
sudo chmod -R 775 "$APP_DIR/bootstrap/cache"

echo "✅ Update selesai! Aplikasi siap digunakan."
