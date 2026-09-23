import { LS_KEYS } from '@/scripts/config/constants'
import { initPlatform, platform } from '@/scripts/platform'

/**
 * Typed wrapper around localStorage for safe get/set/remove operations.
 * Handles JSON serialization and deserialization automatically.
 *
 * In the mobile client localStorage is a WebView cache the OS may drop, so a
 * short list of keys is mirrored into platform storage on the way out and
 * restored from it at boot. The synchronous API above is unchanged: the
 * mirroring happens behind it and the web build compiles it away.
 */

/**
 * The keys the client mirrors. Everything else (UI preferences, the chosen
 * language) is cheap to lose and belongs to the device, not the session.
 *
 * The app lock is here rather than with the preferences on purpose: it is a
 * decision about the session, and a WebView cache the OS drops must not be
 * able to unlock the app by forgetting that the user asked for it.
 */
const CLIENT_MIRRORED_KEYS: readonly string[] = [
  LS_KEYS.AUTH_TOKEN,
  LS_KEYS.SELECTED_COMPANY,
  LS_KEYS.IS_ADMIN_MODE,
  LS_KEYS.CLIENT_SERVER_URL,
  LS_KEYS.CLIENT_APP_LOCK,
]

/**
 * Mirror writes run in order and are not awaited by their callers, so a
 * `remove` can never overtake the `set` before it. `flushClientState()` is
 * how the few paths that reload the WebView wait for the queue to drain.
 */
let mirrorQueue: Promise<void> = Promise.resolve()

function mirror(key: string, write: () => Promise<void>): void {
  if (!__INVOICESHELF_CLIENT__ || !CLIENT_MIRRORED_KEYS.includes(key)) {
    return
  }

  mirrorQueue = mirrorQueue.then(write).catch(() => {
    // A store that will not take a write is not a reason to fail the action
    // the user asked for; the next boot simply finds the older value.
  })
}

/**
 * Resolve once every queued mirror write has landed. Call it before
 * reloading, or the reload can outrun the write it depends on.
 */
export function flushClientState(): Promise<void> {
  return mirrorQueue
}

/**
 * Copy the mirrored keys out of platform storage and into localStorage,
 * before anything reads them. Platform storage is the record of truth in a
 * client: a key it does not hold is a key the app does not have.
 */
export async function restoreClientState(): Promise<void> {
  if (!__INVOICESHELF_CLIENT__) {
    return
  }

  await initPlatform()

  for (const key of CLIENT_MIRRORED_KEYS) {
    const value = await platform.storage.get(key)

    try {
      if (value === null) {
        localStorage.removeItem(key)
      } else {
        localStorage.setItem(key, value)
      }
    } catch {
      // Nothing to restore into. The app still boots, unauthenticated.
    }
  }
}

/**
 * Retrieve a value from localStorage, parsed from JSON.
 *
 * @param key - The localStorage key
 * @returns The parsed value, or null if the key does not exist or parsing fails
 */
export function get<T>(key: string): T | null {
  const raw = localStorage.getItem(key)

  if (raw === null) {
    return null
  }

  try {
    return JSON.parse(raw) as T
  } catch {
    // If parsing fails, return the raw string cast to T.
    // This handles cases where the value is a plain string not wrapped in quotes.
    return raw as unknown as T
  }
}

/**
 * Retrieve a boolean from localStorage while tolerating legacy string values.
 *
 * @param key - The localStorage key
 * @returns True only when the stored value represents a truthy boolean
 */
export function getBoolean(key: string): boolean {
  const value = get<boolean | string>(key)

  if (typeof value === 'boolean') {
    return value
  }

  if (typeof value === 'string') {
    return value.toLowerCase() === 'true'
  }

  return false
}

/**
 * Store a value in localStorage as JSON.
 *
 * @param key - The localStorage key
 * @param value - The value to store (will be JSON-serialized)
 */
export function set<T>(key: string, value: T): void {
  if (typeof value === 'string') {
    localStorage.setItem(key, value)
  } else {
    localStorage.setItem(key, JSON.stringify(value))
  }

  // Mirror exactly what was stored, not the argument, so both stores hold
  // the same bytes whichever branch above ran.
  const stored = localStorage.getItem(key)

  if (stored !== null) {
    mirror(key, () => platform.storage.set(key, stored))
  }
}

/**
 * Remove a key from localStorage.
 *
 * @param key - The localStorage key to remove
 */
export function remove(key: string): void {
  localStorage.removeItem(key)
  mirror(key, () => platform.storage.remove(key))
}

/**
 * Check whether a key exists in localStorage.
 *
 * @param key - The localStorage key
 * @returns True if the key exists
 */
export function has(key: string): boolean {
  return localStorage.getItem(key) !== null
}

/**
 * Clear all entries in localStorage.
 */
export function clear(): void {
  localStorage.clear()
}
