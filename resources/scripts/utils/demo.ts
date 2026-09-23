/**
 * The public demo, as the server describes it to the SPA (window.demo from
 * the Blade shell, or the client manifest): the sign-ins visitors are
 * offered and when the next reset wipes their changes.
 */
export interface DemoState {
  next_reset_at: string | null
  email: string
  password: string
  portal_email: string
  portal_password: string
  portal_path: string | null
}

/** The demo, or null on every other install. */
export function demoState(): DemoState | null {
  if (!window.demo_mode) {
    return null
  }

  return window.demo ?? null
}

/**
 * Time until the next reset in hours and minutes, or null when it is not
 * known or already due.
 */
export function timeUntilReset(state: DemoState | null, now: Date = new Date()): { hours: number; minutes: number } | null {
  if (!state?.next_reset_at) {
    return null
  }

  const remaining = Date.parse(state.next_reset_at) - now.getTime()

  if (Number.isNaN(remaining) || remaining <= 0) {
    return null
  }

  const minutes = Math.ceil(remaining / 60000)

  return { hours: Math.floor(minutes / 60), minutes: minutes % 60 }
}
