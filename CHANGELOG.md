# Changelog

Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning mengikuti [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Instalasi VPS otomatis: `scripts/install.sh`, `scripts/install.env.example`, workflow GitHub **Install**
- Alur deploy: `.gitignore` root, `scripts/deploy.sh`, `scripts/backup-db.sh`, `DEPLOY.md`, GitHub Actions Deploy
- Carry-over item On-Progress di Input Harian (badge "Open dari …")

### Fixed
- `install.sh`: `composer install` sebelum `artisan key:generate` (vendor kosong di VPS baru)

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
