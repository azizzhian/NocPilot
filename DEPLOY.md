# Deploy NocPilot ke VPS

```
Laptop → git push → GitHub → VPS otomatis
```

Jangan edit kode langsung di server.

Repo **private**: jangan pakai `raw.githubusercontent.com` — **clone dulu**, lalu jalankan `install.sh`.

---

## A. Instalasi awal (sekali)

SSH ke VPS (Ubuntu), lalu:

```bash
# 1) Clone dari GitHub (login/token/SSH key bila repo private)
sudo mkdir -p /var/www
sudo git clone https://github.com/azizzhian/NocPilot.git /var/www/nocpilot
cd /var/www/nocpilot

# 2) Isi konfigurasi
sudo cp scripts/install.env.example /root/nocpilot-install.env
sudo nano /root/nocpilot-install.env
# wajib: NOCPILOT_DOMAIN, DB_PASSWORD
# NOCPILOT_PATH=/var/www/nocpilot (sudah default)
# NOCPILOT_REPO boleh dikosongkan jika sudah clone

# 3) Install otomatis (paket OS + DB + build + nginx + queue)
sudo ./scripts/install.sh /root/nocpilot-install.env
```

Script akan:
- install PHP 8.3, nginx, Node, Composer, MariaDB
- buat database + `.env`
- `composer` + build frontend + migrate (+ seed)
- pasang nginx + queue worker + scheduler

Opsional SSL di `install.env`: `INSTALL_SSL=1` dan `CERTBOT_EMAIL=...`

### Alternatif — GitHub Actions

1. Isi secrets (lihat bawah)
2. Actions → **Install** → Run workflow → isi domain

---

## B. Update rutin (setiap perubahan)

Di laptop:

```bash
git add .
git commit -m "pesan perubahan"
git push origin main
```

GitHub Actions **Deploy** akan: backup DB → `git pull` → build → migrate → restart service.

Manual di VPS (jika Actions belum aktif):

```bash
cd /var/www/nocpilot
./scripts/backup-db.sh
./scripts/deploy.sh
```

---

## C. Secrets GitHub (sekali)

| Secret | Dipakai | Keterangan |
|--------|---------|------------|
| `VPS_HOST` | Deploy + Install | IP / hostname VPS |
| `VPS_USER` | Deploy + Install | user SSH (punya sudo) |
| `VPS_SSH_KEY` | Deploy + Install | private key |
| `VPS_PATH` | Deploy + Install | `/var/www/nocpilot` |
| `NOCPILOT_REPO` | Install | URL clone, mis. `https://github.com/azizzhian/NocPilot.git` |
| `DB_PASSWORD` | Install | password DB |
| `DB_DATABASE` | Install | opsional, default `nocpilot` |
| `DB_USERNAME` | Install | opsional, default `nocpilot` |
| `TELEGRAM_BOT_TOKEN` | Install | opsional |
| `TELEGRAM_BOT_USERNAME` | Install | opsional |
| `CERTBOT_EMAIL` | Install | wajib jika SSL=true |

Repo private: pakai deploy key / SSH di VPS, atau URL  
`https://x-access-token:TOKEN@github.com/azizzhian/NocPilot.git`

---

## D. Rollback

```bash
cd /var/www/nocpilot
git fetch --tags
git checkout v1.0.0
./scripts/deploy.sh --skip-pull --no-migrate
# Jika schema rusak: restore dari backups/*.sql.gz
```

---

## E. Checklist production

- [ ] Domain DNS → IP VPS
- [ ] Clone + `./scripts/install.sh` (atau workflow Install)
- [ ] Secrets Actions terisi → Deploy otomatis saat push
- [ ] Ganti password user admin (hasil seed)
- [ ] BotFather `/setdomain` ke domain production
- [ ] Backup harian: cron `./scripts/backup-db.sh`
