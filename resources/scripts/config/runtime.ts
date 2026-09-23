import { LS_KEYS } from './constants'
import * as localStore from '@/scripts/utils/local-storage'

/**
 * What the running bundle is and what it is talking to.
 *
 * On the web both answers are trivial: it is not a client, and the server is
 * the origin the page came from. In a thin client the app package has no
 * server of its own, so every absolute address is built from the URL the user
 * typed on the connect screen.
 */

declare global {
  interface Window {
    Capacitor?: {
      isNativePlatform?: () => boolean
    }
  }
}

/** True in the mobile client build, a folded constant in the web build. */
export const isClient: boolean = __INVOICESHELF_CLIENT__

/** The build this bundle was cut from, the same string `version.md` holds. */
export const CLIENT_VERSION: string = __INVOICESHELF_CLIENT_VERSION__

/**
 * The oldest server that carries the client contract: the public client
 * manifest, CORS on the module asset routes and the throttled bearer login.
 */
export const MIN_SERVER_VERSION = '3.0.0-alpha.4'

/**
 * Tidy a typed-in address into the form everything else assumes: no
 * surrounding space, no trailing slash, no query or fragment. A scheme is
 * required rather than guessed, because whether the connection is encrypted
 * is the user's decision to see and make.
 *
 * Returns an empty string when the input is not a usable http(s) address.
 */
export function normalizeServerUrl(raw: string): string {
  const trimmed = String(raw ?? '').trim()

  if (!/^https?:\/\//i.test(trimmed)) {
    return ''
  }

  let parsed: URL

  try {
    parsed = new URL(trimmed)
  } catch {
    return ''
  }

  if (parsed.host === '') {
    return ''
  }

  // A sub-path install (https://example.com/books) keeps its path.
  const path = parsed.pathname.replace(/\/+$/, '')

  return `${parsed.protocol}//${parsed.host}${path}`
}

/**
 * The address every request is built from. Empty on the web, which leaves
 * axios resolving against the current origin exactly as it does today.
 */
export function serverBaseUrl(): string {
  if (!isClient) {
    return ''
  }

  try {
    return localStorage.getItem(LS_KEYS.CLIENT_SERVER_URL) ?? ''
  } catch {
    return ''
  }
}

/**
 * Remember the server. Written through `local-storage` so the platform store
 * gets it too and a cold start finds it.
 */
export function setServerBaseUrl(url: string): void {
  if (!isClient) {
    return
  }

  localStore.set(LS_KEYS.CLIENT_SERVER_URL, url)
}

/** Forget the server, which is what the "change server" action does. */
export function clearServerBaseUrl(): void {
  if (!isClient) {
    return
  }

  localStore.remove(LS_KEYS.CLIENT_SERVER_URL)
}

/**
 * Address a file the server hosts. A root-relative path such as
 * `/storage/logo.png` means "this origin" in a browser and "the app package"
 * in a client, where there is nothing to serve it.
 */
export function assetUrl(path: string): string {
  if (!isClient || typeof path !== 'string' || path === '') {
    return path
  }

  // Protocol-relative and absolute URLs already name their own host.
  if (!path.startsWith('/') || path.startsWith('//')) {
    return path
  }

  const base = serverBaseUrl()

  return base === '' ? path : `${base}${path}`
}

/** True only inside a Capacitor WebView, false in any browser. */
export function isNative(): boolean {
  return Boolean(window.Capacitor?.isNativePlatform?.())
}
