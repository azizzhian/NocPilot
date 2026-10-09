# NocPilot — Panduan untuk AI Agent

File ini dibaca otomatis oleh OpenCode dan Cursor. Baca sampai habis sebelum mencari-cari di codebase.
Bahasa komunikasi dengan user: **Bahasa Indonesia**.

> **WAJIB**: di awal sesi baca `HANDOFF.md` (tugas yang sedang berjalan) dan cek `git status` / `git diff`.
> Setiap selesai satu langkah berarti, perbarui `HANDOFF.md`. Detail di bagian 9.

## 1. Apa aplikasi ini

Aplikasi operasional **NOC (Network Operations Center) ISP** untuk tim NOC shift harian:

- **Input harian**: Aktivasi, Setup CCTV, Komplain (individu / gamas = gangguan massal), Update NOC
- **Report**: Dismantle (pencabutan layanan) dan Report Ticket, termasuk import Excel & export
- **Dashboard**: statistik Clear per tipe, per NOC, per ODC, peringkat kinerja NOC
- **Monitoring MikroTik**: collector background (API MikroTik + SNMP), UI hanya membaca DB
- **Generate Report**: laporan harian teks berbasis template yang bisa diedit, disimpan sebagai snapshot/history
- **Master jaringan**: POP → OLT/ODC → ODP → ONU, Lokasi, Pelanggan, Paket Internet
- **Admin**: user, role & permission kustom (Spatie), audit/activity log, pengaturan, notifikasi update aplikasi

## 2. Stack

| Bagian | Teknologi | Lokasi |
|---|---|---|
| Backend API | Laravel 13, PHP 8.3, Sanctum (token), spatie/laravel-permission, phpspreadsheet | `apps/backend` |
| Frontend SPA | Vue 3 + Vite + TypeScript, Pinia, vue-router, Tailwind 4, ApexCharts, TanStack Table, lucide icons | `apps/frontend` |
| DB | MySQL/MariaDB (`QUEUE_CONNECTION=database`, `CACHE_STORE=database`) | |
| Dev lokal | Windows + Laragon, shell PowerShell | |
| Production | Ubuntu VPS, nginx, deploy via GitHub Actions | `scripts/`, `.github/workflows/` |

## 3. Menjalankan

```bash
npm run setup      # sekali: composer install + npm install
npm run dev        # api (8000) + queue + scheduler + web (5173) sekaligus
cd apps/backend && php artisan migrate          # --seed hanya untuk DB baru (lihat bagian 6)
npm run build:web  # build frontend (vue-tsc --noEmit && vite build) — pakai untuk cek error TypeScript
composer test --working-dir=apps/backend       # test backend (SQLite in-memory)
```

- Web: http://localhost:5173 · API: http://127.0.0.1:8000 (prefix `/api/v1`)
- User seed (password `password`): `admin`, `noc`, `manager`, `teknisi`, `engineer`
- Deploy: `git push origin main` → GitHub Actions Deploy (backup DB → pull → build → migrate → restart). Detail di `DEPLOY.md`.

## 4. Peta kode

### Backend (`apps/backend`)
- `routes/api.php` — **semua endpoint** (prefix `v1`, di dalam `auth:sanctum`). Mulai dari sini untuk mencari fitur.
- `bootstrap/app.php` — scheduler (`routers:sync` tiap 30 detik), alias middleware permission, error SQL → JSON 422 berbahasa Indonesia.
- `config/permissions.php` — **katalog permission + default per role** (sumber tunggal).
- `app/Http/Controllers/Api/` — satu controller per modul:
  - `DailyEntryController` (terbesar, ±1300 baris) — semua Input Harian, list, export, filter ODC, riwayat komplain
  - `DashboardController` — statistik dashboard & per-ODC
  - `DismantleController`, `ReportTicketController` — menu Report
  - `GenerateReportController` + `Services/Report/ReportGeneratorService.php` — generate report teks
  - `MonitoringController`, `RouterController` — MikroTik
  - `AuthController` — login + captcha + Telegram
- `app/Http/Resources/` — format JSON response
- `app/Services/`
  - `DailyEntry/` — serializer, realtime, `ComplaintHistoryAnalyzer` (complaint score)
  - `Report/` — generator, template, laporan network monitor
  - `Mikrotik/`, `Monitoring/` (RouterMonitor, SnmpPoller) — collector
  - `Dismantle/DismantleImportService`, `Customer/*Import*` — import Excel
  - `Auth/LoginMathCaptcha`, `Auth/TelegramAuthVerifier`, `Phone/PhoneNormalizer`, `Audit/ActivityLogger`
