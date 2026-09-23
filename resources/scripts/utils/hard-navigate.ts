import router from '@/scripts/router'

/**
 * Go to an in-app route the hard way, discarding every store on the way.
 *
 * Used by the two places that swap the signed-in identity, where a router
 * push would carry the previous user's Pinia state across. The target is
 * resolved through the router so it obeys the history mode: a real path on
 * the web, a fragment in a client. A fragment-only change never reloads a
 * document on its own, so that case asks for the reload explicitly.
 */
export function hardNavigate(path: string): void {
  const href = router.resolve({ path }).href

  window.location.assign(href)

  if (href.startsWith('#')) {
    window.location.reload()
  }
}
