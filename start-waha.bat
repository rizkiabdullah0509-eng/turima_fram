@echo off
echo ========================================================
echo   Menjalankan WAHA Bot Turima Farm (Port 3005)...
echo ========================================================
docker compose -f docker-compose.waha.yml up -d
echo.
echo WAHA Container berhasil dijalankan!
echo Dashboard: http://localhost:3005/dashboard/
echo Username : turima
echo Password : turima
echo.
pause
