import {
  format,
  startOfISOWeek,
  endOfISOWeek,
  startOfMonth,
  endOfMonth,
  startOfQuarter,
  endOfQuarter,
  startOfYear,
  endOfYear,
  subWeeks,
  subMonths,
  subQuarters,
  subYears,
} from 'date-fns'

const FMT = 'yyyy-MM-dd'

type Unit = 'isoWeek' | 'month' | 'quarter' | 'year'

const START: Record<Unit, (d: Date) => Date> = {
  isoWeek: startOfISOWeek,
  month: startOfMonth,
  quarter: startOfQuarter,
  year: startOfYear,
}

const END: Record<Unit, (d: Date) => Date> = {
  isoWeek: endOfISOWeek,
  month: endOfMonth,
  quarter: endOfQuarter,
  year: endOfYear,
}

const SUB: Record<Unit, (d: Date, amount: number) => Date> = {
  isoWeek: subWeeks,
  month: subMonths,
  quarter: subQuarters,
  year: subYears,
}

interface Range {
  from: string
  to: string
}

function thisRange(unit: Unit, now: Date): Range {
  return { from: format(START[unit](now), FMT), to: format(END[unit](now), FMT) }
}

function prevRange(unit: Unit, now: Date): Range {
  const d = SUB[unit](now, 1)
  return { from: format(START[unit](d), FMT), to: format(END[unit](d), FMT) }
}

/** Default report range: the current calendar month, as 'yyyy-MM-dd' strings. */
export function defaultMonthRange(): Range {
  return thisRange('month', new Date())
}

/**
 * Resolve a report date-range preset key to `{ from, to }` 'yyyy-MM-dd' strings.
 * Mirrors the previous moment-based presets exactly (ISO week is Monday-based).
 */
export function presetRange(key: string): Range {
  const now = new Date()

  switch (key) {
    case 'This Week':
      return thisRange('isoWeek', now)
    case 'This Month':
      return thisRange('month', now)
    case 'This Quarter':
      return thisRange('quarter', now)
    case 'This Year':
      return thisRange('year', now)
    case 'Previous Week':
      return prevRange('isoWeek', now)
    case 'Previous Month':
      return prevRange('month', now)
    case 'Previous Quarter':
      return prevRange('quarter', now)
    case 'Previous Year':
      return prevRange('year', now)
    default:
      // 'Today' / 'Custom' — both ends are today.
      return { from: format(now, FMT), to: format(now, FMT) }
  }
}
