export const TICKET_FORM_STATUSES = ['On-Progress', 'Closed'] as const

const labels: Record<string, string> = {
  Closed: 'Close',
  Close: 'Close',
}

export function ticketStatusLabel(status?: string | null): string {
  if (!status) return '—'
  return labels[status] ?? status
}

export function isCloseStatus(status?: string | null): boolean {
  return status === 'Clear' || status === 'Closed' || status === 'Close'
}

export function ticketStatusVariant(status: string): 'success' | 'secondary' | 'info' | 'warning' {
  if (isCloseStatus(status) || status === 'Completed') return 'success'
  if (status === 'Cancelled') return 'secondary'
  if (['Assigned', 'On-Site', 'In-Progress'].includes(status)) return 'info'
  return 'warning'
}

export function ticketStatusChoices(current?: string | null): { value: string; label: string }[] {
  const values: string[] = [...TICKET_FORM_STATUSES]
  if (current && !values.includes(current)) values.push(current)
  return values.map((value) => ({ value, label: ticketStatusLabel(value) }))
}
