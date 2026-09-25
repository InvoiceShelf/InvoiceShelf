/**
 * The "Powered by" line and the source code link. The server sets both
 * through window.powered_by and window.source_url (Blade shell) or the
 * client manifest.
 */
export interface PoweredBy {
  name: string
  url: string
}

const DEFAULT_POWERED_BY: PoweredBy = {
  name: 'InvoiceShelf',
  url: 'https://invoiceshelf.com',
}

/**
 * The line's name and address, or null on a white-label install. A server
 * too old to say keeps the line it always had.
 */
export function poweredBy(): PoweredBy | null {
  return window.powered_by === undefined ? DEFAULT_POWERED_BY : window.powered_by
}

/** Where the source code of the running version is (AGPL section 13). */
export function sourceUrl(): string | null {
  return window.source_url ?? null
}
