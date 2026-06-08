@echo off
title GastroLink QR
setlocal

echo.
echo  === GastroLink QR - spustanie ===
echo.

:: PHP - priama cesta (najspolahlivejsia metoda)
set "PHP=C:\Program Files\PHP\8.5.7\nts\x64\php.exe"

if not exist "%PHP%" set "PHP=C:\php\php.exe"
if not exist "%PHP%" set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=C:\php8\php.exe"

if not exist "%PHP%" (
    echo [CHYBA] PHP nenajdene. Over cestu v run.bat
    pause
    exit /b 1
)
echo [OK] PHP: %PHP%

:: Potrebne rozsirenia
set "EXT="
"%PHP%" -r "exit(extension_loaded('pdo_sqlite')?0:1);" 2>nul
if errorlevel 1 (
    set "EXT=%EXT% -d extension=pdo_sqlite -d extension=sqlite3"
    echo [INFO] pdo_sqlite: pridavam -d priznak
) else (
    echo [OK] pdo_sqlite: OK
)

"%PHP%" -r "exit(extension_loaded('mbstring')?0:1);" 2>nul
if errorlevel 1 (
    set "EXT=%EXT% -d extension=mbstring"
    echo [INFO] mbstring: pridavam -d priznak
) else (
    echo [OK] mbstring: OK
)

:: index.php
if not exist "%~dp0index.php" (
    echo [CHYBA] index.php nenajdeny
    pause
    exit /b 1
)
echo [OK] index.php: OK

echo.
echo  URL : http://localhost:8080
echo  Stop: Ctrl+C
echo.

start "" "http://localhost:8080"

cd /d "%~dp0"
"%PHP%" %EXT% -S localhost:8080 index.php

echo.
pause
