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
