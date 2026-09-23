import { computed, ref } from 'vue'
import type { Placement } from '@popperjs/core'

/**
 * Reading direction. Arabic, Persian, Hebrew and Urdu read right to left;
 * the layout mirrors through logical CSS (ms-*, ps-*, start-*, text-start)
 * once <html dir="rtl"> is set, and the few things CSS cannot mirror (popper
 * placements, chart axes, arrow keys) read `isRtl`.
 */
export const RTL_LANGUAGES: readonly string[] = ['ar', 'fa', 'he', 'ur']

export type Direction = 'ltr' | 'rtl'

export const direction = ref<Direction>('ltr')

export const isRtl = computed<boolean>(() => direction.value === 'rtl')

export function directionOf(locale: string): Direction {
  const language = locale.toLowerCase().split(/[-_]/)[0]

  return RTL_LANGUAGES.includes(language) ? 'rtl' : 'ltr'
}

/** Point the document, and everything reading `isRtl`, at a language's direction */
export function applyDirection(locale: string): void {
  direction.value = directionOf(locale)
  document.documentElement.dir = direction.value
}

/**
 * Popper and the tooltip library place by physical side, so "end" is always
 * the right. In a right-to-left page the reading end is the left: swap them.
 */
export function flipPlacement<T extends Placement | string>(placement: T): T {
  if (!isRtl.value) {
    return placement
  }

  const swaps: Record<string, string> = { start: 'end', end: 'start', left: 'right', right: 'left' }

  return placement.replace(/\b(start|end|left|right)\b/g, (side) => swaps[side]) as T
}
