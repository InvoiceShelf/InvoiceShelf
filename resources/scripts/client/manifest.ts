import { CLIENT_VERSION, MIN_SERVER_VERSION } from '@/scripts/config/runtime'
import { isAtLeast, majorOf } from '@/scripts/utils/version'
import type { ClientManifest } from './state'

/**
 * Reading the client manifest and judging what it says.
 *
 * Both the boot sequence and the connect screen do this: one to decide
 * whether the app can run against the stored server, the other to tell the
 * user before storing a new one.
 */

/**
 * The manifest route. Deliberately not in `api/endpoints.ts`: that map is
 * linked into the web bundle, and nothing client-only belongs there.
 */
const CLIENT_MANIFEST_PATH = '/api/v1/app/client-manifest'

export const MANIFEST_TIMEOUT_MS = 10_000

/** Why a manifest could not be read. */
export type ManifestFailure =
  /** The route is not there, so the server predates the contract. */
  | 'not-found'
  /** DNS, TLS, CORS, a timeout, or a reply that is not this manifest. */
  | 'unreachable'

export class ManifestError extends Error {
  constructor(
    readonly failure: ManifestFailure,
    message: string,
  ) {
    super(message)
    this.name = 'ManifestError'
  }
}

/**
 * Fetch the manifest from a normalised server URL.
 *
 * Deliberately `fetch` and not the axios client: this runs before the app
 * exists, must not carry credentials, and a 401 here means nothing.
 */
export async function fetchClientManifest(
  serverUrl: string,
  timeoutMs: number = MANIFEST_TIMEOUT_MS,
): Promise<ClientManifest> {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), timeoutMs)

  let response: Response

  try {
    response = await fetch(`${serverUrl}${CLIENT_MANIFEST_PATH}`, {
      method: 'GET',
      credentials: 'omit',
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    })
  } catch (error: unknown) {
    throw new ManifestError('unreachable', describe(error))
  } finally {
    clearTimeout(timer)
  }

  if (response.status === 404) {
    throw new ManifestError('not-found', 'The server has no client manifest.')
  }

  if (!response.ok) {
    throw new ManifestError('unreachable', `The server answered ${response.status}.`)
  }

  let payload: unknown

  try {
    payload = await response.json()
  } catch {
    throw new ManifestError('not-found', 'The server answered something other than a client manifest.')
  }

  if (!isManifest(payload)) {
    throw new ManifestError('not-found', 'The server answered something other than a client manifest.')
  }

  return payload
}

/** The three ways a manifest can land, once it has been read. */
export type ManifestVerdict = 'ready' | 'too-old' | 'update-app'

/**
 * The version gate, in both directions: this build refuses a server it
 * predates or shares no major with, and the server can refuse this build.
 */
export function gateManifest(manifest: ClientManifest): ManifestVerdict {
  if (majorOf(manifest.version) !== majorOf(CLIENT_VERSION)) {
    return 'too-old'
  }

  if (!isAtLeast(manifest.version, MIN_SERVER_VERSION)) {
    return 'too-old'
  }

  if (!isAtLeast(CLIENT_VERSION, manifest.min_client_version)) {
    return 'update-app'
  }

  return 'ready'
}

/**
 * The host the server calls itself when that is not the host the user typed.
 * Media URLs are built from it, so the mismatch is worth saying out loud.
 */
export function appUrlMismatch(serverUrl: string, appUrl: string): string | null {
  const typed = hostOf(serverUrl)
  const reported = hostOf(appUrl)

  if (typed === null || reported === null || typed === reported) {
    return null
  }

  return reported
}

function hostOf(url: string): string | null {
  try {
    return new URL(url).host.toLowerCase()
  } catch {
    return null
  }
}

function isManifest(payload: unknown): payload is ClientManifest {
  if (typeof payload !== 'object' || payload === null) {
    return false
  }

  const candidate = payload as Partial<ClientManifest>

  return typeof candidate.version === 'string' && typeof candidate.min_client_version === 'string'
}

function describe(error: unknown): string {
  if (error instanceof DOMException && error.name === 'AbortError') {
    return 'The server did not answer in time.'
  }

  return error instanceof Error && error.message !== '' ? error.message : 'The server could not be reached.'
}
