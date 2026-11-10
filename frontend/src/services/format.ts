/**
 * pt-BR formatting helpers. Instants from the API are UTC; display always uses the
 * company's timezone, never the browser's.
 */

const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
const percent = new Intl.NumberFormat('pt-BR', { style: 'percent', maximumFractionDigits: 0 })
const integer = new Intl.NumberFormat('pt-BR')
const relative = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' })

export function formatCurrency(cents: number): string {
  return currency.format(cents / 100)
}

export function formatPercent(rate: number): string {
  return percent.format(rate)
}

export function formatNumber(value: number): string {
  return integer.format(value)
}

/** "sexta-feira, 13 de novembro" for an ISO instant, in the given timezone. */
export function formatLongDate(iso: string, timeZone: string): string {
  return new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', timeZone }).format(
    new Date(iso),
  )
}

/** "13 nov" for a local calendar date (YYYY-MM-DD), independent of timezones. */
export function formatShortDate(date: string): string {
  const [year, month, day] = date.split('-').map(Number)
  return new Intl.DateTimeFormat('pt-BR', { day: 'numeric', month: 'short', timeZone: 'UTC' })
    .format(new Date(Date.UTC(year ?? 1970, (month ?? 1) - 1, day ?? 1)))
    .replace('.', '')
    .replace(' de ', ' ')
}

/** Today's date (YYYY-MM-DD) in the given timezone. */
export function todayIn(timeZone: string, now: Date = new Date()): string {
  // en-CA formats as YYYY-MM-DD.
  return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(now)
}

/** "agora há pouco", "há 5 minutos", "ontem"… */
export function formatRelative(iso: string, now: Date = new Date()): string {
  const seconds = Math.round((new Date(iso).getTime() - now.getTime()) / 1000)
  const abs = Math.abs(seconds)

  if (abs < 60) return 'agora há pouco'
  if (abs < 3600) return relative.format(Math.round(seconds / 60), 'minute')
  if (abs < 86400) return relative.format(Math.round(seconds / 3600), 'hour')
  return relative.format(Math.round(seconds / 86400), 'day')
}

export interface Change {
  /** Relative change, e.g. 0.12 for +12%. Null when there is no baseline to compare with. */
  ratio: number | null
  direction: 'up' | 'down' | 'flat'
}

export function compare(current: number, previous: number): Change {
  if (previous === 0) return { ratio: null, direction: current > 0 ? 'up' : 'flat' }
  const ratio = (current - previous) / previous
  return { ratio, direction: Math.abs(ratio) < 0.005 ? 'flat' : ratio > 0 ? 'up' : 'down' }
}
