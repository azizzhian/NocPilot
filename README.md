# NocPilot

Aplikasi operasional NOC ISP: input harian, monitoring, report, role/permission.

## Stack

- Backend: Laravel (`apps/backend`)
- Frontend: Vue 3 + Vite (`apps/frontend`)
- Dev: satu perintah menjalankan API + queue + scheduler + web

## Development (Laragon / lokal)

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

**Instalasi awal (sekali di VPS):**

```bash
curl -fsSL https://raw.githubusercontent.com/ORG/NocPilot/main/scripts/install.sh \
  | sudo env NOCPILOT_REPO=https://github.com/ORG/NocPilot.git \
             NOCPILOT_DOMAIN=noc.example.com \
             DB_PASSWORD='secret' \
             bash
```

**Update selanjutnya:** `git push origin main` (otomatis via GitHub Actions).

Detail: **[DEPLOY.md](./DEPLOY.md)** · **[CHANGELOG.md](./CHANGELOG.md)**
