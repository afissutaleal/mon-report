@echo off
title Push Monreport ke GitHub
color 0b
echo ==============================================================
echo       PUSH PERUBAHAN MONREPORT KE REPOSITORI GITHUB
echo       Repository: https://github.com/afissutaleal/monreport
echo ==============================================================
echo.
cd /d C:\xampp\htdocs\monreport

echo Menjalankan: git push origin main ...
echo.
git push origin main

echo.
if %errorlevel% equ 0 (
    echo ==============================================================
    echo [BERHASIL] Seluruh file projek berhasil ter-upload ke GitHub!
    echo Sekarang Anda bisa menghubungkannya ke Render.com untuk deploy.
    echo ==============================================================
) else (
    echo ==============================================================
    echo [CATATAN] Jika diminta login, silakan klik 'Sign in with your browser'.
    echo ==============================================================
)
echo.
pause
