import { format, formatDistanceToNow, parseISO, isValid } from 'date-fns'
import type { Locale } from 'date-fns'

/**
 * Default date format used across the application.
 */
export const DEFAULT_DATE_FORMAT = 'yyyy-MM-dd'

/**
 * Default datetime format used across the application.
 */
export const DEFAULT_DATETIME_FORMAT = 'yyyy-MM-dd HH:mm:ss'

/**
 * Format a date value into a string using the given format pattern.
 *
 * @param date - A Date object, ISO string, or timestamp
 * @param formatStr - A date-fns format pattern (default: 'yyyy-MM-dd')
 * @param options - Optional locale for localized formatting
 * @returns Formatted date string, or empty string if invalid
 */
export function formatDate(
  date: Date | string | number,
  formatStr: string = DEFAULT_DATE_FORMAT,
  options?: { locale?: Locale }
): string {
  const parsed = normalizeDate(date)

  if (!parsed || !isValid(parsed)) {
    return ''
  }

  return format(parsed, formatStr, options)
}

/**
 * Get a human-readable relative time string (e.g. "3 days ago").
 *
 * @param date - A Date object, ISO string, or timestamp
 * @param options - Optional settings for suffix and locale
 * @returns Relative time string, or empty string if invalid
 */
export function relativeTime(
  date: Date | string | number,
  options?: { addSuffix?: boolean; locale?: Locale }
): string {
  const parsed = normalizeDate(date)

  if (!parsed || !isValid(parsed)) {
    return ''
  }

  return formatDistanceToNow(parsed, {
    addSuffix: options?.addSuffix ?? true,
    locale: options?.locale,
  })
}

/**
 * Parse a date string or value into a Date object.
 *
 * @param date - A Date object, ISO string, or timestamp
 * @returns A valid Date object, or null if parsing fails
 */
export function parseDate(date: Date | string | number): Date | null {
  const parsed = normalizeDate(date)

  if (!parsed || !isValid(parsed)) {
    return null
  }

  return parsed
}

/**
 * Check whether a given date value is valid.
 *
 * @param date - A Date object, ISO string, or timestamp
 * @returns True if the date is valid
 */
export function isValidDate(date: Date | string | number): boolean {
  const parsed = normalizeDate(date)
  return parsed !== null && isValid(parsed)
}

/**
 * Normalize various date input types into a Date object.
 */
function normalizeDate(date: Date | string | number): Date | null {
  if (date instanceof Date) {
    return date
  }

  if (typeof date === 'string') {
    const parsed = parseISO(date)
    return isValid(parsed) ? parsed : null
  }

  if (typeof date === 'number') {
    const parsed = new Date(date)
    return isValid(parsed) ? parsed : null
  }

  return null
}
