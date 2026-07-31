# Deploy NocPilot ke VPS

Alur yang disarankan:

```
Laptop (dev) → Git (GitHub/GitLab) → VPS (production)
```

Jangan edit kode langsung di server.

## 1. Persiapan Git (sekali)

```bash
# di laptop, dari root NocPilot
git init
git add .
git commit -m "chore: initial NocPilot v1.0.0"
git branch -M main
git remote add origin git@github.com:ORG/NocPilot.git
git push -u origin main
git tag v1.0.0
git push origin v1.0.0
```

Pastikan file secret tidak ikut: `.env`, `vendor/`, `node_modules/`, data `*.xls`.

## 2. Deploy pertama di VPS

```bash
sudo mkdir -p /var/www
cd /var/www
git clone git@github.com:ORG/NocPilot.git nocpilot
cd nocpilot

cp apps/backend/.env.example apps/backend/.env
# edit: APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, TELEGRAM_*, APP_KEY
cd apps/backend && php artisan key:generate && cd ../..

cp apps/frontend/.env.example apps/frontend/.env

chmod +x scripts/*.sh
./scripts/backup-db.sh   # setelah DB sudah ada
./scripts/deploy.sh --skip-pull
```

Siapkan juga:

- Nginx: serve `apps/frontend/dist` + reverse proxy `/api` → Laravel `public`
- Supervisor/systemd: `queue:work` + `schedule:work` (atau cron `schedule:run`)
- HTTPS (Let's Encrypt)

## 3. Update rutin

Di laptop:

```bash
git checkout -b feature/nama-fitur
# ... coding + test lokal (npm run dev)
git add .
git commit -m "feat: ..."
git checkout main
git merge feature/nama-fitur
# update CHANGELOG.md, bump version bila perlu
git tag v1.1.0
git push origin main --tags
```

Di VPS:

```bash
cd /var/www/nocpilot
./scripts/backup-db.sh
./scripts/deploy.sh
```

`deploy.sh` akan: `git pull` → `composer install` → `npm build` → `migrate` → cache Laravel → restart queue.

## 4. Rollback cepat

```bash
cd /var/www/nocpilot
git fetch --tags
git checkout v1.0.0
./scripts/deploy.sh --skip-pull --no-migrate
# Jika migrate sudah merusak schema: restore SQL dari backups/
```

## 5. Otomatisasi (opsional)

Lihat `.github/workflows/deploy.yml`.

Secrets yang dibutuhkan di GitHub:

| Secret | Keterangan |
|--------|------------|
| `VPS_HOST` | IP/hostname VPS |
| `VPS_USER` | user SSH |
| `VPS_SSH_KEY` | private key |
| `VPS_PATH` | path project, mis. `/var/www/nocpilot` |

## 6. Checklist production

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] HTTPS + domain
- [ ] Queue worker + scheduler jalan
- [ ] Backup DB harian (cron `scripts/backup-db.sh`)
- [ ] BotFather `/setdomain` ke domain production
- [ ] `CHANGELOG.md` diisi tiap rilis
