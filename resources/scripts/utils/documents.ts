import { client } from '@/scripts/api/client'
import { isNative, serverBaseUrl } from '@/scripts/config/runtime'
import { platform } from '@/scripts/platform'

/**
 * The documents the server renders: invoice, estimate and payment PDFs, and
 * the financial reports.
 *
 * They used to be addressed as plain URLs: an iframe `src`, a `window.open`,
 * an `<a download>`. Every one of those carries the cookie of the origin the
 * page came from, which is exactly what a thin client does not have: its
 * origin serves the app package and nothing else, and the only credential it
 * holds is a bearer token. So a document is fetched through the API client,
 * which knows both the server address and the token, and what comes back is
 * passed around as a blob.
 *
 * The web gets the same three improvements for free: a failure surfaces as a
 * failure instead of a blank frame, a download no longer depends on popup
 * permission, and the file keeps the name the server gave it.
 */

export interface FetchedDocument {
  blob: Blob
  filename: string
}

/** Used when the response names no file of its own. */
const FALLBACK_FILENAME = 'document.pdf'

/**
 * A blob URL lives until it is revoked, and a tab opened on one needs it for
 * as long as the viewer takes to read it. A minute is well past that and
 * still bounded.
 */
const OPENED_URL_LIFETIME = 60_000

/**
 * Reduce an address to what the API client can resolve: a path on the server.
 *
 * Callers pass `/invoices/pdf/<hash>` already, but an absolute same-origin URL
 * is still accepted, because that is what the app built for years and a stale
 * one would otherwise be sent to the client's own origin.
 */
export function serverPath(src: string): string {
  const value = String(src ?? '').trim()

  if (value === '') {
    return ''
  }

  const origin = window.location.origin

  if (origin !== '' && value.startsWith(origin)) {
    return value.slice(origin.length) || '/'
  }

  return value
}

/**
 * The same address as something a user can paste elsewhere. On the web that
 * is this origin; in a client it is the server they connected to, which is
 * the only host that can actually serve the document.
 */
export function absoluteDocumentUrl(path: string): string {
  const relative = serverPath(path)

  if (!relative.startsWith('/')) {
    return relative
  }

  const base = serverBaseUrl() || window.location.origin

  return `${base}${relative}`
}

/** `decodeURIComponent` on a name the server encoded badly is not fatal. */
function safeDecode(value: string): string {
  try {
    return decodeURIComponent(value)
  } catch {
    return value
  }
}

/**
 * The filename out of a `Content-Disposition` header, RFC 5987 form first
 * since it is the one that can carry a non-ASCII name. Any directory part is
 * dropped: the server names a file, never a location.
 */
function filenameFrom(disposition: unknown): string | null {
  if (typeof disposition !== 'string' || disposition === '') {
    return null
  }

  const encoded = /filename\*=(?:UTF-8|utf-8)''([^;]+)/.exec(disposition)
  const plain = /filename="?([^";]+)"?/.exec(disposition)
  const raw = encoded ? safeDecode(encoded[1]) : plain?.[1]

  if (!raw) {
    return null
  }

  const name = raw.trim().split(/[\\/]/).pop() ?? ''

  return name === '' ? null : name
}

/**
 * Fetch a rendered document. `path` is a path on the server, query string
 * included if it has one; `params` is merged onto it by the client.
 */
export async function fetchDocumentBlob(
  path: string,
  params: Record<string, unknown> = {},
  fallbackFilename: string = FALLBACK_FILENAME,
): Promise<FetchedDocument> {
  const response = await client.get<Blob>(serverPath(path), {
    params,
    responseType: 'blob',
  })

  const blob = response.data

  // Not a nicety: the PDF routes answer a caller they do not recognise with a
  // redirect to the login page, which arrives here as a 200 carrying HTML,
  // and a missing expense receipt answers 200 with a JSON error. Framing the
  // first would show a login form inside the invoice; saving the second would
  // hand the user an error message as a file.
  if (blob.type.startsWith('text/html') || blob.type.startsWith('application/json')) {
    throw new Error('The server answered with a message instead of a document.')
  }

  return {
    blob,
    filename: filenameFrom(response.headers?.['content-disposition']) ?? fallbackFilename,
  }
}

/** An address the current document can frame. Revoke it when it goes away. */
export function previewUrlFor(blob: Blob): string {
  return URL.createObjectURL(blob)
}

export function revokePreviewUrl(url: string | null): void {
  if (url) {
    URL.revokeObjectURL(url)
  }
}

/** Hand a fetched document to the user: a download in a browser, a file on a device. */
export async function deliverDocument(blob: Blob, filename: string): Promise<void> {
  await platform.saveFile(blob, filename)
}

/**
 * Show a fetched document. A browser renders a PDF itself, so the object URL
 * goes to a new tab; a phone WebView cannot, so the file leaves the app
 * through the platform instead.
 */
export async function openDocument(blob: Blob, filename: string): Promise<void> {
  if (isNative()) {
    await deliverDocument(blob, filename)

    return
  }

  const url = previewUrlFor(blob)

  await platform.openExternal(url)

  setTimeout(() => revokePreviewUrl(url), OPENED_URL_LIFETIME)
}

/** Fetch and hand over in one step, which is what every download button wants. */
export async function downloadDocument(
  path: string,
  params: Record<string, unknown> = {},
  fallbackFilename: string = FALLBACK_FILENAME,
): Promise<void> {
  const fetched = await fetchDocumentBlob(path, params, fallbackFilename)

  await deliverDocument(fetched.blob, fetched.filename)
}
