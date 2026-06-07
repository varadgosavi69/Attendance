@echo off
title Copy project to XAMPP htdocs
set SOURCE=%~dp0
set TARGET=C:\xampp\htdocs\attendance-email-system

if not exist "C:\xampp\htdocs" (
    echo ERROR: XAMPP htdocs not found at C:\xampp\htdocs
    pause
    exit /b 1
)

echo Copying from: %SOURCE%
echo Copying to:   %TARGET%
echo.
xcopy "%SOURCE%*" "%TARGET%\" /E /Y /I
if %ERRORLEVEL% equ 0 (
    echo.
    echo Done. Open in browser:
    echo   http://localhost/attendance-email-system/public/setup_database.php
    echo.
) else (
    echo Copy failed.
)
pause
