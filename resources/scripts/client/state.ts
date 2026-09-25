import type { DemoState } from '@/scripts/utils/demo'
import type { ManagedState } from '@/scripts/utils/managed'
import type { PoweredBy } from '@/scripts/utils/branding'
import { reactive } from 'vue'

/**
 * What the boot sequence found, in a shape the login screen can render.
 *
 * A client cannot paint anything useful until it knows which server it talks
 * to and whether the two are the same generation, so the outcome of that
 * check is app state rather than a one-off error.
 */

export type ClientBootStatus =
  /** No server stored yet: the connect screen. */
  | 'no-server'
  /** A server is stored but did not answer. The token is kept. */
  | 'unreachable'
  /** The server predates the client contract, or is another major. */
  | 'too-old'
  /** The server requires a newer app than this one. */
  | 'update-app'
  /** Manifest read, gates passed, modules loaded. */
  | 'ready'

export interface ClientManifestModule {
  name: string
  version: string | null
  script: string | null
  style: string | null
  supported: boolean
}

export interface ClientManifestBranding {
  login_page_logo: string | null
  login_page_heading: string | null
  login_page_description: string | null
  copyright_text: string | null
  /** Absent from servers older than the field. */
  powered_by?: PoweredBy | null
}

/** The client's twin of what `app.blade.php` injects for a browser. */
export interface ClientManifest {
  version: string
  min_client_version: string
  app_url: string
  page_title: string
  branding: ClientManifestBranding
  modules: ClientManifestModule[]
  demo_mode: boolean
  demo?: DemoState | null
  managed_mode?: boolean
  managed?: ManagedState | null
  source_url?: string
  customer_portal_url?: string | null
}

export interface ClientBootState {
  /** The server this app is pointed at, empty when there is none. */
  serverUrl: string
  status: ClientBootStatus
  manifest: ClientManifest | null
  /** Modules that were skipped or failed to load, by name. */
  moduleErrors: string[]
  /** The host the server calls itself, when it disagrees with the typed one. */
  appUrlMismatch: string | null
  /** Detail for the failing states, already human-readable. */
  error: string | null
}

export const clientState = reactive<ClientBootState>({
  serverUrl: '',
  status: 'no-server',
  manifest: null,
  moduleErrors: [],
  appUrlMismatch: null,
  error: null,
})
