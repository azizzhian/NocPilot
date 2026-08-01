/** Hasil parse teks aktivasi yang di-paste ke form. */
export type ParsedActivation = {
  customer_name?: string
  package_name?: string
  odp_name?: string
  olt_name?: string
  port_onu?: string
  status?: string
}

function normalizeLabel(raw: string): string {
  return raw
    .toLowerCase()
    .replace(/\|/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

/**
 * Parse teks multi-baris seperti:
 * Nama Pelanggan: NOKA HERNA YULIANTI
 * ODP : PCT-1A.2
 * Kapasitas: 65 Mbps
 * OLT: Pacitan
 * Port | ONU: 1/1/1:75
 * Status: Clear
 */
export function parseActivationText(text: string): ParsedActivation {
  const result: ParsedActivation = {}
  if (!text?.trim()) return result

  for (const line of text.split(/\r?\n/)) {
    const trimmed = line.trim()
    if (!trimmed) continue
    const idx = trimmed.indexOf(':')
    if (idx <= 0) continue
    const label = normalizeLabel(trimmed.slice(0, idx))
    const value = trimmed.slice(idx + 1).trim()
    if (!value) continue

    if (
      label === 'nama pelanggan'
      || label === 'nama'
      || label === 'customer name'
      || label === 'pelanggan'
    ) {
      result.customer_name = value
    } else if (label === 'odp' || label === 'odp name') {
      result.odp_name = value
    } else if (
      label === 'kapasitas'
      || label === 'paket'
      || label === 'paket pelanggan'
      || label === 'package'
      || label === 'bandwidth'
    ) {
      result.package_name = value
    } else if (label === 'olt' || label === 'olt name') {
      result.olt_name = value
    } else if (
      label === 'port onu'
      || label === 'port'
      || label === 'onu'
      || label === 'port/onu'
    ) {
      result.port_onu = value
    } else if (label === 'status') {
      const s = value.toLowerCase()
      if (s.includes('clear')) result.status = 'Clear'
      else if (s.includes('progress') || s.includes('on-progress') || s.includes('on progress')) {
        result.status = 'On-Progress'
      } else {
        result.status = value
      }
    }
  }

  return result
}