- `app/Support/` — `ReportStatus` (konstanta status), `AppSetting`, `ExcelExport`, `ReportTemplateDefaults`, `SimpleTemplateEngine`
- `app/Models/Concerns/HasDailyAttribution.php` — `created_by` / `cleared_by` / `cleared_at` (siapa input, siapa clear)
- `app/Console/Commands/` — `SyncRoutersCommand` (`routers:sync`), `RecordDeployCommand` (`app:record-deploy`, mencatat commit deploy ke tabel `app_updates` → notifikasi "update aplikasi" di Header), import pelanggan
- `database/seeders/DatabaseSeeder.php` — role, user, data demo

### Frontend (`apps/frontend/src`)
- `router/index.ts` — route + guard permission (`meta.permission`)
- `data/navigation.ts` — menu sidebar + permission per menu. **Menu baru = tambah di sini dan di router.**
- `lib/api.ts` — instance axios (Bearer token dari `localStorage.nocpilot_token`, 401 → event `nocpilot:auth-invalid`)
- `services/api.ts` — **semua fungsi API + tipe TypeScript** (±900 baris), dikelompokkan per modul (`authApi`, `oltApi`, dst.)
- `stores/auth.ts` — sesi, `can(permission)`, sync login/logout antar tab
- `views/InputHarianView.vue` — dipakai 3 route: `/komplain`, `/aktivasi` (aktivasi + CCTV), `/update-noc` (dibedakan via `meta.dailyTab(s)`)
- `views/DashboardView.vue`, `DismantleView.vue`, `ReportTicketView.vue`, `GenerateReportView.vue`, `MonitoringView.vue`
- `components/network/ResourceListView.vue` — CRUD generik untuk halaman master (OLT, ODC, ODP, POP, Lokasi, dll.)
- `components/ui/` — komponen dasar (Button, Input, Select, Modal, Toast, DateRangePicker, ...)
- `components/daily/` — komponen Input Harian (ComplaintHistoryPanel, CustomerAutocomplete, DailyStatusBadge, ...)
- `views/stubs/` — **tidak dipakai** (tidak di-import router). Jangan edit file di sini.

## 5. Aturan bisnis penting

### Status
- Input harian: `On-Progress` | `Clear` (lihat `App\Support\ReportStatus`). Dismantle: `Pending` | `On-Progress` | `Clear`.
- Saat berubah ke `Clear` → isi `cleared_by` + `cleared_at` (`syncClearAttribution`). Keluar dari Clear → dikosongkan.

### Carry-over (item terbuka dibawa ke hari berikutnya)
Tampilan tanggal X = item `report_date = X` **+** item tanggal sebelumnya yang belum Clear **+** item yang di-clear pada tanggal X.
Lihat `forSelectedDateOrStillOpen` / `forDateRangeOrStillOpen` di `DailyEntryController`.
Mode `mode=clear` → hanya item Clear dalam rentang (`forClearedInRange`), harus konsisten dengan angka Clear di dashboard.

### Mapping ODC & filter "Tanpa ODC"
Hampir semua list/dashboard punya filter ODC. Nilai `__none__` atau `Tanpa ODC` = data yang tidak bisa dipetakan ke ODC.
- **Aktivasi**: `olt_name` → `Olt.odc_id`, atau `odp_name` → `Odp.odc_id`. `olt_name` **wajib** saat input aktivasi.
- **CCTV**: `customer_name` → `Customer.odc_id`
- **Komplain / Update NOC / Report Ticket**: kolom `odc_name` langsung (komplain diisi otomatis dari ODC pelanggan)
- **Dismantle / Report Ticket**: `location` → tabel `locations` (`Location.odc_id`, alias lokasi ke ODC)
- Pencocokan nama: trim, lowercase, buang suffix dalam kurung (`normalizeLookupName`).
- Logika "Tanpa ODC" di `DailyEntryController` **harus sama persis** dengan `DashboardController` (mis. `activationCountsByOdc`). Kalau mengubah salah satunya, ubah juga yang lain.

### Permission
- Backend: middleware `permission:<nama>`. Frontend: `auth.can('<nama>')` + `meta.permission`. Role `administrator` = semua akses.
- Permission baru: tambahkan di `config/permissions.php` (groups + role_defaults), lalu buat migration baru yang menyinkronkan (contoh: `2026_07_31_110000_sync_customizable_permissions.php`).

### Auth
- Login username/password + captcha matematika (`GET /auth/captcha`) atau Telegram Login Widget.
- Token Sanctum berlaku `SANCTUM_EXPIRATION` menit (default 720 = 1 shift 12 jam).

## 6. Konvensi

