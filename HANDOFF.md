# Handoff

Catatan tugas yang **sedang berjalan**, dipakai bergantian oleh Cursor dan OpenCode.
Baca di awal sesi, perbarui setiap selesai satu langkah berarti. Dikosongkan oleh `/worklog` saat tugas tuntas
(ringkasannya dipindah ke `CHANGELOG.md`). Keputusan permanen ditulis di `AGENTS.md`, bukan di sini.

- **Terakhir diperbarui**: 2026-10-31 oleh OpenCode
- **Commit dasar**: `f7d4ca9` (Required OLT)

## Tugas sekarang

- **Jam Open / Jam Close di export + list** (permintaan user, 2026-11-13): semua export (Komplain, Report Ticket, Aktivasi, Dismantle) kini punya kolom **Jam Open** (`created_at`) dan **Jam Close** (`cleared_at`) format `H:i`. Tampilan list juga menampilkan jam: kartu komplain (`ComplaintDataList`), daftar aktivasi (`InputHarianView`), tabel ticket & dismantle. Helper baru `formatTimeId()` di `lib/date-input.ts`. `ReportTicketResource` kini mengekspos `cleared_at`.
- **Export Excel Dismantle** (permintaan user, 2026-11-13): tombol "Export Excel" di `/report/dismantle` mengunduh `dismantle-{from|all}-{to|all}.xlsx` lewat endpoint baru `GET /dismantles/export` (`DismantleController::export`). Kolom: Referensi, Lokasi, ID Pel, Nama Pelanggan, Status, Open Ticket, Close Ticket, Input oleh, Close oleh, Catatan. Filter mengikuti list: search, status (kecuali mode clear), location, odc_name (termasuk Tanpa ODC), from/to (open, atau closed_at bila `mode=clear`).
- **Export Excel Report Ticket 500** (2026-11-13): `ReportTicketController::export` memakai `$from`/`$to` untuk nama file tanpa dideklarasikan → ErrorException. Ditambahkan `$from`/`$to` dari request sebelum label file. `php -l` lolos.
- **Export Excel Aktivasi** (permintaan user, 2026-11-13): tombol "Excel" kini muncul juga di tab Aktivasi (halaman `/aktivasi`) dan mengunduh `aktivasi-{from}-{to}.xlsx` lewat endpoint baru `GET /daily-entry/export/activations` (`DailyEntryController::exportActivations`). Kolom: Tanggal, Nama Pelanggan, Paket, OLT, ODP, Port ONU, Status, Catatan, Input oleh, Clear oleh. Filter mengikuti list: `mode` (carry-over / clear), `search` nama, dan ODC via `olt_name`/`odp_name` (termasuk "Tanpa ODC" di PHP, sama seperti `listActivations`).
- (Sebelumnya) Perbaikan KPI card Aktivasi/CCTV di dashboard saat filter ODC dipilih: sebelumnya `summaryForRange` memaksa 0 karena kolom `odc_name` tidak ada. Kini aktivasi dipetakan via `olt_name` → `Olt.odc_id` / fallback `odp_name` → `Odp.odc_id`, CCTV via `customer_name` → `Customer.odc_id`, memakai helper `dailySummaryByOdc` (period-based open + kumulatif `open_all` + clear, ikut filter NOC).
- **On-Progress period-based** (performa/presentasi/lencana): hanya item yang dibuat dalam rentang filter (`report_date` antara from–to), bukan backlog kumulatif. **Kecuali KPI card atas** yang tetap menampilkan SEMUA item masih On-Progress (kumulatif `<= to`, field `*_open_all`).
- Role `noc` kehilangan permission `network.view` → menu JARINGAN (Router MikroTik, OLT, ONU, POP, ODC, ODP, Lokasi, Network Inventory) tak lagi tampil untuk role noc.
- Favorit sidebar kini **global & bisa dibuka semua role**: `Sidebar.vue` tidak lagi memfilter favorit dengan permission, dan router guard memperbolehkan navigasi ke path yang ada di daftar favorit walau tanpa permission (admin mengontrol lewat `/settings`).

## Sudah selesai

