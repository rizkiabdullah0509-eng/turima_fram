@echo off
echo ========================================================
echo   Menghentikan WAHA Bot Turima Farm...
echo ========================================================
docker compose -f docker-compose.waha.yml down
echo.
echo WAHA Container telah dihentikan.
echo.
pause
