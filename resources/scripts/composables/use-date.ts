import {
  formatDate as formatDateUtil,
  relativeTime as relativeTimeUtil,
  parseDate as parseDateUtil,
  DEFAULT_DATE_FORMAT,
  DEFAULT_DATETIME_FORMAT,
} from '../utils/format-date'
import type { Locale } from 'date-fns'

export interface UseDateReturn {
  formatDate: (
    date: Date | string | number,
    formatStr?: string,
    options?: { locale?: Locale }
  ) => string
  formatDateTime: (
    date: Date | string | number,
    options?: { locale?: Locale }
  ) => string
  relativeTime: (
    date: Date | string | number,
    options?: { addSuffix?: boolean; locale?: Locale }
  ) => string
  parseDate: (date: Date | string | number) => Date | null
}

/**
 * Composable for date formatting and parsing using date-fns.
 * Provides convenient wrappers around utility functions for use within Vue components.
 */
export function useDate(): UseDateReturn {
  /**
   * Format a date using a format pattern.
   *
   * @param date - A Date object, ISO string, or timestamp
   * @param formatStr - date-fns format pattern (default: 'yyyy-MM-dd')
   * @param options - Optional locale
   * @returns Formatted date string, or empty string if invalid
   */
  function formatDate(
    date: Date | string | number,
    formatStr: string = DEFAULT_DATE_FORMAT,
    options?: { locale?: Locale }
  ): string {
    return formatDateUtil(date, formatStr, options)
  }

  /**
   * Format a date with date and time.
   *
   * @param date - A Date object, ISO string, or timestamp
   * @param options - Optional locale
   * @returns Formatted datetime string
   */
  function formatDateTime(
    date: Date | string | number,
    options?: { locale?: Locale }
  ): string {
    return formatDateUtil(date, DEFAULT_DATETIME_FORMAT, options)
  }

  /**
   * Get a human-readable relative time string (e.g. "3 days ago").
   *
   * @param date - A Date object, ISO string, or timestamp
   * @param options - Optional addSuffix and locale settings
   * @returns Relative time string
   */
  function relativeTime(
    date: Date | string | number,
    options?: { addSuffix?: boolean; locale?: Locale }
  ): string {
    return relativeTimeUtil(date, options)
  }

  /**
   * Parse a date value into a Date object.
   *
   * @param date - A Date object, ISO string, or timestamp
   * @returns Parsed Date or null
   */
  function parseDate(date: Date | string | number): Date | null {
    return parseDateUtil(date)
  }

  return {
    formatDate,
    formatDateTime,
    relativeTime,
    parseDate,
  }
}
