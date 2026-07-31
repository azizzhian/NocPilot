@echo off
setlocal
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
  echo [NocPilot] PHP tidak ditemukan. Pastikan Laragon/PHP ada di PATH.
  pause
  exit /b 1
)

where npm >nul 2>nul
if errorlevel 1 (
  echo [NocPilot] npm tidak ditemukan. Pastikan Node.js terpasang.
  pause
  exit /b 1
)

if not exist "node_modules\concurrently" (
  echo [NocPilot] Install dependency root...
  call npm install
)

if not exist "apps\frontend\node_modules" (
  echo [NocPilot] Install dependency frontend...
  call npm install --prefix apps\frontend
)

if not exist "apps\backend\vendor" (
  echo [NocPilot] Install dependency backend...
  pushd apps\backend
  call composer install
  popd
)

echo.
echo [NocPilot] Menjalankan API + Queue + Frontend...
echo   API  : http://127.0.0.1:8000
echo   Web  : http://localhost:5173
echo   Stop : Ctrl+C
echo.

call npm run dev
endlocal
