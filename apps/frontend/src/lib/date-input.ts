/** Tanggal hari ini (lokal browser) dalam format YYYY-MM-DD untuk input[type=date]. */
export function todayInput(date: Date = new Date()): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/** YYYY-MM-DD dari objek Date (lokal). */
export function toDateKey(date: Date): string {
  return todayInput(date)
}

/** Parse YYYY-MM-DD ke Date lokal (hindari drift UTC). */
export function parseDateInput(value: string): Date {
  const raw = toDateInput(value)
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    const [y, m, d] = raw.split('-').map(Number)
    return new Date(y, m - 1, d)
  }
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? new Date() : parsed
}

const MONTHS_SHORT_ID = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

/** Format tampilan: "08 Agu 2026" */
export function formatDateId(value: string): string {
  const d = parseDateInput(value)
  const day = String(d.getDate()).padStart(2, '0')
  return `${day} ${MONTHS_SHORT_ID[d.getMonth()]} ${d.getFullYear()}`
}

/** Konversi nilai tanggal API ke format YYYY-MM-DD untuk input[type=date]. */
export function toDateInput(value: string | null | undefined): string {
  if (!value) return ''

  const raw = String(value).trim()

  // Sudah format date-only
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    return raw
  }

  // ISO datetime — pakai tanggal lokal browser, jangan slice UTC (bisa mundur 1 hari)
  if (/^\d{4}-\d{2}-\d{2}/.test(raw)) {
    const parsed = new Date(raw)
    if (!Number.isNaN(parsed.getTime())) {
      return todayInput(parsed)
    }
  }

  return raw.slice(0, 10)
}
