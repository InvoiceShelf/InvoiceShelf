/**
 * Keeping the app's stylesheets ahead of the modules' in the cascade.
 *
 * Module CSS is a self-contained Tailwind build, so it carries utilities the
 * app also defines, and between two rules of equal weight the later sheet
 * wins. A module sheet loaded after the app's would flip a layout utility out
 * from under the app: its `.hidden` beats the app's `xl:flex`, its
 * `.grid-cols-2` beats `md:grid-cols-4`. The module sheet still has to come
 * after the app's first copy, because the first Tailwind build in the
 * document fixes the cascade layer order for everything after it. So the app's
 * sheets are added once more, after the modules'.
 */

const MODULE_STYLE = 'link[rel="stylesheet"][href*="/modules/styles/"]'

/**
 * Put the given app stylesheets back at the end of the cascade.
 *
 * A link is copied rather than moved: moving the node drops its stylesheet
 * and re-fetches it, which flashes. An inline `<style>` (the dev server's
 * form) has nothing to re-fetch, so it moves.
 */
export function reassertHostStyles(hostStyles: Element[]): void {
  for (const node of hostStyles) {
    if (node instanceof HTMLLinkElement) {
      const copy = document.createElement('link')

      copy.rel = 'stylesheet'
      copy.href = node.href
      document.head.appendChild(copy)

      continue
    }

    document.head.appendChild(node)
  }
}

/**
 * The Blade shell writes the module stylesheets after the app's. Re-add the
 * app's sheets that precede them, so every page gets the same cascade
 * whichever chunk it happens to load.
 */
export function reassertHostStylesAfterShellModules(): void {
  const firstModuleStyle = document.head.querySelector(MODULE_STYLE)

  if (!firstModuleStyle) {
    return
  }

  const hostStyles = [...document.head.querySelectorAll('link[rel="stylesheet"], style')].filter(
    (node) => !node.matches(MODULE_STYLE)
      && (node.compareDocumentPosition(firstModuleStyle) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0,
  )

  reassertHostStyles(hostStyles)
}
