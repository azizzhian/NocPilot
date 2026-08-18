#!/usr/bin/env bash
# Deploy NocPilot di VPS setelah git pull / clone.
# Usage (dari root project):
#   ./scripts/deploy.sh
#   ./scripts/deploy.sh --skip-pull
#   ./scripts/deploy.sh --no-migrate
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SKIP_PULL=0
NO_MIGRATE=0
BRANCH="${DEPLOY_BRANCH:-main}"

for arg in "$@"; do
  case "$arg" in
    --skip-pull) SKIP_PULL=1 ;;
    --no-migrate) NO_MIGRATE=1 ;;
    *)
      echo "Unknown option: $arg"
      echo "Usage: $0 [--skip-pull] [--no-migrate]"
      exit 1
      ;;
  esac
done

echo "[deploy] Root: $ROOT"

if [[ ! -f apps/backend/.env ]]; then
  echo "[deploy] ERROR: apps/backend/.env belum ada. Salin dari .env.example lalu isi production."
  exit 1
fi

if [[ ! -f apps/frontend/.env ]]; then
  echo "[deploy] Membuat apps/frontend/.env dari .env.example"
  cp apps/frontend/.env.example apps/frontend/.env
fi

DEPLOY_FROM=""
if [[ -d .git ]]; then
  DEPLOY_FROM="$(git rev-parse HEAD 2>/dev/null || true)"
fi

if [[ "$SKIP_PULL" -eq 0 ]]; then
  if [[ -d .git ]]; then
    echo "[deploy] git fetch + pull ($BRANCH)"
    git fetch --all --tags
    git checkout "$BRANCH"
    git pull --ff-only origin "$BRANCH"
  else
    echo "[deploy] Bukan git repo — lewati pull"
  fi
fi

DEPLOY_TO=""
if [[ -d .git ]]; then
  DEPLOY_TO="$(git rev-parse HEAD 2>/dev/null || true)"
fi

echo "[deploy] composer install (backend)"
composer install --working-dir=apps/backend --no-dev --optimize-autoloader --no-interaction

echo "[deploy] npm ci + build (frontend)"
if [[ -f apps/frontend/package-lock.json ]]; then
  npm ci --prefix apps/frontend
else
  npm install --prefix apps/frontend
fi
npm run build --prefix apps/frontend

if [[ "$NO_MIGRATE" -eq 0 ]]; then
  echo "[deploy] php artisan migrate --force"
  php apps/backend/artisan migrate --force
else
  echo "[deploy] Lewati migrate (--no-migrate)"
fi

if [[ -n "$DEPLOY_FROM" && -n "$DEPLOY_TO" && "$DEPLOY_FROM" != "$DEPLOY_TO" ]]; then
  echo "[deploy] Catat update aplikasi ($DEPLOY_FROM..$DEPLOY_TO)"
  php apps/backend/artisan app:record-deploy "$DEPLOY_FROM" "$DEPLOY_TO" --branch="$BRANCH" || true
fi

echo "[deploy] Laravel optimize"
php apps/backend/artisan config:cache
php apps/backend/artisan route:cache
php apps/backend/artisan view:cache
php apps/backend/artisan storage:link 2>/dev/null || true

echo "[deploy] Restart queue workers (abaikan jika belum ada supervisor)"
php apps/backend/artisan queue:restart 2>/dev/null || true

# Opsional: sesuaikan nama service di VPS Anda
if command -v systemctl >/dev/null 2>&1; then
  for svc in nocpilot-queue nocpilot-scheduler php8.3-fpm php-fpm nginx; do
    if systemctl list-unit-files "${svc}.service" >/dev/null 2>&1; then
      echo "[deploy] systemctl reload/restart $svc"
      sudo systemctl reload "$svc" 2>/dev/null || sudo systemctl restart "$svc" 2>/dev/null || true
    fi
  done
fi

echo
echo "[deploy] Selesai."
echo "  Pastikan nginx mengarah ke frontend dist + proxy /api ke Laravel."
echo "  Pastikan queue worker + scheduler jalan (supervisor/systemd)."
