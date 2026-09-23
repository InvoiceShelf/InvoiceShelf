import type { Router } from 'vue-router'
import { announce, focusMain } from '@/scripts/utils/page-focus'

/**
 * After a navigation inside the app, name the page in the tab title, move
 * focus to its content and announce it, as a full page load would. The name
 * is the page's own heading, read once it has rendered; detail pages fill
 * theirs in after loading, so it is looked for a few times.
 *
 * The first load is left alone (the browser already announces it), and so
 * are changes to the query or hash only, such as a filter.
 */
export function installPageAnnouncer(router: Router): void {
  const baseTitle = document.title
  let firstNavigation = true

  router.afterEach((to, from, failure) => {
    if (failure) {
      return
    }

    const isFirst = firstNavigation
    const pathChanged = to.path !== from.path

    firstNavigation = false

    const settle = (attempt: number): void => {
      const heading = document.querySelector('main h1, h1')?.textContent?.trim() ?? ''

      if (!heading && attempt < 4) {
        window.setTimeout(() => settle(attempt + 1), 300 * (attempt + 1))
        return
      }

      document.title = heading ? `${heading} - ${baseTitle}` : baseTitle

      if (isFirst || !pathChanged) {
        return
      }

      focusMain()
      announce(heading || baseTitle)
    }

    window.setTimeout(() => settle(0), 150)
  })
}
