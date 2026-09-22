import '../main'

import { serverBaseUrl } from '../config/runtime'
import { restoreClientState } from '../utils/local-storage'
import { LS_KEYS } from '../config/constants'
import { appUrlMismatch, fetchClientManifest, gateManifest, ManifestError } from './manifest'
import { clientState } from './state'
import type { ClientManifest, ClientManifestModule } from './state'

/**
 * The thin client's entry point.
 *
 * A browser gets all of this from `resources/views/app.blade.php`: which
 * server it belongs to, the sign-in branding, and the module assets to pull
 * in before the app boots. A client has no shell, so it asks the server for
 * the same facts and then starts the very same SPA.
 *
 * Nothing here throws its way out: every failure lands on a boot status the
 * login screen can explain, because an app that will not paint cannot tell
 * anyone why.
 */

/** A module that hangs must not hold the app hostage. */
const MODULE_TIMEOUT_MS = 15_000

async function boot(): Promise<void> {
  await restoreClientState()

  const serverUrl = serverBaseUrl()
  clientState.serverUrl = serverUrl

  if (serverUrl === '') {
    clientState.status = 'no-server'

    return
  }

  let manifest: ClientManifest

  try {
    manifest = await fetchClientManifest(serverUrl)
  } catch (error: unknown) {
    // The token survives both: a server that is down or unreachable has not
    // said anything about this session, and neither has a 404.
    clientState.status = error instanceof ManifestError && error.failure === 'not-found' ? 'too-old' : 'unreachable'
    clientState.error = error instanceof Error ? error.message : null

    return
  }

  clientState.manifest = manifest

  const verdict = gateManifest(manifest)

  if (verdict !== 'ready') {
    clientState.status = verdict

    return
  }

  clientState.appUrlMismatch = appUrlMismatch(serverUrl, manifest.app_url)

  await loadModules(manifest.modules)
  applyBranding(manifest)

  clientState.status = 'ready'
}

/**
 * Module assets, in manifest order and one script at a time.
 *
 * Sequential because modules register through `window.InvoiceShelf.booting()`
 * and the Blade shell gives them a deterministic order today. Each one gets
 * its own timeout and its own try/catch: a module that fails is reported and
 * skipped, never a reason the app does not start.
 */
async function loadModules(modules: ClientManifestModule[]): Promise<void> {
  let styled = false

  for (const entry of modules ?? []) {
    if (!entry.supported) {
      // A script registered as a remote URL: the operator cannot put CORS
      // headers on an origin they do not own, so there is no way to load it.
      clientState.moduleErrors.push(entry.name)

      continue
    }

    if (entry.style) {
      appendModuleStyle(entry.style)
      styled = true
    }

    if (!entry.script) {
      continue
    }

    try {
      await withTimeout(import(/* @vite-ignore */ entry.script), MODULE_TIMEOUT_MS)
    } catch {
      clientState.moduleErrors.push(entry.name)
    }
  }

  if (styled) {
    reassertHostStyles()
  }
}

/**
 * The app's own stylesheets, noted before a module adds any of its own.
 */
const hostStyles: Element[] = [...document.head.querySelectorAll('link[rel="stylesheet"], style')]

/**
 * Add one module stylesheet, after the app's, exactly as the Blade shell
 * writes it: the app's sheet has to be parsed first, because the first
 * Tailwind build in the document is the one that fixes the cascade layer
 * order for everything after it.
 */
function appendModuleStyle(href: string): void {
  const link = document.createElement('link')

  link.rel = 'stylesheet'
  link.href = href
  document.head.appendChild(link)
}

/**
 * Put the app's stylesheets back at the end of the cascade.
 *
 * Module CSS is a self-contained Tailwind build, so it carries utilities the
 * app also defines, and between two rules of equal weight the later sheet
 * wins. A browser loading the Blade shell ends up with the app's stylesheet
 * twice, because the entry chunk re-injects it after the shell's module
 * links, and that second copy is what stops a module flipping a layout
 * utility (`.hidden` over `md:flex`) out from under the app. Nothing
 * re-injects anything here, so this does it deliberately.
 *
 * A link is copied rather than moved: moving the node drops its stylesheet
 * and re-fetches it, which flashes. An inline `<style>` (the dev server's
 * form) has nothing to re-fetch, so it moves.
 */
function reassertHostStyles(): void {
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

function withTimeout<T>(work: Promise<T>, timeoutMs: number): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('Timed out.')), timeoutMs)

    work.then(resolve, reject).finally(() => clearTimeout(timer))
  })
}

/**
 * The globals the Blade shell would have written, from the manifest instead.
 */
function applyBranding(manifest: ClientManifest): void {
  const branding = manifest.branding ?? {}

  window.login_page_logo = branding.login_page_logo ?? undefined
  window.login_page_heading = branding.login_page_heading ?? undefined
  window.login_page_description = branding.login_page_description ?? undefined
  window.copyright_text = branding.copyright_text ?? undefined
  window.demo_mode = manifest.demo_mode === true

  if (manifest.page_title) {
    document.title = manifest.page_title
  }
}

/**
 * Where the app opens.
 *
 * A client starts on an empty hash, and anything short of `ready` has only
 * one screen worth showing: the login route, which carries the connect flow
 * and every boot message.
 */
function openingRoute(): void {
  if (clientState.status !== 'ready') {
    window.location.hash = '/login'

    return
  }

  // '/login' counts as no destination: it is where a reload after connecting
  // to a server lands, and a session that survived that is not asked to sign
  // in again. A '/login?next=...' is a real destination and is left alone.
  const current = window.location.hash.replace(/^#/, '')

  if (current !== '' && current !== '/' && current !== '/login') {
    return
  }

  window.location.hash = localStorage.getItem(LS_KEYS.AUTH_TOKEN) ? '/admin/dashboard' : '/login'
}

boot()
  .catch((error: unknown) => {
    clientState.status = 'unreachable'
    clientState.error = error instanceof Error ? error.message : null
  })
  .finally(() => {
    openingRoute()
    window.InvoiceShelf.start()
  })
