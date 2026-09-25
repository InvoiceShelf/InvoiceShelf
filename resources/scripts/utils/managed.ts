/**
 * A managed install: a hosting provider runs it for its owner and owns the
 * storage, backups, PDF rendering, the server's mail transport, modules and
 * upgrades. The server says so through window.managed (Blade shell) or the
 * client manifest.
 */
export interface ManagedState {
  support_url: string | null
}

/** Whether a hosting provider manages this install. */
export function isManaged(): boolean {
  return window.managed_mode === true
}

/** The managed install's details, or null on every other install. */
export function managedState(): ManagedState | null {
  return isManaged() ? (window.managed ?? { support_url: null }) : null
}
