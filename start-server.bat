@echo off
title Turima Fram - Laravel Server (Multi-Worker)
echo ========================================================
echo   Menjalankan Laravel Server Turima Fram (4 Workers)
echo ========================================================
set PHP_CLI_SERVER_WORKERS=4
php artisan serve --port=8000
