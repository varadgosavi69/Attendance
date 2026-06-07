@echo off
title JD College Attendance Mailer
cd /d "%~dp0"

echo ---------------------------------------------------
echo  JD COLLEGE ATTENDANCE - AUTOMATED EMAILER
echo ---------------------------------------------------
echo.

:: Check if PHP is available in PATH
php -v >nul 2>&1
if %ERRORLEVEL% == 0 (
    set "PHP_BIN=php"
    goto :RUN
)

:: Check common XAMPP path as fallback
if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
    goto :RUN
)

:: If not found, show error
echo [ERROR] PHP was not found in your system.
echo 1. Ensure XAMPP is installed.
echo 2. Ensure MySQL (XAMPP) is running.
echo.
pause
exit

:RUN
echo [INFO] Found PHP. Starting process...
echo.
"%PHP_BIN%" cron/send_daily_attendance.php
echo.
echo ---------------------------------------------------
echo  Process Finished. Verify details in Logs.
echo ---------------------------------------------------
echo Press any key to exit...
pause > nul
