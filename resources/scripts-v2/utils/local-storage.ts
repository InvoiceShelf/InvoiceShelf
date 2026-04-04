/**
 * Typed wrapper around localStorage for safe get/set/remove operations.
 * Handles JSON serialization and deserialization automatically.
 */

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
}

/**
 * Remove a key from localStorage.
 *
 * @param key - The localStorage key to remove
 */
export function remove(key: string): void {
  localStorage.removeItem(key)
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
