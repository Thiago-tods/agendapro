/** Turns blank form strings into null, as the API expects for optional fields. */
export function nullable(value: string): string | null {
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

/** "(11) 98765-4321" for display; the API stores digits only. */
export function formatPhone(digits: string | null | undefined): string {
  if (!digits) return ''
  const d = digits.replace(/\D/g, '')
  if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`
  if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`
  return digits
}

/** Money typed as "120", "120,5" or "1.200,00" → cents. Null when not a number. */
export function parseMoney(input: string): number | null {
  const normalized = input
    .replace(/[^\d,.]/g, '')
    .replace(/\./g, '')
    .replace(',', '.')
  if (normalized === '') return null
  const value = Number(normalized)
  return Number.isFinite(value) ? Math.round(value * 100) : null
}

export function centsToInput(cents: number): string {
  return (cents / 100).toFixed(2).replace('.', ',')
}

/** "45 min", "1h", "1h30". */
export function formatDuration(minutes: number): string {
  if (minutes < 60) return `${minutes} min`
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return m === 0 ? `${h}h` : `${h}h${String(m).padStart(2, '0')}`
}

/** Fixed palette for professionals and services (consistent calendar colors). */
export const COLOR_PALETTE: { value: string; name: string }[] = [
  { value: '#8B5CF6', name: 'Roxo' },
  { value: '#6366F1', name: 'Índigo' },
  { value: '#3B82F6', name: 'Azul' },
  { value: '#06B6D4', name: 'Ciano' },
  { value: '#14B8A6', name: 'Turquesa' },
  { value: '#22C55E', name: 'Verde' },
  { value: '#EAB308', name: 'Amarelo' },
  { value: '#F97316', name: 'Laranja' },
  { value: '#EF4444', name: 'Vermelho' },
  { value: '#EC4899', name: 'Rosa' },
]

export const WEEKDAYS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'] as const

/** First validation message of any field (e.g. "periods.3.end_time"), or the general message. */
export function firstError(
  error: { message: string; fieldErrors: Record<string, string[]> } | null,
): string | undefined {
  if (!error) return undefined
  return Object.values(error.fieldErrors)[0]?.[0] ?? error.message
}
