import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatNumber(value: number): string {
  return new Intl.NumberFormat('id-ID').format(value)
}

export function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

export function formatMbps(mbps: number): string {
  if (mbps >= 1000) return `${(mbps / 1000).toFixed(1)} Gbps`
  return `${mbps.toFixed(0)} Mbps`
}

/**
 * Format durasi menit → teks mudah dibaca.
 * 45 → "45 mnt", 60 → "1 jam", 95 → "1 jam 35 mnt", 1500 → "1 hari 1 jam".
 */
export function formatDuration(minutes: number | null | undefined): string {
  if (minutes == null) return '—'
  const total = Math.max(0, Math.round(minutes))
  if (total < 60) return total < 1 ? '<1 mnt' : `${total} mnt`

  const days = Math.floor(total / 1440)
  const hours = Math.floor((total % 1440) / 60)
  const mins = total % 60

  const parts: string[] = []
  if (days > 0) parts.push(`${days} hari`)
  if (hours > 0) parts.push(`${hours} jam`)
  if (mins > 0 && days === 0) parts.push(`${mins} mnt`)
  return parts.join(' ')
}

export function timeAgo(date: Date | string): string {
  const now = new Date()
  const then = new Date(date)
  const seconds = Math.floor((now.getTime() - then.getTime()) / 1000)

  if (seconds < 60) return `${seconds} detik lalu`
  if (seconds < 3600) return `${Math.floor(seconds / 60)} menit lalu`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} jam lalu`
  return `${Math.floor(seconds / 86400)} hari lalu`
}
