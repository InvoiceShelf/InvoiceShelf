import { createSharedComposable, useMediaQuery } from '@vueuse/core'

/**
 * Layout breakpoints as reactive flags, one media-query listener each for the
 * whole app. They mirror Tailwind's `md` (768px) and `lg` (1024px) so script
 * decisions and CSS classes switch at the same width.
 *
 * - `isPhone`: below md. Phone shell (app bar, tab bar), sheets instead of
 *   popovers, lists instead of tables.
 * - `isDesktop`: lg and up. Full sidebar; between the two the sidebar is a rail.
 */
export const useBreakpoints = createSharedComposable(() => {
  const isPhone = useMediaQuery('(max-width: 767.98px)')
  const isDesktop = useMediaQuery('(min-width: 1024px)')

  return { isPhone, isDesktop }
})
