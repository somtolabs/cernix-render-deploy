@echo off
setlocal enabledelayedexpansion
title CERNIX — Exam Verification System
color 0A
chcp 65001 > nul 2>&1

set "PHP=C:\xampp\php\php.exe"
set "PROJECT=C:\Users\hp\cernix-exam-verify\cernix"
set "PORT=8000"
set "NGROK_DOMAIN=refusal-deem-launch.ngrok-free.dev"

echo.
echo  ============================================================
echo    CERNIX  ^|  Adekunle Ajasin University  ^|  QR Verify
echo  ============================================================
echo.

:: ── Step 1: Release port 8000 ────────────────────────────────────────────────
echo  [1/4] Releasing port %PORT%...
for /f "tokens=5" %%P in ('netstat -aon 2^>nul ^| findstr /R ":%PORT% "') do (
    if not "%%P"=="0" (
        taskkill /f /pid %%P > nul 2>&1
    )
)
:: Small pause to let OS release the socket
timeout /t 1 /nobreak > nul

:: ── Step 2: Start Laravel server ────────────────────────────────────────────
echo  [2/4] Starting Laravel server on port %PORT%...
if not exist "%PROJECT%\storage\logs" mkdir "%PROJECT%\storage\logs"
start /B "" "%PHP%" "%PROJECT%\artisan" serve --host=0.0.0.0 --port=%PORT% > "%PROJECT%\storage\logs\server.log" 2>&1

:: ── Step 3: Wait until server responds ──────────────────────────────────────
echo  [3/4] Waiting for server to be ready...
set /a TRIES=0
:POLL
set /a TRIES+=1
if %TRIES% gtr 40 (
    echo.
    echo  ERROR: Server did not become ready after 20 seconds.
    echo  Check: %PROJECT%\storage\logs\server.log
    echo.
    pause
    exit /b 1
)
timeout /t 1 /nobreak > nul
powershell -NoProfile -NonInteractive -Command ^
    "try { $null = Invoke-WebRequest 'http://localhost:%PORT%/up' -TimeoutSec 1 -UseBasicParsing -ErrorAction Stop; exit 0 } catch { exit 1 }" > nul 2>&1
if errorlevel 1 goto POLL
echo  [3/4] Server is ready at http://localhost:%PORT%

:: ── Step 4: Start ngrok tunnel ───────────────────────────────────────────────
echo  [4/4] Connecting ngrok tunnel (%NGROK_DOMAIN%)...
start /B "" ngrok http --domain=%NGROK_DOMAIN% %PORT% > "%TEMP%\ngrok-cernix.log" 2>&1

:: Give ngrok 3 seconds to establish
timeout /t 3 /nobreak > nul

echo.
echo  ============================================================
echo   LOCAL :  http://localhost:%PORT%
echo   PUBLIC:  https://%NGROK_DOMAIN%
echo  ============================================================
echo.
echo  System is running. Close this window to stop all services.
echo  (ngrok log: %TEMP%\ngrok-cernix.log)
echo.
pause > nul

:: Cleanup on exit
echo  Shutting down...
for /f "tokens=5" %%P in ('netstat -aon 2^>nul ^| findstr /R ":%PORT% "') do (
    taskkill /f /pid %%P > nul 2>&1
)
taskkill /f /im ngrok.exe > nul 2>&1
endlocal
