#!/usr/bin/env bash
# Instalasi awal NocPilot di VPS (Ubuntu/Debian).
#
# Cara 1 — dari repo yang sudah di-clone:
#   cp scripts/install.env.example install.env   # edit isinya
#   sudo ./scripts/install.sh ./install.env
#
# Cara 2 — satu perintah (belum ada clone):
#   curl -fsSL https://raw.githubusercontent.com/ORG/NocPilot/main/scripts/install.sh \
#     | sudo env NOCPILOT_REPO=https://github.com/ORG/NocPilot.git \
#                NOCPILOT_DOMAIN=noc.example.com \
#                DB_PASSWORD=secret \
#                bash
#
# Setelah ini, update rutin: git push → GitHub Actions (lihat DEPLOY.md)
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

log() { echo "[install] $*"; }
die() { echo "[install] ERROR: $*" >&2; exit 1; }

[[ "$(id -u)" -eq 0 ]] || die "Jalankan sebagai root (sudo)."

if [[ "${1:-}" != "" && -f "$1" ]]; then
  log "Memuat env: $1"
  set -a
  # shellcheck disable=SC1090
  source "$1"
  set +a
fi

NOCPILOT_REPO="${NOCPILOT_REPO:-}"
NOCPILOT_DOMAIN="${NOCPILOT_DOMAIN:-}"
NOCPILOT_PATH="${NOCPILOT_PATH:-/var/www/nocpilot}"
NOCPILOT_BRANCH="${NOCPILOT_BRANCH:-main}"
NOCPILOT_APP_NAME="${NOCPILOT_APP_NAME:-NocPilot}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-nocpilot}"
DB_USERNAME="${DB_USERNAME:-nocpilot}"
DB_PASSWORD="${DB_PASSWORD:-}"
INSTALL_MYSQL_LOCAL="${INSTALL_MYSQL_LOCAL:-1}"
NOCPILOT_SEED="${NOCPILOT_SEED:-1}"
TELEGRAM_BOT_TOKEN="${TELEGRAM_BOT_TOKEN:-}"
TELEGRAM_BOT_USERNAME="${TELEGRAM_BOT_USERNAME:-}"
INSTALL_NGINX="${INSTALL_NGINX:-1}"
INSTALL_SYSTEMD="${INSTALL_SYSTEMD:-1}"
INSTALL_SSL="${INSTALL_SSL:-0}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-}"
APP_URL="${APP_URL:-https://${NOCPILOT_DOMAIN}}"

[[ -n "$NOCPILOT_REPO" ]] || die "Set NOCPILOT_REPO (URL git clone)."
[[ -n "$NOCPILOT_DOMAIN" ]] || die "Set NOCPILOT_DOMAIN (domain produksi)."
[[ -n "$DB_PASSWORD" ]] || die "Set DB_PASSWORD."

if [[ ! -f /etc/os-release ]]; then
  die "Hanya mendukung Linux dengan /etc/os-release (Ubuntu/Debian)."
fi
# shellcheck disable=SC1091
. /etc/os-release
case "${ID:-}" in
  ubuntu|debian) ;;
  *) die "Distro belum didukung otomatis: ${ID:-unknown}. Pakai Ubuntu/Debian." ;;
esac

set_env_file() {
  local key="$1" val="$2" file="$3"
  python3 - "$key" "$val" "$file" <<'PY'
import sys
key, val, path = sys.argv[1], sys.argv[2], sys.argv[3]
lines = []
found = False
try:
    with open(path, encoding="utf-8") as f:
        lines = f.read().splitlines()
except FileNotFoundError:
    pass
out = []
for line in lines:
    if line.startswith(key + "="):
        out.append(f"{key}={val}")
        found = True
    else:
        out.append(line)
if not found:
    out.append(f"{key}={val}")
with open(path, "w", encoding="utf-8") as f:
    f.write("\n".join(out) + "\n")
PY
}

install_packages() {
  log "Update apt + install paket sistem"
  apt-get update -y
  apt-get install -y \
    ca-certificates curl gnupg unzip git python3 \
    nginx \
    default-mysql-client \
    software-properties-common

  if [[ "$INSTALL_MYSQL_LOCAL" == "1" ]]; then
    apt-get install -y mariadb-server
    systemctl enable --now mariadb
  fi

  if [[ "${ID}" == "ubuntu" ]]; then
    add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
    apt-get update -y
  fi

  apt-get install -y \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml php8.3-mbstring \
    php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl php8.3-sqlite3

  systemctl enable --now php8.3-fpm

  if ! command -v composer >/dev/null 2>&1; then
    log "Install Composer"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
  fi

  if ! command -v node >/dev/null 2>&1 || [[ "$(node -v | sed 's/v//' | cut -d. -f1)" -lt 20 ]]; then
    log "Install Node.js 20"
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
  fi

  if [[ "$INSTALL_SSL" == "1" ]]; then
    apt-get install -y certbot python3-certbot-nginx
  fi
}

