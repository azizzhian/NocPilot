# Changelog

Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning mengikuti [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- **Komplain clear-like di performa Dashboard** (2026-11-23): `Menunggu Konfirmasi` yang sudah lewat 30 menit sejak `handled_at` dan komplain yang dieskalasikan ke tiket (`escalated_at`) dihitung **Clear** di stacked bar Performa NOC per Kategori, leaderboard, detail performa, KPI Komplain, Statistik ODC, dan widget Performa Komplain; yang belum 30 menit tetap open (tanpa dobel hitung). Helper row-aware `DashboardController::isComplaintPerformanceClear()` + `complaintClearLikeRows()`/atribusi Clear. Widget menyimpan `clear_final` terpisah agar SLA/durasi/kategori tetap ketat dari Clear final saja.
- **Eskalasi tiket hanya untuk pembuat komplain** (2026-11-23): `DailyComplaint::isOwnedBy()` jadi sumber tunggal aturan ownership (dipakai ulang oleh `DailyEntryController`); `POST /report-tickets` dengan `complaint_id` menolak 403 untuk non-pembuat (admin tidak dikecualikan). Tombol "Buat Tiket" di daftar komplain dinonaktifkan dengan ikon gembok + tooltip untuk non-pembuat.
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
- **Incident Management Dashboard GAMAS + Timeline Progress** (2026-11-21): kolom `started_at`/`ended_at`/`duration_minutes` pada riwayat update (`daily_gamas_updates`, migration additive, riwayat lama tetap NULL → "Durasi belum tersedia"); popup "Tambah Update" direnovasi dengan rentang waktu & riwayat timeline terbaru-di-atas; tabel GAMAS 9 kolom dengan "Durasi Penanganan" (usia gangguan) & "Update Terakhir"; KPI "Total Durasi Update"; format durasi lengkap (`37 menit`, `3 jam 45 menit`); validasi 422 untuk waktu depan/sebelum input/tumpang tindih. Endpoint & mekanisme lama (Action wajib, carry-over, ODC, realtime, Excel, Generate Report) tidak berubah.
- **Minimum umur 5 menit sebelum Clear komplain** (2026-11-21): transisi bukan Clear → Clear ditolak 422 bila `created_at` belum berusia 300 detik (presisi detik: 4m59s ditolak, tepat 5m diizinkan; `created_at` kosong/tidak valid juga ditolak). Backend memvalidasi di PATCH status, PUT complaint, dan menolak input komplain baru dengan status Clear (`errors.status`); prioritas 403 pembuat → 422 Action → 422 umur. Frontend: pill Clear & opsi Clear di form komplain dinonaktifkan dengan countdown mm:ss yang update tiap detik (form create: Clear selalu disabled), sumber waktu dari offset header `Date` respons API. Auto-clear 30 menit, atribusi clear, handling_minutes, dan activity log Manual vs System / Scheduler tidak berubah.
- **Eskalasi Komplain → Ticket — satu modul, dua sumber** (2026-11-23):
  - Satu endpoint `POST /report-tickets` dan satu form `TicketFormModal.vue` untuk tiket langsung (`complaint_id = NULL`) maupun tiket dari komplain (prefill pelanggan/telepon/alamat/ODC/problem, riwayat update komplain, alasan eskalasi wajib)
  - Migration: kolom eskalasi/workflow/assignment di `report_tickets`, tabel riwayat kerja `report_ticket_updates`, relasi `daily_complaints.ticket_id` + `escalated_at` (FK nullOnDelete, snapshot arsip tetap ada saat komplain dihapus)
  - `App\Support\TicketStatus`: workflow Open/Assigned/On-Site/In-Progress/Completed/Cancelled + status lama On-Progress/Clear/Closed masih dikenal; assignment teknisi, start kerja, update hasil/material/foto, PATCH status
  - Tombol **Buat Tiket / Lihat Tiket** + badge "Dialihkan ke Tiket" di daftar komplain (termasuk carry-over); panel **SUMBER TIKET: KOMPLAIN** & **Lihat Komplain Asal** di detail tiket; riwayat kerja tiket terpisah dari riwayat komplain
  - Tiket aktif melindungi komplain dari auto-clear; tiket selesai → komplain Menunggu Konfirmasi dengan timer 30 menit dimulai ulang; tiket dibatalkan → eskalasi dilepas & boleh dibuat ulang
  - Filter sumber tiket di tabel/export/generate report; dashboard ringkasan sumber & status tiket; performa komplain menampilkan jumlah yang dieskalasikan terpisah dari Clear
- Export Excel Dismantle (`GET /dismantles/export`), Aktivasi (`GET /daily-entry/export/activations`), Report Ticket perbaikan `$from`/`$to`, dan kolom **Jam Open/Jam Close** (`H:i`) di semua export + list (2026-11-13)

### Changed
- Angka Clear performa Dashboard untuk `Menunggu Konfirmasi` baru naik setelah 30 menit sejak `handled_at` (selaras dengan auto-clear); sebelum itu tetap Open. Data lama tanpa `handled_at` tetap Clear. Helper status-only `ReportStatus::isPerformanceClear()`/`performanceClearStatuses()` dihapus demi helper baris yang sadar waktu di `DashboardController` (2026-11-23)
- Status Report Ticket disederhanakan untuk tiket baru: form hanya On-Progress (default) dan Close (disimpan sebagai `Closed`); `Clear` historis tetap tampil "Clear", `Closed` tampil "Close"; status workflow lama tetap dikenal & bisa dikelola lewat filter "Lainnya"; KPI Aktif = On-Progress, Selesai = Clear+Closed; filter Close mencakup Clear+Closed; Close wajib Action dan tetap sinkron ke komplain Menunggu Konfirmasi (2026-11-23)
- Tampilan Report Ticket diringkas: 4 KPI utama (Total, Aktif, Dari Komplain, Selesai) + satu baris badge status berjumlah yang bisa diklik sebagai filter; KPI & tabel memakai filter aktif yang sama, pencarian menerima nomor tiket (2026-11-23)
- Route `/report-tickets` kini di-gate middleware `permission:ticket.view` (tulis: `ticket.manage`); sebelumnya hanya frontend. Status tiket lama `On-Progress|Clear|Closed` tetap didukung sebagai alias dari workflow baru (2026-11-23)
- Performa Komplain: auto-clear tidak lagi tampil sebagai baris "System / Scheduler" — digabung ke baris pembuat (`created_by`) dan ditandai `(N auto)` di kolom Clear; filter NOC ikut menyertakan auto-clear milik petugas tersebut. Kartu ringkasan, SLA sistem, dan activity log "System / Scheduler" tidak berubah (2026-11-21)
- Input Aktivasi: `olt_name` wajib diisi agar bisa dipetakan ke ODC (2026-09-22)
- Optimasi performa perhitungan statistik ODC (2026-09-21)
- Durasi komplain kini format mudah dibaca: `≥60 menit` → jam, `≥24 jam` → hari, sisa satuan ikut ditulis (mis. "1 jam 35 mnt", "2 hari 3 jam") — berlaku di kartu komplain, dashboard, riwayat, dan export Excel (header "Durasi (mnt)" → "Durasi") (2026-11-20)
- Form komplain di Input Harian tidak lagi punya toggle Individu/Gamas — komplain selalu individu; data gamas diinput lewat menu Gamas (2026-11-20)
- Tombol Clear GAMAS: Action wajib diisi lewat popup khusus "Action Wajib Diisi" (bukan form Edit), disimpan dalam satu request `PUT /gamas/{id}` dengan mekanisme close GAMAS yang sudah ada (2026-11-20)
- Export detail komplain kini memakai semantik periode daftar (termasuk carry-over & `mode=clear`) agar angka KPI, tabel, dan export konsisten (2026-11-20)

### Fixed
- `GET /report-tickets/stats` tidak lagi menghasilkan 422 akibat SQL 1055 `ONLY_FULL_GROUP_BY` (`GROUP BY status` dibarengi `ORDER BY id`); halaman Report Ticket tidak lagi menampilkan KPI/table kosong saat stats gagal — list & stats dimuat terpisah dan error ditampilkan eksplisit (2026-11-23)
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
