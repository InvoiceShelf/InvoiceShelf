import { computed } from 'vue'
import type { RouteLocationNormalizedLoaded } from 'vue-router'
import { useGlobalStore } from '@/scripts/stores/global.store'

/**
 * Which main-menu entry the current route belongs to: the longest menu link
 * that is the route path or a parent of it, so `/admin/invoices/12/view` marks
 * Invoices and `/admin/settings/tax-types` marks Settings. Shared by the
 * sidebar, the phone tab bar and the More sheet so they always agree.
 */
export function useActiveMenuLink(route: RouteLocationNormalizedLoaded) {
  const globalStore = useGlobalStore()

  const activeMenuLink = computed<string | null>(() => {
    const allLinks = globalStore.menuGroups.flat().map((item) => item.link)
    const matches = allLinks.filter(
      (url) => route.path === url || route.path.startsWith(url + '/'),
    )

    return matches.sort((a, b) => b.length - a.length)[0] ?? null
  })

  function hasActiveUrl(url: string): boolean {
    return url === activeMenuLink.value
  }

  return { activeMenuLink, hasActiveUrl }
}
