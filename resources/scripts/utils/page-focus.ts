/**
 * Page-level focus and announcements for keyboard and screen reader users.
 */

/** Move focus to the page's main content without scrolling it */
export function focusMain(): void {
  document.getElementById('main-content')?.focus({ preventScroll: true })
}

let region: HTMLElement | null = null

/**
 * Say something politely through a visually hidden live region, created on
 * first use and kept for the life of the page.
 */
export function announce(message: string): void {
  if (!message) {
    return
  }

  if (!region) {
    region = document.createElement('div')
    region.setAttribute('role', 'status')
    region.setAttribute('aria-live', 'polite')
    region.setAttribute('aria-atomic', 'true')
    region.className = 'sr-only'
    document.body.appendChild(region)
  }

  // Cleared first, so the same words twice in a row are still read
  region.textContent = ''
  window.setTimeout(() => {
    if (region) {
      region.textContent = message
    }
  }, 50)
}
