@echo off
title Cloudflare Tunnel - Monreport Balmon Samarinda
echo ==============================================================
echo   MENYALAKAN AKSES CLOUD INTERNET UNTUK DUMMY WEBSITE
echo   Sistem Monitoring Balmon Samarinda (Monreport)
echo ==============================================================
echo.
echo Pastikan Apache XAMPP sudah berjalan di port 80.
echo Menghubungkan ke Cloudflare Network...
echo.
scratch\cloudflared.exe tunnel --url http://localhost:80
pause
