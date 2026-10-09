---
description: Perbarui HANDOFF.md dengan status sesi ini (sebelum pindah ke Cursor)
---

Sesi ini akan dilanjutkan oleh agent lain (biasanya Cursor), yang tidak bisa melihat riwayat chat ini.

Kondisi saat ini:
!`git log --oneline -5`

!`git status --short`

!`git diff --stat`

Perbarui @HANDOFF.md berdasarkan pekerjaan di sesi ini dan kondisi git di atas:
- **Terakhir diperbarui**: tanggal hari ini, "oleh OpenCode"
- **Commit dasar**: hash commit terakhir
- **Tugas sekarang**: tujuan tugas dalam 1-3 kalimat
- **Sudah selesai**: langkah yang sudah tuntas
- **Langkah berikutnya**: langkah konkret yang bisa langsung dikerjakan agent berikutnya, berurutan
- **File disentuh**: semua file yang diubah/dibuat beserta alasan singkatnya
- **Catatan & jebakan**: hal yang tidak terlihat dari kode (asumsi, bug yang ditemukan, pendekatan yang sudah dicoba dan gagal)

Aturan:
- Tulis supaya agent lain bisa melanjutkan tanpa bertanya.
- Keputusan permanen (arsitektur/aturan bisnis) tulis juga di AGENTS.md bagian 7.
- Hanya ubah HANDOFF.md (dan AGENTS.md bila perlu). Jangan commit.
- Setelah selesai, tampilkan ringkasan isi HANDOFF.md yang baru.

Catatan tambahan dari saya: $ARGUMENTS
