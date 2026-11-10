import { describe, expect, it } from 'vitest'
import { compare, formatCurrency, formatPercent, formatRelative, formatShortDate, todayIn } from '@/services/format'

describe('format helpers', () => {
  it('formats money from integer cents in BRL', () => {
    expect(formatCurrency(12000).replace(/\s/g, ' ')).toBe('R$ 120,00')
    expect(formatCurrency(4990).replace(/\s/g, ' ')).toBe('R$ 49,90')
  })

  it('formats rates as whole percentages', () => {
    expect(formatPercent(0.6667)).toMatch(/^67\s?%$/)
  })

  it('formats local calendar dates without timezone drift', () => {
    expect(formatShortDate('2026-11-09')).toBe('9 nov')
    expect(formatShortDate('2026-01-01')).toBe('1 jan')
  })

  it('computes today in the company timezone, not the browser one', () => {
    // 02:00 UTC on the 10th is still the 9th in São Paulo (UTC-3).
    const instant = new Date('2026-11-10T02:00:00Z')
    expect(todayIn('America/Sao_Paulo', instant)).toBe('2026-11-09')
    expect(todayIn('UTC', instant)).toBe('2026-11-10')
  })

  it('describes changes against a baseline, without inventing one', () => {
    expect(compare(110, 100)).toEqual({ ratio: 0.1, direction: 'up' })
    expect(compare(90, 100).direction).toBe('down')
    expect(compare(5, 0)).toEqual({ ratio: null, direction: 'up' })
    expect(compare(0, 0)).toEqual({ ratio: null, direction: 'flat' })
  })

  it('formats relative times', () => {
    const now = new Date('2026-11-09T12:00:00Z')
    expect(formatRelative('2026-11-09T11:59:30Z', now)).toBe('agora há pouco')
    expect(formatRelative('2026-11-09T11:55:00Z', now)).toBe('há 5 minutos')
  })
})
