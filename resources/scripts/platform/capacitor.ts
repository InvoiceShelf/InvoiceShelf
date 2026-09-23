import { BiometricAuth } from '@aparajita/capacitor-biometric-auth'
import { Capacitor } from '@capacitor/core'
import { Browser } from '@capacitor/browser'
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera'
import { Device } from '@capacitor/device'
import { Directory, Filesystem } from '@capacitor/filesystem'
import { Preferences } from '@capacitor/preferences'
import { Share } from '@capacitor/share'
import { webPlatform } from './web'
import type { Platform } from './types'

/**
 * The Capacitor implementation, reached only from the client build through a
 * dynamic import, so nothing here is linked into the web bundle.
 *
 * Two shells load this file. On a phone every method below is the native one.
 * In a browser, `pnpm dev:client` and `pnpm preview:client`, which is the
 * main development loop, `isNativePlatform()` is false, the plugins have no
 * implementation behind them, and each method hands back to `webPlatform`.
 * That fallback is deliberate: the client bundle has to stay runnable in a
 * tab, because that is where it is developed.
 *
 * Storage is the exception, and the reason this file existed before the shell
 * did: a WebView's localStorage is cache, the OS may clear it, and that would
 * sign the user out at random. `@capacitor/preferences` has a browser
 * implementation of its own, so it is used unconditionally.
 */

/** The folder inside the app's cache that shared documents are written to. */
const SHARE_FOLDER = 'shared'

/** How long a shared file is left behind before the next save collects it. */
const SHARE_LIFETIME_MS = 60 * 60 * 1000

/** `platform` as the device list should read it, not as the OS spells it. */
const SYSTEM_LABELS: Readonly<Record<string, string>> = {
  android: 'Android',
  ios: 'iOS',
  web: 'Web',
}

function isNativeShell(): boolean {
  return Capacitor.isNativePlatform()
}

/**
 * Both the share sheet and the camera report a user who backed out as a
 * rejection. Backing out is not a failure: there is nothing to tell the user
 * that they do not already know, and an error toast for it is noise.
 */
function isDismissal(error: unknown): boolean {
  const message = error instanceof Error ? error.message : String(error ?? '')

  return /cancell?ed/i.test(message)
}

/** The payload `Filesystem.writeFile` wants: base64 without the data: prefix. */
function toBase64(blob: Blob): Promise<string> {
  return new Promise<string>((resolve, reject) => {
    const reader = new FileReader()

    reader.onerror = () => reject(reader.error ?? new Error('The file could not be read.'))
    reader.onload = () => {
      const result = typeof reader.result === 'string' ? reader.result : ''
      const comma = result.indexOf(',')

      resolve(comma === -1 ? result : result.slice(comma + 1))
    }
    reader.readAsDataURL(blob)
  })
}

/**
 * A filename the filesystem will take. The server names a file, and that name
 * reaches the user in the share sheet, so it is kept as close as possible:
 * only the characters that would be read as a path or break a write are
 * replaced.
 */
function safeFilename(filename: string): string {
  const name = String(filename ?? '')
    .split(/[\\/]/)
    .pop()
    ?.replace(/[^\w.\-() ]+/g, '_')
    .replace(/^\.+/, '')
    .trim()

  return name === '' || name === undefined ? 'document.pdf' : name.slice(0, 120)
}

/**
 * Drop what earlier shares left in the cache.
 *
 * A file has to outlive the share sheet, because the app the user picked
 * reads it after we are done, so nothing can be deleted on the way out. It is
 * collected on the next save instead, and only once it is old enough that no
 * receiving app can still be holding it. The OS may also clear this directory
 * on its own, which is what `Directory.Cache` is for.
 *
 * Never awaited and never fatal: a full or unreadable cache must not stop a
 * user opening their invoice.
 */
function pruneShareFolder(): void {
  const cutoff = Date.now() - SHARE_LIFETIME_MS

  void Filesystem.readdir({ path: SHARE_FOLDER, directory: Directory.Cache })
    .then(({ files }) =>
      Promise.all(
        files
          .filter((entry) => entry.type === 'file' && entry.mtime < cutoff)
          .map((entry) =>
            Filesystem.deleteFile({
              path: `${SHARE_FOLDER}/${entry.name}`,
              directory: Directory.Cache,
            }).catch(() => undefined),
          ),
      ),
    )
    .catch(() => undefined)
}

/**
 * Photograph a receipt.
 *
 * Not part of the `Platform` interface: it is one screen's affordance, not a
 * capability every caller needs, and the uploader asks for it directly. It
 * lives here so that every Capacitor plugin import stays in the one module
 * the web build never reaches.
 *
 * Returns `null` when there is no camera to use or the user backed out, which
 * are the same thing as far as the form is concerned: nothing was attached.
 */
