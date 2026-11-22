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

export interface PlatformBiometrics {
  /**
   * Whether this device can ask for a fingerprint or a face right now:
   * the hardware is there and the user has enrolled with it.
   */
  available(): Promise<boolean>
  /**
   * Ask. `reason` is what the system prompt tells the user it is for.
   *
   * Resolves `true` only on a confirmed identity. A user who cancels, a
   * device that has locked biometry out, and a shell with no plugin behind
   * it are all `false`: the caller's job is to keep the door shut, not to
   * explain which of those happened.
   */
  verify(reason: string): Promise<boolean>
}

/** A sign-in a server started for this app (see `openForAppLink`). */
export interface AppLinkSignIn {
  /** The server's sign-in page, to open in a browser. */
  url: string
  /** The address this app answers to, where the server sends that browser at the end. */
  appLink: string
  /** The hand-off the sign-in belongs to. A link for any other is not its answer. */
  handoff: string
}

export interface Platform {
  /** Durable key/value storage. On a device this outlives the WebView cache. */
  storage: PlatformStorage
  /** The device's own identity check, behind the app lock. */
  biometrics: PlatformBiometrics
  /** A human label for this device, used to name the access token it holds. */
  deviceName(): Promise<string>
  /** Hand a generated file (a PDF, a report) to the user. */
  saveFile(blob: Blob, filename: string): Promise<void>
  /** Open an address outside the app. */
  openExternal(url: string): Promise<void>
  /**
   * Open a browser for a server's sign-in and wait for the server to send
   * that browser on to the app link with the answer for this hand-off.
   *
   * `start` asks the server for the sign-in. It is called once the browser
   * side is ready, so that a browser tab can open its popup straight from
   * the click, before any request is made, where popup blockers allow it.
   *
   * Resolves the full address the browser was sent to, or null when the user
   * closed the browser first. Rejects when `start` does, or when no browser
   * could be opened.
   */
  openForAppLink(start: () => Promise<AppLinkSignIn>): Promise<string | null>
}
