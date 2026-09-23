import { addYears, format, parseISO, startOfMonth, subMonths } from 'date-fns'
import { presetRange } from './date-range'
import type { Range } from './date-range'

/**
 * The period a money chart or report covers, as BasePeriodPicker holds it.
 *
 * `preset` is a preset's key or CUSTOM_PERIOD. `from` and `to` are 'yyyy-MM-dd'
 * strings; they stay empty for presets only the server can resolve (the
 * company's fiscal years).
 */
export interface PeriodValue {
  preset: string
  from?: string | null
  to?: string | null
}

export interface PeriodPreset {
  key: string
  label: string
  /** The dates, when the client can work them out itself */
  range?: () => Range
}

export const CUSTOM_PERIOD = 'custom'

/** The longest range the server accepts, in years */
export const MAX_PERIOD_YEARS = 5

const FMT = 'yyyy-MM-dd'

type Translate = (key: string) => string

/**
 * The dashboard's and the customer chart's presets. The two years follow the
 * company's fiscal year, so the server resolves them.
 */
export function yearPresets(t: Translate): PeriodPreset[] {
  return [
    { key: 'this_year', label: t('dateRange.this_year') },
    { key: 'previous_year', label: t('dateRange.previous_year') },
    {
      key: 'last_12_months',
      label: t('dateRange.last_12_months'),
      range: () => {
        const today = new Date()

        return { from: format(startOfMonth(subMonths(today, 11)), FMT), to: format(today, FMT) }
      },
    },
  ]
}

/** The reports' calendar presets, resolved on the client */
export function reportPresets(t: Translate): PeriodPreset[] {
  const presets: Array<[string, string]> = [
    ['Today', 'dateRange.today'],
    ['This Week', 'dateRange.this_week'],
    ['This Month', 'dateRange.this_month'],
    ['This Quarter', 'dateRange.this_quarter'],
    ['This Year', 'dateRange.this_year'],
    ['Previous Week', 'dateRange.previous_week'],
    ['Previous Month', 'dateRange.previous_month'],
    ['Previous Quarter', 'dateRange.previous_quarter'],
    ['Previous Year', 'dateRange.previous_year'],
  ]

  return presets.map(([key, label]) => ({ key, label: t(label), range: () => presetRange(key) }))
}

/** The value a preset stands for, with its dates filled in when known */
export function presetValue(preset: PeriodPreset): PeriodValue {
  const range = preset.range?.()

  return { preset: preset.key, from: range?.from ?? null, to: range?.to ?? null }
}

/**
 * The chart endpoints' query parameters: nothing for this fiscal year, the
 * previous_year flag for the last one, and dates for everything else.
 */
export function periodParams(value: PeriodValue): { previous_year?: number; from_date?: string; to_date?: string } {
  if (value.from && value.to) {
    return { from_date: value.from, to_date: value.to }
  }

  return value.preset === 'previous_year' ? { previous_year: 1 } : {}
}

/**
 * "1 Mar – 31 Aug 2026" in the reader's language: the year written once when
 * both ends share it.
 */
export function formatPeriodRange(from: string, to: string, locale?: string): string {
  const formatter = new Intl.DateTimeFormat(safeLocale(locale), { day: 'numeric', month: 'short', year: 'numeric' })

  return formatter.formatRange(parseISO(from), parseISO(to))
}

/** What the picker's trigger reads: the preset's name, or the custom dates */
export function formatPeriodLabel(value: PeriodValue, presets: PeriodPreset[], locale?: string): string {
  const preset = presets.find((candidate) => candidate.key === value.preset)

  if (preset) {
    return preset.label
  }

  return value.from && value.to ? formatPeriodRange(value.from, value.to, locale) : ''
}

/** Whether a range is one the server will accept: in order, and not too long */
export function isAcceptedRange(from: string, to: string): boolean {
  return from <= to && to <= format(addYears(parseISO(from), MAX_PERIOD_YEARS), FMT)
}

// The app's language codes use underscores ("pt_BR"); Intl wants a hyphen
function safeLocale(locale?: string): string | undefined {
  if (!locale) {
    return undefined
  }

  const tag = locale.replace('_', '-')

  try {
    return Intl.DateTimeFormat.supportedLocalesOf(tag).length ? tag : undefined
  } catch {
    return undefined
  }
}
