import { describe, expect, it } from 'vitest'
import {
  addDays,
  addMonths,
  isValidDate,
  monthMatrix,
  nowIn,
  rangeFor,
  shift,
  startOfWeek,
  titleFor,
} from '@/modules/appointments/calendar/dates'
import { clipToDate, complement, gridBounds, layoutColumn } from '@/modules/appointments/calendar/layout'

describe('calendar dates', () => {
  it('adds days and months across boundaries', () => {
    expect(addDays('2026-11-30', 1)).toBe('2026-12-01')
    expect(addDays('2026-03-01', -1)).toBe('2026-02-28')
    expect(addMonths('2026-01-31', 1)).toBe('2026-02-28')
  })

  it('starts weeks on Monday', () => {
    expect(startOfWeek('2026-11-15')).toBe('2026-11-09') // Sunday → previous Monday
    expect(startOfWeek('2026-11-09')).toBe('2026-11-09')
  })

  it('builds a 6×7 month matrix starting on Monday', () => {
    const matrix = monthMatrix('2026-11-16')
    expect(matrix).toHaveLength(6)
    expect(matrix[0]?.[0]).toBe('2026-10-26')
    expect(matrix[5]?.[6]).toBe('2026-12-06')
  })

  it('computes the fetched range and navigation for each view', () => {
    expect(rangeFor('day', '2026-11-16')).toEqual({
      from: '2026-11-16',
      to: '2026-11-16',
    })
    expect(rangeFor('week', '2026-11-18')).toEqual({
      from: '2026-11-16',
      to: '2026-11-22',
    })
    expect(shift('week', '2026-11-18', -1)).toBe('2026-11-11')
    expect(shift('month', '2026-11-18', 1)).toBe('2026-12-18')
  })

  it('validates route dates and builds titles', () => {
    expect(isValidDate('2026-02-30')).toBe(false)
    expect(isValidDate('2026-02-28')).toBe(true)
    expect(titleFor('week', '2026-11-18')).toBe('16 – 22 de nov de 2026')
  })

  it('reads the current minute in the company timezone', () => {
    expect(nowIn('America/Sao_Paulo', new Date('2026-11-10T02:30:00Z'))).toEqual({
      date: '2026-11-09',
      minute: 23 * 60 + 30,
    })
  })
})

describe('calendar layout', () => {
  it('puts overlapping items side by side and keeps back-to-back items in one lane', () => {
    const layout = layoutColumn([
      { id: 'a', start: 600, end: 675 }, // 10:00-11:15
      { id: 'b', start: 630, end: 690 }, // overlaps a
      { id: 'c', start: 675, end: 720 }, // starts when a ends → reuses lane 0
      { id: 'd', start: 780, end: 840 }, // separate cluster
    ])

    expect(layout.get('a')).toEqual({ id: 'a', lane: 0, lanes: 2 })
    expect(layout.get('b')).toEqual({ id: 'b', lane: 1, lanes: 2 })
    expect(layout.get('c')).toEqual({ id: 'c', lane: 0, lanes: 2 })
    expect(layout.get('d')).toEqual({ id: 'd', lane: 0, lanes: 1 })
  })

  it('handles many simultaneous items', () => {
    const items = Array.from({ length: 6 }, (_, i) => ({
      id: `x${i}`,
      start: 600,
      end: 660,
    }))
    const layout = layoutColumn(items)
    expect(new Set([...layout.values()].map((l) => l.lane)).size).toBe(6)
    expect([...layout.values()].every((l) => l.lanes === 6)).toBe(true)
  })

  it('derives visible hours and unavailable ranges', () => {
    expect(
      gridBounds([
        [540, 720],
        [780, 1080],
        [1110, 1170],
      ]),
    ).toEqual([540, 1200])
    expect(gridBounds([])).toEqual([480, 1200])
    expect(
      complement(
        [
          [540, 720],
          [780, 1080],
        ],
        [480, 1200],
      ),
    ).toEqual([
      [480, 540],
      [720, 780],
      [1080, 1200],
    ])
    expect(complement([], [480, 1200])).toEqual([[480, 1200]])
  })

  it('clips multi-day blocks to one date', () => {
    expect(clipToDate('2026-11-16 10:00', '2026-11-16 11:30', '2026-11-16')).toEqual([600, 690])
    expect(clipToDate('2026-11-16 18:00', '2026-11-18 09:00', '2026-11-17')).toEqual([0, 1440])
    expect(clipToDate('2026-11-16 18:00', '2026-11-18 09:00', '2026-11-18')).toEqual([0, 540])
    expect(clipToDate('2026-11-16 10:00', '2026-11-16 11:00', '2026-11-17')).toBeNull()
  })
})