- Perubahan skema **selalu migration baru**; jangan edit migration lama (sudah jalan di production).
- Pesan error/validasi & label UI dalam Bahasa Indonesia.
- Endpoint baru: route di `routes/api.php` → method controller → fungsi di `services/api.ts` (+ tipe TS).
- Frontend pakai `<script setup lang="ts">` + Composition API. Ikuti pola komponen di `components/ui/`.
- Jalankan `npm run build:web` setelah mengubah frontend untuk memastikan tidak ada error TypeScript.
- Modul **Tickets lama** (`Ticket`, `TicketActivity`) dan **Meechat** sudah tidak dipakai (route dihapus / tabel di-drop). Jangan dihidupkan lagi. Yang aktif adalah **ReportTicket** (`/report-tickets`).
- Jangan commit `.env`, file Excel/CSV data pelanggan, atau dump SQL (sudah di `.gitignore`).

### Perintah berbahaya (jangan dijalankan tanpa diminta eksplisit)
- `git push` — push ke `main` **langsung deploy ke production** lewat GitHub Actions.
- `php artisan db:seed` / `migrate --seed` — `DatabaseSeeder` me-reset password user seed (`admin`, `noc`, ...) menjadi `password`.
- `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `git reset --hard`, `git clean`, `scripts/deploy.sh`, `scripts/install.sh`.
- Test backend: pakai `composer test --working-dir=apps/backend` (menjalankan `config:clear` dulu). Test memakai SQLite `:memory:`; kalau config ter-cache, `php artisan test` bisa jalan ke DB asli.

## 7. Keputusan penting

- **2026-07-30** — Modul Tickets lama dan integrasi Meechat dihapus; fokus ke Daily Report. Ticket yang aktif adalah `ReportTicket`.
- **2026-07-30** — Monitoring memakai pola collector (ala Zabbix): scheduler `routers:sync` tiap 30 detik, UI hanya membaca DB.
- **2026-09-14** — Masa berlaku token diatur `SANCTUM_EXPIRATION` (default 720 menit = 1 shift); login memakai captcha matematika.
- **2026-09-19** — ODC dipetakan lewat OLT (`olts.odc_id`) dan tabel `locations` (alias lokasi → ODC), bukan teks bebas.
- **2026-09-22** — `olt_name` wajib diisi saat input Aktivasi supaya aktivasi selalu bisa dipetakan ke ODC.

## 8. Utang teknis yang diketahui

- Test otomatis minim (`apps/backend/tests` baru berisi contoh + `SslCaResolverTest`). Frontend belum punya test; verifikasi pakai `npm run build:web`.
- `apps/frontend/src/views/stubs/` tidak dipakai — kandidat dihapus.
- Model/tabel `Ticket`, `TicketActivity`, `Activation`, `Dismantle` lama masih dipakai `DatabaseSeeder`; `Ticket` lama sudah tidak punya route.

## 9. Serah terima Cursor ↔ OpenCode (WAJIB)

Proyek ini dikerjakan bergantian oleh Cursor dan OpenCode. Riwayat chat satu tool tidak terlihat oleh tool lain.

| File | Isi | Kapan diubah |
|---|---|---|
| `HANDOFF.md` | Tugas yang sedang berjalan: tugas sekarang, sudah selesai, langkah berikutnya, file disentuh, catatan & jebakan | Setiap selesai satu langkah berarti |
| `CHANGELOG.md` | Riwayat fitur/perbaikan yang sudah tuntas (`[Unreleased]` = sudah di `main`, belum diberi versi) | Saat tugas tuntas |
| `AGENTS.md` | Pengetahuan permanen: arsitektur, aturan bisnis, keputusan penting | Saat ada modul/aturan/keputusan baru |

Aturan:
1. Awal sesi: baca `HANDOFF.md`, lalu `git status` dan `git diff --stat` untuk melihat perubahan yang belum di-commit.
2. Selama bekerja: perbarui `HANDOFF.md` setiap selesai satu langkah berarti (bukan hanya di akhir), supaya kalau token habis di tengah jalan, agent lain tetap bisa melanjutkan.
3. Tugas tuntas: pindahkan ringkasan ke `CHANGELOG.md` `[Unreleased]`, keputusan permanen ke `AGENTS.md` bagian 7, lalu kosongkan `HANDOFF.md`.
4. Jangan commit atau push kecuali diminta.

Command OpenCode (`.opencode/commands/`): `/lanjut` (mulai sesi dari HANDOFF), `/handoff` (perbarui HANDOFF sebelum pindah tool),
`/test` (test backend + build frontend), `/worklog` (tugas tuntas → CHANGELOG, kosongkan HANDOFF).
Di Cursor cukup bilang "lanjutkan dari HANDOFF.md" atau "perbarui HANDOFF.md".