- `DashboardController::nocPerformance` baris 378 — `total` = Σ clear + Σ open; ikut memengaruhi `contribution_pct`, `avg_per_day`, ranking, dan grafik kontribusi.
- Label UI `DashboardView.vue` disesuaikan: "ranking by total (Clear + On-Progress)", header "Total (C+OP)", subtitle kontribusi, dan footer detail.
- On-Progress juga dimasukkan ke **KPI top performer** (`categoryKpis` → `$top(clear, open)`), **Lencana** (`specialistBadges` → Clear + On-Progress per kategori), **donut Persentase Penyelesaian** (`clearByType`), dan **stacked bar Performa NOC per Kategori** (`stackedByNoc`). Subtitle UI terkait diperbarui.
- **On-Progress period-based untuk performa/presentasi/lencana**: `nocPerformance` opens & `summaryForRange` `*_open` memakai `whereBetween('report_date', [from,to])` (ticket/dismantle via `opened_at`/`created_at` between). `php -l` + `composer test` lolos.
- **KPI card atas tetap kumulatif**: `summaryForRange` menambah field `*_open_all` (masih `<= to`, jwt helper `countOpensUpTo`, `countDismantleOpensUpTo`); `categoryKpis['open']` memakai `*_open_all`. `php -l` + `composer test` lolos.
- `php -l` + `npm run build:web` lolos.
- `network.view` dihapus dari `role_defaults['noc']` (`config/permissions.php`) + migration `2026_10_09_000000_remove_network_view_from_noc_role.php` (revoke dari role `noc` di DB, down = give back). Migration sudah dijalankan & terverifikasi (`has network.view: false`).
- Favorit global: `Sidebar.vue:favorites` tidak difilter `filterByAccess`; `router/index.ts:beforeEach` meng-await `fetchSidebarFavorites()` lalu mengizinkan `to.path` yang ada di `sidebarFavoritePaths` walau user tidak punya permission halaman tsb. `npm run build:web` lolos.
- KPI card Aktivasi/CCTV saat filter ODC: `summaryForRange` tidak lagi memaksa 0; helper `dailySummaryByOdc` memetakan aktivasi via OLT/ODP dan CCTV via nama pelanggan, menghitung input period, `open` period, `open_all` kumulatif, dan clear via `cleared_at`. Angka KPI kini sama dengan `odc_stats` (contoh SOBRAH Jul–Sep: activations_clear 2). `php -l` + `composer test` lolos.

## Langkah berikutnya

- Verifikasi manual: login user `noc` → favorit OLT (yang dicentang admin di `/settings`) muncul & bisa dibuka; section JARINGAN tetap tidak muncul.
- Verifikasi manual dashboard: On-Progress di performa/donut/stacked/lencana hanya dari item periode tsb.
- Verifikasi manual KPI Aktivasi/CCTV dengan filter ODC (mis. SOBRAH, Jul–Sep) → Clear/On-Progress harus sama dengan tabel Statistik ODC, bukan 0.
- (Opsional) Terapkan period-based open yang sama ke `odcPerformance` / `stacked_by_odc` (masih `<= to`) bila statistik ODC harus konsisten dengan performa-per-user.
- (Perlu keputusan) Karena API jaringan tidak ber-middleware permission, noc yang membuka favorit OLT otomatis bisa **tambah/edit/hapus** data OLT. Bila ingin read-only, perlu gate tambahan (`network.manage`) di UI.

## File disentuh

- `apps/backend/app/Http/Controllers/Api/DailyEntryController.php` (tambah `exportActivations`)
- `apps/backend/app/Http/Controllers/Api/ReportTicketController.php` (deklarasi `$from`/`$to` di `export`)
- `apps/backend/app/Http/Controllers/Api/DismantleController.php` (tambah `export`)
- `apps/backend/routes/api.php` (route `dismantles/export`)
- `apps/frontend/src/services/api.ts` (tambah `dismantleApi.exportExcel`)
- `apps/frontend/src/views/DismantleView.vue` (tombol Export Excel)
- `apps/frontend/src/lib/date-input.ts` (helper `formatTimeId`)
- `apps/frontend/src/components/daily/ComplaintDataList.vue` (jam open/close)
- `apps/frontend/src/views/ReportTicketView.vue` (jam open/close)
- `apps/backend/app/Http/Resources/ReportTicketResource.php` (`cleared_at`)
- `apps/backend/app/Http/Controllers/Api/DailyEntryController.php`, `ReportTicketController.php`, `DismantleController.php` (kolom Jam Open/Close)
- `apps/backend/routes/api.php` (route `daily-entry/export/activations`)
- `apps/frontend/src/services/api.ts` (tambah `dailyEntryApi.exportActivations`)
- `apps/frontend/src/views/InputHarianView.vue` (tombol Excel untuk tab aktivasi)

- `apps/backend/app/Http/Controllers/Api/DashboardController.php`
- `apps/frontend/src/views/DashboardView.vue`
- `apps/backend/config/permissions.php`
- `apps/backend/database/migrations/2026_10_09_000000_remove_network_view_from_noc_role.php`
- `apps/frontend/src/components/layout/Sidebar.vue`
- `apps/frontend/src/router/index.ts`

## Catatan & jebakan

- Open terbagi dua: **period-based** (`*_open`, untuk performa/presentasi/lencana — report_date antara dari–sampai, belum Clear) dan **kumulatif** (`*_open_all`, khusus KPI card atas — `report_date <= to`, sudah termasuk backlog). Dibedakan lewat nama field; jangan mencampur keduanya.
- Clear selalu period-based via `cleared_at`/`cleared_by`. `total` performa = beban kerja masuk periode (open period) + hasil clear periode.
- `odcPerformance`/`odc_stats` (tabel Statistik ODC) **masih clear-only total dengan open kumulatif** (`<= to`) — sengaja dibiarkan, konsisten dengan carry-over list. Kalau diubah, pastikan sejalan modul lain.
- API jaringan (olt/odc/odp/router/dll.) **tidak** digate permission middleware — menu section di-filter permission, tapi akses langsung + edit hanya bisa dibatasi lewat UI/router atau middleware baru.
- Perubahan permission default hanya re-run saat `db:seed` atau migration baru; edit UI di `/roles` tetap menang (tidak di-reset).
- Migration `up` idempotent (revoke yang sudah tidak ada = no-op); `down` mengembalikan `network.view`. Target hanya role `noc`.
