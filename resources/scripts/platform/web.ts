import type { AppLinkSignIn, Platform } from './types'

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

/**
 * Whether an address the browser was sent to is the app link's answer for
 * this sign-in, and not one for another hand-off, such as an earlier sign-in
 * whose link arrived late.
 */
export function isAnswerFor(link: string, signIn: AppLinkSignIn): boolean {
  if (!link.startsWith(signIn.appLink)) {
    return false
  }

  try {
    return new URL(link).searchParams.get('handoff') === signIn.handoff
  } catch {
    return false
  }
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

  /**
   * Signing in through the server's SSO from the client bundle in a tab
   * (`pnpm dev:client`): the sign-in opens in a popup, and the server's last
   * redirect lands it on `sso-return.html` on this origin, which posts its
   * address back here. That needs the server's INVOICESHELF_CLIENT_APP_LINK
   * to point at that page; the phone apps use their own link (capacitor.ts).
   * Closing the popup is a cancel; a popup the browser blocked is a failure.
   */
  async openForAppLink(start: () => Promise<AppLinkSignIn>): Promise<string | null> {
    // Opened empty before `start` makes its request, while this still runs
    // in the click: a popup opened after an await may be blocked.
    const popup = window.open('', 'invoiceshelf-sign-in', 'popup,width=520,height=720')

    if (popup === null) {
      throw new Error('The sign-in window could not be opened.')
    }

    let signIn: AppLinkSignIn

    try {
      signIn = await start()
    } catch (error: unknown) {
      popup.close()
      throw error
    }

    popup.location.href = signIn.url

    return new Promise((resolve) => {
      let settled = false

      const finish = (link: string | null): void => {
        if (!settled) {
          settled = true
          window.removeEventListener('message', onMessage)
          window.clearInterval(watch)
          resolve(link)
        }
      }

      const onMessage = (event: MessageEvent): void => {
        const link = (event.data as { appLink?: unknown } | null)?.appLink

        if (event.origin === window.location.origin && typeof link === 'string' && isAnswerFor(link, signIn)) {
          popup.close()
          finish(link)
        }
      }

      const watch = window.setInterval(() => {
        if (popup.closed) {
          finish(null)
        }
      }, 500)

      window.addEventListener('message', onMessage)
    })
  },
}
