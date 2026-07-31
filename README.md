# NocPilot

Aplikasi operasional NOC ISP: input harian, monitoring, report, role/permission.

## Stack

- Backend: Laravel (`apps/backend`)
- Frontend: Vue 3 + Vite (`apps/frontend`)

## Development (lokal)

```bash
npm run setup   # sekali
npm run dev     # atau ./dev.sh / dev.bat
```

- Web: http://localhost:5173  
- API: http://127.0.0.1:8000  

Salin env:

```bash
cp apps/backend/.env.example apps/backend/.env
cp apps/frontend/.env.example apps/frontend/.env
cd apps/backend && php artisan key:generate && php artisan migrate --seed
```

## Production

**Instalasi awal di VPS** :

```bash
sudo git clone https://github.com/azizzhian/NocPilot.git /var/www/nocpilot
cd /var/www/nocpilot
sudo cp scripts/install.env.example /root/nocpilot-install.env
# edit: NOCPILOT_DOMAIN + DB_PASSWORD
sudo ./scripts/install.sh /root/nocpilot-install.env
```

**Update selanjutnya:** `git push origin main` (otomatis via GitHub Actions).

Detail: **[DEPLOY.md](./DEPLOY.md)** · **[CHANGELOG.md](./CHANGELOG.md)**
