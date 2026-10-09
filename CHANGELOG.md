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

### Changed
- Input Aktivasi: `olt_name` wajib diisi agar bisa dipetakan ke ODC (2026-09-22)
- Optimasi performa perhitungan statistik ODC (2026-09-21)

### Fixed
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
