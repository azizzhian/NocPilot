---
description: Jalankan test backend + build frontend, lalu rangkum kegagalan
---

Jalankan verifikasi NocPilot secara berurutan:

1. Test backend (SQLite in-memory, aman untuk DB lokal):
   `composer test --working-dir=apps/backend`
2. Build frontend (cek error TypeScript + build Vite; frontend belum punya unit test):
   `npm run build:web`

Jangan jalankan `php artisan test` langsung; `composer test` menjalankan `config:clear` dulu supaya test tidak menyentuh DB asli.

Setelah selesai, rangkum:
- Hasil tiap langkah (lulus/gagal, jumlah test).
- Untuk setiap kegagalan: nama test atau file:baris, pesan error, dan dugaan penyebabnya.
- Usulan perbaikan. **Jangan mengedit file sebelum saya setujui.**

Fokus tambahan (boleh kosong): $ARGUMENTS