export async function capturePhoto(): Promise<File | null> {
  if (!isNativeShell()) {
    return null
  }

  let webPath: string | undefined
  let format = 'jpeg'

  try {
    const photo = await Camera.getPhoto({
      quality: 80,
      // A URI, not base64: the photo is handed straight to an upload, and a
      // multi-megabyte string through the plugin bridge is what makes phones
      // drop the frame.
      resultType: CameraResultType.Uri,
      source: CameraSource.Camera,
      correctOrientation: true,
      // The receipt is attached to the expense, not kept in the camera roll.
      saveToGallery: false,
    })

    webPath = photo.webPath
    format = photo.format || format
  } catch (error: unknown) {
    if (isDismissal(error)) {
      return null
    }

    throw error
  }

  if (!webPath) {
    return null
  }

  // `webPath` is served by Capacitor's own scheme handler, so a fetch of it
  // stays on the device and never touches the network.
  const blob = await (await fetch(webPath)).blob()
  const type = blob.type || `image/${format}`
  const extension = format === 'jpeg' ? 'jpg' : format

  return new File([blob], `receipt-${Date.now()}.${extension}`, { type })
}

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

  /**
   * The phone's own identity check, behind the app lock.
   *
   * `checkBiometry()` answers for the hardware and the enrolment together,
   * which is the only answer worth acting on: a phone with a fingerprint
   * reader nobody has registered a finger with cannot let its owner back in.
   *
   * `allowDeviceCredential` puts the PIN, pattern or passcode behind the
   * prompt as the system's own fallback. Without it a wet thumb or a face
   * the sensor will not take locks the owner out of their own books with no
   * way past, and Android hands back `biometryLockout` after a few tries.
   */
  biometrics: {
    async available(): Promise<boolean> {
      if (!isNativeShell()) {
        return webPlatform.biometrics.available()
      }

      try {
        const { isAvailable } = await BiometricAuth.checkBiometry()

        return isAvailable
      } catch {
        // A shell built without the native half. No lock is offered.
        return false
      }
    },

    async verify(reason: string): Promise<boolean> {
      if (!isNativeShell()) {
        return webPlatform.biometrics.verify(reason)
      }

      try {
        await BiometricAuth.authenticate({
          reason,
          androidTitle: reason,
          allowDeviceCredential: true,
        })

        return true
      } catch {
        // Cancelled, failed, locked out or unavailable. All of them mean
        // the same thing here: not verified.
        return false
      }
    },
  },

  /**
   * What the user will see in Settings > Devices next to this token, so it
   * wants to be the name they call the phone. `name` is that name, when the
   * OS will part with it; `model` is the fallback, and on iOS 16 without the
   * device-name entitlement it is effectively the only answer.
   */
  async deviceName(): Promise<string> {
    if (!isNativeShell()) {
      return webPlatform.deviceName()
    }

    try {
      const info = await Device.getInfo()
      const label = info.name?.trim() || info.model
      const system = SYSTEM_LABELS[info.platform] ?? info.platform

      return `${label} (${system})`
    } catch {
      return webPlatform.deviceName()
    }
  },

  /**
   * Hand a document to the user.
   *
   * A phone has no downloads folder a WebView may write to and no viewer it
   * can hand a blob URL, so the share sheet is the whole answer: it is where
   * "save to Files", "open in Acrobat", "mail it to my accountant" and
   * "print" all live. The file is staged in the app's cache first, because
   * the sheet shares a file URI, not bytes.
   */
  async saveFile(blob: Blob, filename: string): Promise<void> {
    if (!isNativeShell()) {
      return webPlatform.saveFile(blob, filename)
    }

    // Before the write, so this run's file is never a candidate.
    pruneShareFolder()

    const name = safeFilename(filename)
    const path = `${SHARE_FOLDER}/${name}`

    await Filesystem.writeFile({
      path,
      data: await toBase64(blob),
      directory: Directory.Cache,
      recursive: true,
    })

    const { uri } = await Filesystem.getUri({ path, directory: Directory.Cache })

    try {
      await Share.share({ title: name, files: [uri], dialogTitle: name })
    } catch (error: unknown) {
      if (!isDismissal(error)) {
        throw error
      }
    }
  },

  /**
   * Open an address outside the app, in the in-app browser rather than by
   * leaving for the browser app: the user comes back with one gesture and the
   * client is still where they left it.
   *
   * Only ever a real http(s) address. A blob never arrives here: on a device
   * `documents.ts` routes those to `saveFile` instead, because a WebView
   * cannot render a PDF and the blob would open on a blank page.
   */
  async openExternal(url: string): Promise<void> {
    if (!isNativeShell()) {
      return webPlatform.openExternal(url)
    }

    await Browser.open({ url })
  },
}
