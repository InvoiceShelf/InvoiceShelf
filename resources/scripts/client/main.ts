import '../main'

import { isNative, serverBaseUrl } from '../config/runtime'
import { restoreClientState } from '../utils/local-storage'
import { LS_KEYS } from '../config/constants'
import router from '../router'
import { appUrlMismatch, fetchClientManifest, gateManifest, ManifestError } from './manifest'
import { startAppLock } from './lock'
import { clientState } from './state'
import { reassertHostStyles } from '../utils/host-styles'
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
  registerBackButton()

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
 * Android's back button, which is a system gesture and not ours to ignore.
 *
 * The rule the platform expects: go back a screen while there is one, and
 * leave the app at the root rather than sitting there doing nothing, which is
 * how a WebView app earns its reputation. `history.state.back` is the router's
 * own record of the screen behind this one, and it is the right signal here
 * where the WebView's `canGoBack` is not: the boot sequence writes the opening
 * hash before the router exists, so the WebView counts entries the user never
 * navigated to and would refuse to exit on the first screen.
 *
 * Registered before anything else in boot, because an app stuck on the
 * unreachable-server screen is exactly when a user reaches for back. It is a
 * no-op everywhere but a device: iOS has no hardware back button and a browser
 * tab has its own.
 */
function registerBackButton(): void {
  if (!isNative()) {
    return
  }

  void import('@capacitor/app')
    .then(({ App }) =>
      App.addListener('backButton', () => {
        if (window.history.state?.back) {
          router.back()

          return
        }

        void App.exitApp()
      }),
    )
    .catch(() => {
      // A shell without the plugin keeps the system default, which is to
      // close the activity. That is the same outcome, minus the router step.
    })
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
    // Nothing re-injects the app's sheets here, so put them back on top
    reassertHostStyles(hostStyles)
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
  window.demo = manifest.demo ?? undefined

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

    // The app lock goes up after the app has mounted, and only then: the
    // overlay is appended to `body`, which the mount clears. It covers
    // whatever was painted, the login screen included.
    void window.InvoiceShelf.start()
      .catch((error: unknown) => {
        // A mount failure has no screen left to be shown on, but it must
        // not swallow the lock: the app still holds a token.
        console.error(error)
      })
      .finally(startAppLock)
  })
