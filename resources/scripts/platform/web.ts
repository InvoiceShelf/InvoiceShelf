import type { Platform } from './types'

/**
 * The browser implementation, and the fallback for everything the Capacitor
 * shell does not override yet.
 */

/** Browser tokens, most specific first: Edge and Opera also claim Chrome. */
const BROWSERS: ReadonlyArray<readonly [string, string]> = [
  ['Edg/', 'Edge'],
  ['OPR/', 'Opera'],
  ['Firefox/', 'Firefox'],
  ['Chrome/', 'Chrome'],
  ['Safari/', 'Safari'],
]

/** Platform tokens, most specific first: an iPad also says Mac. */
const SYSTEMS: ReadonlyArray<readonly [string, string]> = [
  ['Android', 'Android'],
  ['iPhone', 'iOS'],
  ['iPad', 'iPadOS'],
  ['Mac OS', 'macOS'],
  ['Windows', 'Windows'],
  ['Linux', 'Linux'],
]

function match(agent: string, table: ReadonlyArray<readonly [string, string]>): string | null {
  for (const [token, label] of table) {
    if (agent.includes(token)) {
      return label
    }
  }

  return null
}

/**
 * A short label such as "Chrome on Linux". It names a token in the account's
 * device list, so it wants to be recognisable rather than exhaustive.
 */
function describeBrowser(): string {
  const agent = typeof navigator === 'undefined' ? '' : navigator.userAgent
  const browser = match(agent, BROWSERS)
  const system = match(agent, SYSTEMS)

  if (browser && system) {
    return `${browser} on ${system}`
  }

  return browser ?? system ?? 'InvoiceShelf client'
}

export const webPlatform: Platform = {
  storage: {
    async get(key: string): Promise<string | null> {
      try {
        return localStorage.getItem(key)
      } catch {
        return null
      }
    },

    async set(key: string, value: string): Promise<void> {
      try {
        localStorage.setItem(key, value)
      } catch {
        // Private-browsing and quota failures are not worth a broken boot.
      }
    },

    async remove(key: string): Promise<void> {
      try {
        localStorage.removeItem(key)
      } catch {
        // As above.
      }
    },
  },

  /**
   * A browser has no identity check to offer. WebAuthn is not one either:
   * it authenticates against a server-held credential, which is a different
   * thing from the app lock's question, "is this the phone's owner".
   */
  biometrics: {
    async available(): Promise<boolean> {
      return false
    },

    async verify(): Promise<boolean> {
      return false
    },
  },

  async deviceName(): Promise<string> {
    return describeBrowser()
  },

  async saveFile(blob: Blob, filename: string): Promise<void> {
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')

    anchor.href = url
    anchor.download = filename
    anchor.rel = 'noopener'
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()

    // Revoke on the next tick: Safari needs the URL alive past the click.
    setTimeout(() => URL.revokeObjectURL(url), 0)
  },

  async openExternal(url: string): Promise<void> {
    window.open(url, '_blank', 'noopener')
  },
}
