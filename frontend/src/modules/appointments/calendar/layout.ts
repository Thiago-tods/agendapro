/** Geometry helpers for the time grid. All values are minutes since local midnight. */

export type Range = [number, number]

export interface TimedItem {
  id: string
  start: number
  /** End of the occupied interval (service + buffer). */
  end: number
}

export interface LaidOut {
  id: string
  /** Zero-based lane inside its overlap cluster. */
  lane: number
  /** Number of lanes in the cluster: the item takes 1/lanes of the column width. */
  lanes: number
}

/**
 * Side-by-side layout for overlapping items in one column (half-open intervals: an item that
 * ends at 10:00 does not overlap one starting at 10:00). Items in the same cluster share the
 * lane count so their widths line up.
 */
export function layoutColumn(items: TimedItem[]): Map<string, LaidOut> {
  const sorted = [...items].sort((a, b) => a.start - b.start || b.end - a.end || a.id.localeCompare(b.id))
  const result = new Map<string, LaidOut>()

  let cluster: { item: TimedItem; lane: number }[] = []
  let laneEnds: number[] = []
  let clusterEnd = -Infinity

  const flush = () => {
    const lanes = Math.max(1, laneEnds.length)
    cluster.forEach(({ item, lane }) => result.set(item.id, { id: item.id, lane, lanes }))
    cluster = []
    laneEnds = []
  }

  for (const item of sorted) {
    if (item.start >= clusterEnd) flush()

    let lane = laneEnds.findIndex((end) => end <= item.start)
    if (lane === -1) {
      lane = laneEnds.length
      laneEnds.push(item.end)
    } else {
      laneEnds[lane] = item.end
    }

    cluster.push({ item, lane })
    clusterEnd = Math.max(clusterEnd === -Infinity ? item.end : clusterEnd, item.end)
  }
  flush()

  return result
}

/**
 * Visible hours: from the earliest opening/appointment to the latest closing/appointment,
 * rounded to whole hours. Falls back to 08:00–20:00 when there is nothing to show.
 */
export function gridBounds(ranges: Range[], fallback: Range = [8 * 60, 20 * 60]): Range {
  if (!ranges.length) return fallback
  const start = Math.min(...ranges.map((r) => r[0]))
  const end = Math.max(...ranges.map((r) => r[1]))
  return [Math.max(0, Math.floor(start / 60) * 60), Math.min(24 * 60, Math.ceil(end / 60) * 60)]
}

/** Parts of [from, to) not covered by the open ranges: rendered as unavailable time. */
export function complement(open: Range[], [from, to]: Range): Range[] {
  const closed: Range[] = []
  let cursor = from

  for (const [start, end] of [...open].sort((a, b) => a[0] - b[0])) {
    if (end <= cursor) continue
    if (start > cursor) closed.push([cursor, Math.min(start, to)])
    cursor = Math.max(cursor, end)
    if (cursor >= to) break
  }
  if (cursor < to) closed.push([cursor, to])

  return closed.filter(([s, e]) => e > s)
}

/** Portion of a local "YYYY-MM-DD HH:mm" interval that falls on one date. */
export function clipToDate(localStart: string, localEnd: string, date: string): Range | null {
  const startDate = localStart.slice(0, 10)
  const endDate = localEnd.slice(0, 10)
  if (date < startDate || date > endDate) return null

  const toMin = (value: string) => Number(value.slice(11, 13)) * 60 + Number(value.slice(14, 16))
  const start = date === startDate ? toMin(localStart) : 0
  const end = date === endDate ? toMin(localEnd) : 24 * 60
  return end > start ? [start, end] : null
}

/** True when [start, end) is fully inside one of the open ranges. */
export function fitsIn(open: Range[], start: number, end: number): boolean {
  return open.some(([s, e]) => start >= s && end <= e)
}
