import { serverBaseUrl } from '@/scripts/config/runtime'

/**
 * Show a server-rendered email preview in an iframe.
 *
 * The preview arrives as a complete HTML document and is handed to the iframe
 * as a blob URL, which carries the *app* origin, not the server's. Every
 * relative URL the template contains, a logo under `/storage/` above all,
 * would therefore be looked up on an origin that serves nothing. A single
 * `<base href>` naming the server fixes all of them at once.
 *
 * On the web `serverBaseUrl()` is empty, the document is left exactly as the
 * server wrote it, and the blob resolves against the origin it came from.
 */

const HEAD_OPEN = /<head\b[^>]*>/i

/** Point a preview document's relative URLs at the server that rendered it. */
export function withServerBase(html: string): string {
  const base = serverBaseUrl()

  if (base === '') {
    return html
  }

  const tag = `<base href="${base}/">`
  const head = HEAD_OPEN.exec(html)

  if (head === null) {
    return tag + html
  }

  const insertAt = head.index + head[0].length

  return html.slice(0, insertAt) + tag + html.slice(insertAt)
}

/**
 * Turn a preview response into the iframe `src` the modals bind. The caller
 * owns the returned object URL.
 */
export function emailPreviewUrl(response: unknown): string {
  const html = (response as { data?: unknown })?.data ?? response
  const document = typeof html === 'string' ? withServerBase(html) : String(html)

  return URL.createObjectURL(new Blob([document], { type: 'text/html' }))
}
