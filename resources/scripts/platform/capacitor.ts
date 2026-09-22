import { Preferences } from '@capacitor/preferences'
import { webPlatform } from './web'
import type { Platform } from './types'

/**
 * The Capacitor implementation, reached only from the client build through a
 * dynamic import, so nothing here is linked into the web bundle.
 *
 * Storage is the piece that genuinely has to be native: a WebView's
 * localStorage is cache, and the OS may clear it, which would sign the user
 * out at random. The rest still delegates to the browser implementation;
 * the native file, share and device pieces land with the Capacitor shell.
 */
export const capacitorPlatform: Platform = {
  storage: {
    async get(key: string): Promise<string | null> {
      const { value } = await Preferences.get({ key })

      return value ?? null
    },

    async set(key: string, value: string): Promise<void> {
      await Preferences.set({ key, value })
    },

    async remove(key: string): Promise<void> {
      await Preferences.remove({ key })
    },
  },

  deviceName: webPlatform.deviceName,
  saveFile: webPlatform.saveFile,
  openExternal: webPlatform.openExternal,
}
