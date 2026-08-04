/** Tanggal hari ini (lokal browser) dalam format YYYY-MM-DD untuk input[type=date]. */
export function todayInput(date: Date = new Date()): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
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
