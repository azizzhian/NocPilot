# Changelog

Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning mengikuti [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Instalasi VPS otomatis: `scripts/install.sh`, `scripts/install.env.example`, workflow GitHub **Install**
- Alur deploy: `.gitignore` root, `scripts/deploy.sh`, `scripts/backup-db.sh`, `DEPLOY.md`, GitHub Actions Deploy
- Carry-over item On-Progress di Input Harian (badge "Open dari …")
- Generate report per section (`POST /reports/generate/section`, `SectionReportModal`) + `DateRangePicker` (2026-08-08)
- Dashboard: statistik periode (hari/minggu/bulan/tahun/custom), top performer & spesialis NOC (2026-08-09 s/d 08-11)
- Notifikasi update aplikasi: tabel `app_updates`, command `app:record-deploy` dipanggil `deploy.sh`, dropdown di Header (2026-08-18)
- Menu Dismantle dipindah ke Report + import Excel Dismantle (`DismantleImportService`) (2026-08-18)
- Dismantle: kolom `cleared_by`/`cleared_at` (siapa yang clear) (2026-08-27)
- Dashboard per ODC (2026-09-19)
- Master **Lokasi** (`locations`, alias lokasi → ODC) dan OLT punya `odc_id` (2026-09-19)
- Filter ODC + opsi "Tanpa ODC" di Aktivasi, CCTV, Komplain, Update NOC, Dismantle, Report Ticket (2026-09-21 s/d 09-22)
- Konteks bersama Cursor & OpenCode: `AGENTS.md`, `HANDOFF.md`, `opencode.json` (permission: blokir `git push`, `db:seed`, `migrate:fresh`, dll.), command OpenCode `/lanjut`, `/handoff`, `/test`, `/worklog` (2026-09-29)
- **Alur komplain baru + performa penanganan** (2026-11-19):
  - Status **"Menunggu Konfirmasi"** (On-Progress → Tandai Selesai → Clear manual / **auto-clear 30 menit** via `complaint:auto-clear` tiap menit, timer dari `handled_at`)
  - Kategori performa tersimpan saat Clear: `handling_minutes` (input → clear, termasuk jeda konfirmasi) → Cepat 1–30 / Lambat 31–40 / Cukup Lambat 41–60 / Sangat Lambat >60 menit
  - Komplain berulang otomatis: relasi `parent_complaint_id` + `complaint_group_id` (pelanggan/lokasi sama dalam 24 jam)
  - Dashboard widget **Performa Komplain** (total per petugas, sedang ditangani, menunggu konfirmasi, rata-rata durasi, jumlah 4 kategori, auto-clear) — permission `dashboard.widget.complaint_performance`
  - Kartu komplain: tombol "Tandai Selesai", countdown auto-clear, chip kategori performa + durasi, chip "Berulang"; KPI `/komplain` tambah kartu "Menunggu"; export komplain tambah kolom Durasi, Kategori Performa, Komplain Berulang
- **Modul Gamas terpisah** dari Komplain (2026-11-20):
  - Tabel baru `daily_gamas` + migration pemindahan data gamas dari `daily_complaints` (status `Menunggu Konfirmasi` → `On-Progress`)
  - Permission `gamas.view` (administrator, manager, noc, teknisi) + widget permission `dashboard.widget.gamas`
  - Route/menu **`/gamas`** → `GamasView.vue`: KPI, rekap per jenis, filter ODC/Tanpa ODC, tabs status, form (jenis ODP/Upstream/OLT/Lainnya), Tandai Clear, hapus, export Excel, generate report section Gamas, polling realtime channel `gamas`
  - Backend: `GamasController`, `GamasEventsController`, `GamasStatsService`, model `DailyGamas`, route prefix `/gamas`, nav badge sidebar
  - Dashboard widget **Statistik Gamas** (total/open/clear + per jenis + top ODC)
  - Report: section **Gamas** baru di generate per section (template "Gangguan Massal (Gamas)")
- **Komplain: filter NOC/Shift/Status, KPI SLA 60 menit & export rekap** (2026-11-20): dropdown NOC (dari `/technicians`), Shift (1–3), Status; KPI "Persen Komplain (≤ 60 menit)" = Clear ≤60 menit / total Clear × 100% (dari `handling_minutes` valid saja; data NULL tidak dihitung, tanpa mengubah data historis); export detail mengikuti filter aktif + endpoint `GET /daily-entry/export/complaints-recap` (rekap NOC × Shift: Total, Clear, On-Progress, Clear ≤60, Clear >60, Persentase SLA).
- **Pembatasan perubahan status Komplain hanya untuk pembuat** (2026-11-20): 403 bila user login bukan `created_by` saat transisi status apa pun (On-Progress / Menunggu Konfirmasi / Clear), berlaku di `PATCH /daily-entry/{type}/{id}/status` dan `PUT /daily-entry/complaint/{id}` (admin tidak dikecualikan). Semua pill status (termasuk carry-over) & dropdown status form Edit dikunci dengan ikon gembok + tooltip; menu Edit/Hapus Komplain juga dikunci untuk non-pembuat. Auto-clear tetap jalan sebagai aksi sistem: `cleared_by = null`, audit "System / Scheduler" (`meta.source = scheduler`), tampilan "Clear oleh" & dashboard menampilkan System / Scheduler.
- **KPI Persen Komplain (SLA ≤ 60 menit) di Dashboard** (2026-11-20): kartu baru di bagian Performa Komplain (ikon checklist, `X% (within/clear)`, warna hijau ≥90 / oranye 70–89 / merah <70) + dropdown Shift yang memengaruhi section tersebut. Tabel per petugas mendapat kolom Persen Komplain dengan aturan & warna yang sama. Helper `App\Support\ComplaintSla` menyatukan aturan SLA ketat (hanya `handling_minutes` valid; NULL tidak dihitung) untuk Dashboard, halaman Komplain, dan rekap.
- **Update Progress GAMAS** (2026-11-20): tabel riwayat terpisah `daily_gamas_updates` + endpoint `GET/POST /gamas/{id}/updates`; tombol Update di tiap baris GAMAS → popup "Tambah Update" (identitas GAMAS, textarea wajib Update/Progress, riwayat terbaru-dulu berisi petugas & tanggal/jam, toast sukses/error). Catatan baru tidak menimpa `action` dan tidak mengubah status.
- Export Excel Dismantle (`GET /dismantles/export`), Aktivasi (`GET /daily-entry/export/activations`), Report Ticket perbaikan `$from`/`$to`, dan kolom **Jam Open/Jam Close** (`H:i`) di semua export + list (2026-11-13)

### Changed
- Input Aktivasi: `olt_name` wajib diisi agar bisa dipetakan ke ODC (2026-09-22)
- Optimasi performa perhitungan statistik ODC (2026-09-21)
- Durasi komplain kini format mudah dibaca: `≥60 menit` → jam, `≥24 jam` → hari, sisa satuan ikut ditulis (mis. "1 jam 35 mnt", "2 hari 3 jam") — berlaku di kartu komplain, dashboard, riwayat, dan export Excel (header "Durasi (mnt)" → "Durasi") (2026-11-20)
- Form komplain di Input Harian tidak lagi punya toggle Individu/Gamas — komplain selalu individu; data gamas diinput lewat menu Gamas (2026-11-20)
- Tombol Clear GAMAS: Action wajib diisi lewat popup khusus "Action Wajib Diisi" (bukan form Edit), disimpan dalam satu request `PUT /gamas/{id}` dengan mekanisme close GAMAS yang sudah ada (2026-11-20)
- Export detail komplain kini memakai semantik periode daftar (termasuk carry-over & `mode=clear`) agar angka KPI, tabel, dan export konsisten (2026-11-20)

### Fixed
- Toast tidak meninggalkan kotak kosong setelah auto-dismiss dan timer di-reset untuk setiap pesan baru (2026-11-20)
- `install.sh`: `composer install` sebelum `artisan key:generate` (vendor kosong di VPS baru)
- Filter lokasi Dismantle (2026-08-24)
- Generate section Update NOC (2026-09-08)
- Sesi login sering putus & captcha login; sinkron login/logout antar tab (2026-09-14)
- Statistik & filter "Tanpa ODC" tidak konsisten antara list dan dashboard (2026-09-21)

## [1.0.0] - 2026-07-31

### Added
- Login username/password + Telegram Login Widget
- Input harian: aktivasi, CCTV, dismantle, komplain (individu/gamas), update NOC
- Riwayat komplain + complaint score
- Role & permission dapat dikustomisasi
- Dashboard widget berdasar permission
- Audit log & activity log
- Monitoring jaringan (collector via queue/scheduler)
- Generate & history report harian
- Edit profil pengguna
