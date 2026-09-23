/**
 * The handful of things the app can only do through whatever shell it is
 * running in: a browser tab, or a Capacitor WebView on a phone.
 *
 * Every member is async so the native implementations can be dropped in
 * without the callers changing shape.
 */
export interface PlatformStorage {
  get(key: string): Promise<string | null>
  set(key: string, value: string): Promise<void>
  remove(key: string): Promise<void>
}

export interface Platform {
  /** Durable key/value storage. On a device this outlives the WebView cache. */
  storage: PlatformStorage
  /** A human label for this device, used to name the access token it holds. */
  deviceName(): Promise<string>
  /** Hand a generated file (a PDF, a report) to the user. */
  saveFile(blob: Blob, filename: string): Promise<void>
  /** Open an address outside the app. */
  openExternal(url: string): Promise<void>
}
