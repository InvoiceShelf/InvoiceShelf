/**
 * A managed install: a hosting provider runs it for its owner and owns the
 * storage, backups, PDF rendering, the server's mail transport, modules and
 * upgrades. The server says so through window.managed (Blade shell) or the
 * client manifest.
 */
export interface ManagedState {
  support_url: string | null
  billing_url?: string | null
  read_only?: boolean
  /** The provider mounted a writable Modules directory: owners install official modules. */
  modules_installable?: boolean
}

/** Whether a hosting provider manages this install. */
export function isManaged(): boolean {
  return window.managed_mode === true
}

/** The managed install's details, or null on every other install. */
export function managedState(): ManagedState | null {
  return isManaged() ? (window.managed ?? { support_url: null }) : null
}

/**
 * Whether the hosting provider handles modules itself: a managed install
 * without a writable Modules directory. Everywhere else owners install,
 * update and remove official modules as on any install (pairing stays with
 * the provider on a managed one).
 */
export function providerManagesModules(): boolean {
  return isManaged() && managedState()?.modules_installable !== true
}

/** Read-only hosting stops business writes, independent of a user's normal grants. */
export function isReadOnly(): boolean {
  return isManaged() && managedState()?.read_only === true
}

/** Keep readable pages and exports available while hiding business-write controls. */
export function allowsManagedAbility(ability: string): boolean {
  return !isReadOnly() || !/(^|[._ -])(create|edit|update|delete|send|manage|write|accept|decline|mark|install|uninstall|publish)([._ -]|$)/i.test(ability)
}
