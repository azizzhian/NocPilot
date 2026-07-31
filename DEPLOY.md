# Deploy NocPilot ke VPS

```
Laptop → git push → GitHub → VPS otomatis
```

Jangan edit kode langsung di server.

---

## A. Instalasi awal (sekali)

### Opsi 1 — Satu perintah di VPS (paling sederhana)

SSH ke VPS (Ubuntu), lalu:

```bash
curl -fsSL https://raw.githubusercontent.com/ORG/NocPilot/main/scripts/install.sh \
  | sudo env \
      NOCPILOT_REPO=https://github.com/ORG/NocPilot.git \
      NOCPILOT_DOMAIN=noc.example.com \
      DB_PASSWORD='password_kuat' \
      bash
```

Ganti `ORG/NocPilot` dan domain/password.

Script otomatis:
- install PHP 8.3, nginx, Node, Composer, MariaDB
- `git clone` dari GitHub
- buat database + `.env`
- `composer` + build frontend + migrate (+ seed)
- nginx + queue worker + scheduler

Opsional SSL:

```bash
... INSTALL_SSL=1 CERTBOT_EMAIL=admin@example.com bash
```

Atau salin `scripts/install.env.example` → `install.env`, isi, lalu:

```bash
sudo ./scripts/install.sh ./install.env
```

### Opsi 2 — Tombol di GitHub Actions

1. Isi secrets (lihat bawah)
2. Actions → **Install** → Run workflow → isi domain
3. VPS terisi otomatis lewat SSH

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
| `NOCPILOT_REPO` | Install | URL clone, mis. `https://github.com/ORG/NocPilot.git` |
| `DB_PASSWORD` | Install | password DB |
| `DB_DATABASE` | Install | opsional, default `nocpilot` |
| `DB_USERNAME` | Install | opsional, default `nocpilot` |
| `TELEGRAM_BOT_TOKEN` | Install | opsional |
| `TELEGRAM_BOT_USERNAME` | Install | opsional |
| `CERTBOT_EMAIL` | Install | wajib jika SSL=true |

Repo private: pakai URL dengan token, mis.  
`https://x-access-token:TOKEN@github.com/ORG/NocPilot.git`

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
- [ ] Install selesai (`scripts/install.sh` atau workflow Install)
- [ ] Secrets Actions terisi → Deploy otomatis saat push
- [ ] Ganti password user admin (hasil seed)
- [ ] BotFather `/setdomain` ke domain production
- [ ] Backup harian: cron `./scripts/backup-db.sh`
