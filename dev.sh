#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

if ! command -v php >/dev/null 2>&1; then
  echo "[NocPilot] PHP tidak ditemukan. Install PHP 8.3+ dulu."
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "[NocPilot] npm tidak ditemukan. Install Node.js dulu."
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "[NocPilot] Composer tidak ditemukan. Install Composer dulu."
  exit 1
fi

if [[ ! -d node_modules/concurrently ]]; then
  echo "[NocPilot] Install dependency root..."
  npm install
fi

if [[ ! -d apps/frontend/node_modules ]]; then
  echo "[NocPilot] Install dependency frontend..."
  npm install --prefix apps/frontend
fi

if [[ ! -d apps/backend/vendor ]]; then
  echo "[NocPilot] Install dependency backend..."
  (cd apps/backend && composer install)
fi

echo
echo "[NocPilot] Menjalankan API + Queue + Frontend..."
echo "  API  : http://127.0.0.1:8000"
echo "  Web  : http://localhost:5173"
echo "  Stop : Ctrl+C"
echo

npm run dev
