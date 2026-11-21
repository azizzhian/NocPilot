# NocPilot — Panduan untuk AI Agent

File ini dibaca otomatis oleh OpenCode dan Cursor. Baca sampai habis sebelum mencari-cari di codebase.
Bahasa komunikasi dengan user: **Bahasa Indonesia**.

> **WAJIB**: di awal sesi baca `HANDOFF.md` (tugas yang sedang berjalan) dan cek `git status` / `git diff`.
> Setiap selesai satu langkah berarti, perbarui `HANDOFF.md`. Detail di bagian 9.

## 1. Apa aplikasi ini

Aplikasi operasional **NOC (Network Operations Center) ISP** untuk tim NOC shift harian:

- **Input harian**: Aktivasi, Setup CCTV, Komplain (individu), Update NOC
- **Gamas**: gangguan massal (ODP/Upstream/OLT) — modul **terpisah** dari Komplain (tabel `daily_gamas`, menu `/gamas`)
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
  - `GamasController` + `GamasEventsController` — modul Gamas (CRUD/list/stats/export + realtime channel `gamas`)
  - `DashboardController` — statistik dashboard & per-ODC
  - `DismantleController`, `ReportTicketController` — menu Report
  - `GenerateReportController` + `Services/Report/ReportGeneratorService.php` — generate report teks
  - `MonitoringController`, `RouterController` — MikroTik
  - `AuthController` — login + captcha + Telegram
- `app/Http/Resources/` — format JSON response
- `app/Services/`
  - `DailyEntry/` — serializer, realtime, `ComplaintHistoryAnalyzer` (complaint score)
  - `Gamas/GamasStatsService` — statistik gamas (dipakai `GamasController::stats` & widget dashboard)
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
- `views/GamasView.vue` — halaman Gamas (`/gamas`), pola mirip ReportTicketView + realtime channel `gamas`
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

### Alur komplain & performa penanganan (2026-11-19)
- Status komplain: `On-Progress` → **`Menunggu Konfirmasi`** (NOC klik "Tandai Selesai", wajib isi `action`) → `Clear` (manual) atau **auto-clear 30 menit** oleh command `complaint:auto-clear` (jadwal `everyMinute`). Tipe lain tetap hanya `On-Progress`/`Clear` (`ReportStatus::options()` vs `complaintOptions()`).
- Durasi penilaian = `created_at` → `cleared_at` (**termasuk** jeda menunggu konfirmasi), disimpan di `handling_minutes` + `performance_category`: 1–30 **Cepat**, 31–40 **Lambat**, 41–60 **Cukup Lambat**, >60 **Sangat Lambat** (`DailyComplaint::performanceCategoryFor`). Keluar dari Clear → metrik di-reset.
- Auto-clear adalah aksi sistem: `cleared_by = null`, `cleared_at = now()` eksplisit, `auto_cleared = true`, activity log `user_name = 'System / Scheduler'` + `meta.source = 'scheduler'` (jangan memakai `handled_by`/`created_by` sebagai pelaku). Manual Clear tetap mencatat ID pembuat yang melakukan Clear.
- Komplain berulang: otomatis saat `storeComplaint` — komplain terakhir (individu: customer_id/code/phone/nama sama) dalam **24 jam** → `parent_complaint_id` + `complaint_group_id`.
- Item `Menunggu Konfirmasi` = **open** (ikut carry-over & hitungan open). Widget dashboard "Performa Komplain" digate `dashboard.widget.complaint_performance`.

### Minimum umur Clear manual (2026-11-21)
- Transisi **bukan Clear → Clear** (manual) hanya boleh setelah **`created_at` berusia ≥ 300 detik** (presisi detik: 4m59s ditolak, tepat 5m diizinkan). Sumber waktu: `now()` backend; frontend memakai offset dari header HTTP `Date` (`serverNow()` di `lib/api.ts`), fallback `Date.now()`.
- `created_at` kosong/tidak valid → **tolak 422** (`errors.created_at`), jangan menganggap sudah memenuhi umur. Tidak ada fallback ke `report_date`.
- Aturan hanya di `DailyEntryController` (`assertComplaintMinimumAgeForClear`, dipanggil di `updateStatus` & `updateComplaint`). Edit komplain yang **sudah Clear** (no-op status) tidak divalidasi umur lagi.
- `storeComplaint` juga menolak 422 (`errors.status`) bila komplain baru dibuat langsung dengan status **Clear** (komplain baru selalu berumur ~0 detik). Auto-clear scheduler, `syncHandling`/`syncClearAttribution`, Gamas, dan modul lain tidak terkena guard ini.
- Prioritas error manual: **403 pembuat** → **422 Action wajib** → **422 umur**. Tombol pill Clear & opsi Clear di form Edit dinonaktifkan dengan countdown mm:ss + tooltip; backend tetap memvalidasi (bukan hanya frontend).
- **Dashboard Performa Komplain** (2026-11-21): auto-clear **tidak** dibuatkan baris "System / Scheduler" — ikut ke baris `created_by` (clear, kategori, durasi, SLA) dan ditandai `(N auto)` di kolom Clear. Filter NOC pada `$cleared` = `cleared_by = user` **atau** (`auto_cleared` dan `created_by = user`). Activity log & kolom "Clear oleh" di list komplain tetap "System / Scheduler" — jangan mengubahnya.

### Gamas (2026-11-20)
- Gamas **bukan komplain**: datanya di tabel `daily_gamas`, menu `/gamas` (permission `gamas.view`), channel realtime `gamas`, section report `gamas`. Komplain selalu `complaint_type = individual`.
- Alur status gamas: **On-Progress ↔ Clear** saja (Clear wajib `action`); **tanpa** Menunggu Konfirmasi, auto-clear, kategori performa, dan komplain berulang.
- Carry-over & filter ODC/`__none__`/`mode=clear` di `GamasController` mengikuti pola `DailyEntryController` — kalau logika carry-over berubah, ubah keduanya.

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
- **2026-11-20** — Gamas dipisah dari Komplain: tabel sendiri `daily_gamas`, permission `gamas.view`, menu `/gamas`; komplain selalu individu. Alur gamas sederhana On-Progress/Clear.
- **2026-11-21** — Clear manual komplain wajib berusia ≥5 menit sejak `created_at` (presisi detik). Validasi backend di `DailyEntryController` (termasuk store dengan status Clear), countdown frontend memakai offset header `Date`; auto-clear tidak terkena aturan ini.

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