setup_mysql() {
  [[ "$INSTALL_MYSQL_LOCAL" == "1" ]] || return 0
  log "Buat database/user MySQL: $DB_DATABASE / $DB_USERNAME"
  mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
}

clone_or_update() {
  mkdir -p "$(dirname "$NOCPILOT_PATH")"
  if [[ -d "$NOCPILOT_PATH/.git" ]]; then
    log "Repo sudah ada — pull $NOCPILOT_BRANCH"
    git -C "$NOCPILOT_PATH" fetch --all --tags
    git -C "$NOCPILOT_PATH" checkout "$NOCPILOT_BRANCH"
    git -C "$NOCPILOT_PATH" pull --ff-only origin "$NOCPILOT_BRANCH" || true
  else
    log "Clone $NOCPILOT_REPO → $NOCPILOT_PATH"
    git clone --branch "$NOCPILOT_BRANCH" "$NOCPILOT_REPO" "$NOCPILOT_PATH"
  fi
  chmod +x "$NOCPILOT_PATH"/scripts/*.sh 2>/dev/null || true
}

write_backend_env() {
  local envf="$NOCPILOT_PATH/apps/backend/.env"
  log "Tulis $envf"
  if [[ ! -f "$envf" ]]; then
    cp "$NOCPILOT_PATH/apps/backend/.env.example" "$envf"
  fi

  set_env_file APP_NAME "$NOCPILOT_APP_NAME" "$envf"
  set_env_file APP_ENV production "$envf"
  set_env_file APP_DEBUG false "$envf"
  set_env_file APP_URL "$APP_URL" "$envf"
  set_env_file DB_CONNECTION mysql "$envf"
  set_env_file DB_HOST "$DB_HOST" "$envf"
  set_env_file DB_PORT "$DB_PORT" "$envf"
  set_env_file DB_DATABASE "$DB_DATABASE" "$envf"
  set_env_file DB_USERNAME "$DB_USERNAME" "$envf"
  set_env_file DB_PASSWORD "$DB_PASSWORD" "$envf"
  set_env_file QUEUE_CONNECTION database "$envf"
  set_env_file SESSION_DRIVER database "$envf"
  set_env_file CACHE_STORE database "$envf"
  set_env_file LOG_LEVEL warning "$envf"
  set_env_file TELEGRAM_BOT_TOKEN "$TELEGRAM_BOT_TOKEN" "$envf"
  set_env_file TELEGRAM_BOT_USERNAME "$TELEGRAM_BOT_USERNAME" "$envf"

  if ! grep -qE '^APP_KEY=base64:' "$envf"; then
    log "Generate APP_KEY"
    (cd "$NOCPILOT_PATH/apps/backend" && php artisan key:generate --force)
  fi
}

write_frontend_env() {
  local envf="$NOCPILOT_PATH/apps/frontend/.env"
  log "Tulis $envf"
  echo 'VITE_API_URL=/api/v1' > "$envf"
}

detect_php_fpm_sock() {
  if [[ -n "${PHP_FPM_SOCK:-}" && -S "$PHP_FPM_SOCK" ]]; then
    echo "$PHP_FPM_SOCK"
    return
  fi
  for s in /run/php/php8.3-fpm.sock /run/php/php-fpm.sock; do
    if [[ -S "$s" ]]; then
      echo "$s"
      return
    fi
  done
  die "Socket PHP-FPM tidak ditemukan. Set PHP_FPM_SOCK."
}

install_nginx_site() {
  [[ "$INSTALL_NGINX" == "1" ]] || return 0
  local sock
  sock="$(detect_php_fpm_sock)"
  local conf="/etc/nginx/sites-available/nocpilot"
  log "Tulis nginx site → $conf"

  cat > "$conf" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${NOCPILOT_DOMAIN};

    client_max_body_size 32M;

    # SPA frontend
    root ${NOCPILOT_PATH}/apps/frontend/dist;
    index index.html;

    # Laravel API (/api/v1/...)
    location ^~ /api {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME ${NOCPILOT_PATH}/apps/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT ${NOCPILOT_PATH}/apps/backend/public;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param REQUEST_URI \$request_uri;
        fastcgi_pass unix:${sock};
        fastcgi_read_timeout 120;
    }

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 7d;
        access_log off;
        try_files \$uri =404;
    }
}
NGINX

  ln -sfn "$conf" /etc/nginx/sites-enabled/nocpilot
  rm -f /etc/nginx/sites-enabled/default
  nginx -t
  systemctl enable --now nginx
  systemctl reload nginx
}

install_systemd_units() {
  [[ "$INSTALL_SYSTEMD" == "1" ]] || return 0
  log "Pasang systemd queue + scheduler"

  cat > /etc/systemd/system/nocpilot-queue.service <<UNIT
[Unit]
Description=NocPilot queue worker
After=network.target mysql.service mariadb.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=3
WorkingDirectory=${NOCPILOT_PATH}/apps/backend
ExecStart=/usr/bin/php ${NOCPILOT_PATH}/apps/backend/artisan queue:work --sleep=1 --tries=3 --timeout=120
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
UNIT

  cat > /etc/systemd/system/nocpilot-scheduler.service <<UNIT
[Unit]
Description=NocPilot scheduler
After=network.target mysql.service mariadb.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=3
WorkingDirectory=${NOCPILOT_PATH}/apps/backend
ExecStart=/usr/bin/php ${NOCPILOT_PATH}/apps/backend/artisan schedule:work
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
UNIT

  systemctl daemon-reload
  systemctl enable --now nocpilot-queue nocpilot-scheduler
}

fix_permissions() {
  log "Permission storage / bootstrap / dist"
  chown -R www-data:www-data \
    "$NOCPILOT_PATH/apps/backend/storage" \
    "$NOCPILOT_PATH/apps/backend/bootstrap/cache"
  chmod -R ug+rwx \
    "$NOCPILOT_PATH/apps/backend/storage" \
    "$NOCPILOT_PATH/apps/backend/bootstrap/cache"
  # Biar nginx bisa baca frontend build
  find "$NOCPILOT_PATH/apps/frontend/dist" -type d -exec chmod 755 {} \; 2>/dev/null || true
  find "$NOCPILOT_PATH/apps/frontend/dist" -type f -exec chmod 644 {} \; 2>/dev/null || true
}

run_app_build() {
  log "Composer + npm build + migrate"
  cd "$NOCPILOT_PATH"

  composer install --working-dir=apps/backend --no-dev --optimize-autoloader --no-interaction

  if [[ -f apps/frontend/package-lock.json ]]; then
    npm ci --prefix apps/frontend
  else
    npm install --prefix apps/frontend
  fi
  npm run build --prefix apps/frontend

  php apps/backend/artisan migrate --force
  if [[ "$NOCPILOT_SEED" == "1" ]]; then
    log "db:seed"
    php apps/backend/artisan db:seed --force || log "Seed gagal/lewati (mungkin sudah pernah dijalankan)"
  fi

  php apps/backend/artisan storage:link 2>/dev/null || true
  php apps/backend/artisan config:cache
  php apps/backend/artisan route:cache
  php apps/backend/artisan view:cache
}

maybe_ssl() {
  [[ "$INSTALL_SSL" == "1" ]] || return 0
  [[ -n "$CERTBOT_EMAIL" ]] || die "INSTALL_SSL=1 membutuhkan CERTBOT_EMAIL"
  log "Certbot untuk $NOCPILOT_DOMAIN"
  certbot --nginx -d "$NOCPILOT_DOMAIN" --non-interactive --agree-tos -m "$CERTBOT_EMAIL" --redirect || \
    log "Certbot gagal — cek DNS domain mengarah ke VPS ini, lalu jalankan ulang certbot."
}

log "Mulai instalasi NocPilot → $NOCPILOT_PATH ($NOCPILOT_DOMAIN)"
install_packages
setup_mysql
clone_or_update
write_backend_env
write_frontend_env
run_app_build
fix_permissions
install_nginx_site
install_systemd_units
maybe_ssl

systemctl restart nocpilot-queue nocpilot-scheduler 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

echo
log "Selesai."
echo "  URL     : $APP_URL"
echo "  Path    : $NOCPILOT_PATH"
echo "  Update  : git push ke main (GitHub Actions) atau di VPS: ./scripts/deploy.sh"
if [[ "$NOCPILOT_SEED" == "1" ]]; then
  echo "  Login   : cek seeder (biasanya admin / password) — ganti segera!"
fi
echo
