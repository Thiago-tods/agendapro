/**
 * Calendar date math on local calendar dates ("YYYY-MM-DD") in the company's timezone.
 * Dates are handled as UTC midnights internally, so the browser timezone never shifts them.
 */

export type CalendarView = 'day' | 'week' | 'month'

function toUtc(date: string): Date {
  const [y, m, d] = date.split('-').map(Number)
  return new Date(Date.UTC(y ?? 1970, (m ?? 1) - 1, d ?? 1))
}

function fromUtc(value: Date): string {
  return value.toISOString().slice(0, 10)
}

export function isValidDate(value: unknown): value is string {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && fromUtc(toUtc(value)) === value
}

export function addDays(date: string, days: number): string {
  const value = toUtc(date)
  value.setUTCDate(value.getUTCDate() + days)
  return fromUtc(value)
}

export function addMonths(date: string, months: number): string {
  const value = toUtc(date)
  const day = value.getUTCDate()
  value.setUTCDate(1)
  value.setUTCMonth(value.getUTCMonth() + months)
  const lastDay = new Date(Date.UTC(value.getUTCFullYear(), value.getUTCMonth() + 1, 0)).getUTCDate()
  value.setUTCDate(Math.min(day, lastDay))
  return fromUtc(value)
}

/** 0 = Sunday ... 6 = Saturday. */
export function weekday(date: string): number {
  return toUtc(date).getUTCDay()
}

/** Weeks start on Monday (pt-BR business convention). */
export function startOfWeek(date: string): string {
  return addDays(date, -((weekday(date) + 6) % 7))
}

export function weekDates(date: string): string[] {
  const start = startOfWeek(date)
  return Array.from({ length: 7 }, (_, i) => addDays(start, i))
}

export function startOfMonth(date: string): string {
  return `${date.slice(0, 7)}-01`
}

/** 6 rows × 7 days covering the month, starting on Monday. */
export function monthMatrix(date: string): string[][] {
  const first = startOfWeek(startOfMonth(date))
  return Array.from({ length: 6 }, (_, row) => Array.from({ length: 7 }, (_, col) => addDays(first, row * 7 + col)))
}

export function isSameMonth(a: string, b: string): boolean {
  return a.slice(0, 7) === b.slice(0, 7)
}

/** Inclusive local date range that a view displays. */
export function rangeFor(view: CalendarView, date: string): { from: string; to: string } {
  if (view === 'day') return { from: date, to: date }
  if (view === 'week') {
    const days = weekDates(date)
    return { from: days[0] ?? date, to: days[6] ?? date }
  }
  const matrix = monthMatrix(date)
  return { from: matrix[0]?.[0] ?? date, to: matrix[5]?.[6] ?? date }
}

export function shift(view: CalendarView, date: string, direction: 1 | -1): string {
  if (view === 'day') return addDays(date, direction)
  if (view === 'week') return addDays(date, 7 * direction)
  return addMonths(date, direction)
}

const fmt = (options: Intl.DateTimeFormatOptions) => new Intl.DateTimeFormat('pt-BR', { ...options, timeZone: 'UTC' })

export function formatDate(date: string, options: Intl.DateTimeFormatOptions): string {
  return fmt(options).format(toUtc(date)).replace(/\./g, '')
}

/** Toolbar title for the current view. */
export function titleFor(view: CalendarView, date: string): string {
  if (view === 'day')
    return formatDate(date, {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
  if (view === 'month') return formatDate(date, { month: 'long', year: 'numeric' })

  const days = weekDates(date)
  const first = days[0] ?? date
  const last = days[6] ?? date
  const sameMonth = isSameMonth(first, last)
  const start = formatDate(first, sameMonth ? { day: 'numeric' } : { day: 'numeric', month: 'short' })
  return `${start} – ${formatDate(last, { day: 'numeric', month: 'short', year: 'numeric' })}`
}

export function toMinutes(time: string): number {
  const [h, m] = time.split(':').map(Number)
  return (h ?? 0) * 60 + (m ?? 0)
}

export function minutesLabel(minutes: number): string {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

/** Current local date and minute of day in a timezone. */
export function nowIn(timeZone: string, now: Date = new Date()): { date: string; minute: number } {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-CA', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
    })
      .formatToParts(now)
      .map((p) => [p.type, p.value]),
  )
  return {
    date: `${parts.year}-${parts.month}-${parts.day}`,
    minute: Number(parts.hour) * 60 + Number(parts.minute),
  }
}
