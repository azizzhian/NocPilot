# Handoff

Catatan tugas yang **sedang berjalan**, dipakai bergantian oleh Cursor dan OpenCode.
Baca di awal sesi, perbarui setiap selesai satu langkah berarti. Dikosongkan oleh `/worklog` saat tugas tuntas
(ringkasannya dipindah ke `CHANGELOG.md`). Keputusan permanen ditulis di `AGENTS.md`, bukan di sini.

- **Terakhir diperbarui**: 2026-10-09 oleh OpenCode
- **Commit dasar**: `f7d4ca9` (Required OLT)

## Tugas sekarang

- Performa NOC: `total` kini menghitung Clear **dan** On-Progress (Komplain, Aktivasi, Ticket, Dismantle, CCTV, Update NOC).
- Role `noc` kehilangan permission `network.view` → menu JARINGAN (Router MikroTik, OLT, ONU, POP, ODC, ODP, Lokasi, Network Inventory) tak lagi tampil untuk role noc.
- Favorit sidebar kini **global & bisa dibuka semua role**: `Sidebar.vue` tidak lagi memfilter favorit dengan permission, dan router guard memperbolehkan navigasi ke path yang ada di daftar favorit walau tanpa permission (admin mengontrol lewat `/settings`).

## Sudah selesai

- `DashboardController::nocPerformance` baris 378 — `total` = Σ clear + Σ open; ikut memengaruhi `contribution_pct`, `avg_per_day`, ranking, dan grafik kontribusi.
- Label UI `DashboardView.vue` disesuaikan: "ranking by total (Clear + On-Progress)", header "Total (C+OP)", subtitle kontribusi, dan footer detail.
- On-Progress juga dimasukkan ke **KPI top performer** (`categoryKpis` → `$top(clear, open)`), **Lencana** (`specialistBadges` → Clear + On-Progress per kategori), **donut Persentase Penyelesaian** (`clearByType`), dan **stacked bar Performa NOC per Kategori** (`stackedByNoc`). Subtitle UI terkait diperbarui.
- `php -l` + `npm run build:web` lolos.
- `network.view` dihapus dari `role_defaults['noc']` (`config/permissions.php`) + migration `2026_10_09_000000_remove_network_view_from_noc_role.php` (revoke dari role `noc` di DB, down = give back). Migration sudah dijalankan & terverifikasi (`has network.view: false`).
- Favorit global: `Sidebar.vue:favorites` tidak difilter `filterByAccess`; `router/index.ts:beforeEach` meng-await `fetchSidebarFavorites()` lalu mengizinkan `to.path` yang ada di `sidebarFavoritePaths` walau user tidak punya permission halaman tsb. `npm run build:web` lolos.

## Langkah berikutnya

- Verifikasi manual: login user `noc` → favorit OLT (yang dicentang admin di `/settings`) muncul & bisa dibuka; section JARINGAN tetap tidak muncul.
- (Opsional) Terapkan hal yang sama ke `odcPerformance` / `stacked_by_odc` (`total` masih clear-only) bila statistik per-ODC harus konsisten.
- (Perlu keputusan) Karena API jaringan tidak ber-middleware permission, noc yang membuka favorit OLT otomatis bisa **tambah/edit/hapus** data OLT. Bila ingin read-only, perlu gate tambahan (`network.manage`) di UI.

## File disentuh

- `apps/backend/app/Http/Controllers/Api/DashboardController.php`
- `apps/frontend/src/views/DashboardView.vue`
- `apps/backend/config/permissions.php`
- `apps/backend/database/migrations/2026_10_09_000000_remove_network_view_from_noc_role.php`
- `apps/frontend/src/components/layout/Sidebar.vue`
- `apps/frontend/src/router/index.ts`

## Catatan & jebakan

- Open adalah backlog kumulatif (report_date ≤ akhir periode, belum Clear) diatribusikan ke `created_by`/`assigned_to`; Clear diatribusikan ke `cleared_by`. Jadi `total` = beban kerja + hasil clear pada periode.
- ODC belum diubah, jadi header "Total Clear" di tabel Statistik ODC, tooltip/filter ODC, dan `stacked_by_odc` masih benar/clear-only.
- API jaringan (olt/odc/odp/router/dll.) **tidak** digate permission middleware — menu section di-filter permission, tapi akses langsung + edit hanya bisa dibatasi lewat UI/router atau middleware baru.
- Perubahan permission default hanya re-run saat `db:seed` atau migration baru; edit UI di `/roles` tetap menang (tidak di-reset).
- Migration `up` idempotent (revoke yang sudah tidak ada = no-op); `down` mengembalikan `network.view`. Target hanya role `noc`.
