import { webPlatform } from './web'
import type { Platform, PlatformBiometrics, PlatformStorage } from './types'

export type { Platform, PlatformBiometrics, PlatformStorage } from './types'

/**
 * The shell the app is running in, resolved once at boot.
 *
 * The web implementation is the default and the fallback; the Capacitor one
 * is fetched behind `__INVOICESHELF_CLIENT__`, which is a literal `false` in
 * the web build, so neither the import nor the plugin reaches that bundle.
 */
let active: Platform = webPlatform
let resolving: Promise<void> | null = null

async function resolvePlatform(): Promise<void> {
  if (__INVOICESHELF_CLIENT__) {
    try {
      const { capacitorPlatform } = await import('./capacitor')
      active = capacitorPlatform
    } catch {
      // A shell without the plugin still runs on the browser implementation.
    }
  }
}

/**
 * Pick the implementation. Idempotent, and awaited by the client boot
 * sequence before anything reads stored state.
 */
export function initPlatform(): Promise<void> {
  if (resolving === null) {
    resolving = resolvePlatform()
  }

  return resolving
}

/**
 * A stable facade: callers hold this object, `initPlatform()` decides what it
 * forwards to.
 */
export const platform: Platform = {
  storage: {
    get: (key: string) => active.storage.get(key),
    set: (key: string, value: string) => active.storage.set(key, value),
    remove: (key: string) => active.storage.remove(key),
  },
  biometrics: {
    available: () => active.biometrics.available(),
    verify: (reason: string) => active.biometrics.verify(reason),
  },
  deviceName: () => active.deviceName(),
  saveFile: (blob: Blob, filename: string) => active.saveFile(blob, filename),
  openExternal: (url: string) => active.openExternal(url),
}
