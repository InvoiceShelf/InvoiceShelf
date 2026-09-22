import { createApp, h, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import type { App } from 'vue'
import type { I18nOptions } from 'vue-i18n'
import en from '../../../lang/en.json'
import ClientLockScreen from './ClientLockScreen.vue'
import { defineGlobalComponents } from '../global-components'
import { LS_KEYS } from '../config/constants'
import { isNative } from '../config/runtime'
import { platform } from '../platform'
import * as localStore from '../utils/local-storage'

/**
 * The app lock: the phone's own identity check in front of the app.
 *
 * A thin client holds a token that does not expire, so a phone left on a
 * table is an open account. The lock is the answer, and it is the device's
 * to give: Face ID, a fingerprint, or the passcode behind them.
 *
 * It lives outside the Vue app the router mounts, in a second small app of
 * its own, for one reason: it has to cover the login screen too. A token
 * can outlive a reload that lands on `/login`, and a lock that is a route,
 * or a piece of a layout, is a navigation away from being stepped around.
 *
 * Nothing here runs in the web build: the module is only reachable from
 * `client/main.ts`.
 */

/**
 * How long the app may sit in the background before it locks again.
 *
 * Long enough that the trip to the camera, the share sheet or the
 * authenticator app that a user takes mid-invoice comes back to the screen
 * they left, short enough that a phone handed to someone else does not.
 */
const LOCK_AFTER_MS = 30_000

/** Visible state of the overlay, read by the lock app's render function. */
const visible = ref<boolean>(false)

/** True while the system prompt is up. */
const busy = ref<boolean>(false)

/** When the app last went to the background, or null while it is in front. */
let inactiveSince: number | null = null

let lockApp: App | null = null

/**
 * The overlay's own translations.
 *
 * A second Vue app needs a second i18n instance, and it deliberately does
 * not go through `createAppI18n()`: that call registers itself as the
 * instance later module messages are merged into, and stealing that from
 * the app the user is actually looking at would be a poor trade for four
 * strings. The consequence is that the lock screen speaks English while a
 * translated app does not, which is also what the app itself shows at cold
 * start, before the user's language has been fetched.
 */
const i18nOptions: I18nOptions = {
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  globalInjection: true,
  messages: { en } as I18nOptions['messages'],
}

const i18n = createI18n(i18nOptions)

/** Whether the user asked for the lock on this device. */
function isEnabled(): boolean {
  return localStore.getBoolean(LS_KEYS.CLIENT_APP_LOCK)
}

/**
 * There is only something to lock once there is a session. Without a token
 * the app is on the connect or login screen, which is the screen a locked
 * out user would be sent to anyway.
 */
function hasSession(): boolean {
  try {
    return localStorage.getItem(LS_KEYS.AUTH_TOKEN) !== null
  } catch {
    return false
  }
}

function shouldLock(): boolean {
  return isNative() && isEnabled() && hasSession()
}

/**
 * Arm the lock. Called once, from the client entry point, after the app has
 * mounted: the overlay is appended to `body` and a mount clears it, so this
 * cannot run earlier.
 */
export function startAppLock(): void {
  if (!__INVOICESHELF_CLIENT__ || !isNative()) {
    return
  }

  listenForAppState()

  if (shouldLock()) {
    lock()
  }
}

/**
 * Lock again when the app comes back from the background, but only after it
 * has been away long enough to have left the user's hands.
 *
 * Exported so the transition can be driven directly in a browser, where
 * there is no OS to put the app to sleep. `now` is an argument for the same
 * reason.
 */
export function onAppStateChange(isActive: boolean, now: number = Date.now()): void {
  if (!isActive) {
    // Only the first report of a run counts: both platforms send more than
    // one on the way down (the app switcher, then the lock screen), and a
    // later one would restart the clock the user is being timed against.
    inactiveSince ??= now

    return
  }

  const since = inactiveSince
  inactiveSince = null

  if (since === null || now - since <= LOCK_AFTER_MS) {
    return
  }

  if (shouldLock()) {
    lock()
  }
}

function listenForAppState(): void {
  void import('@capacitor/app')
    .then(({ App: CapacitorApp }) =>
      CapacitorApp.addListener('appStateChange', ({ isActive }) => {
        onAppStateChange(isActive)
      }),
    )
    .catch(() => {
      // A shell without the plugin locks at cold start and no more. That is
      // a weaker lock, not a broken app.
    })
}

/** Put the overlay up and ask the device straight away. */
function lock(): void {
  if (visible.value) {
    return
  }

  visible.value = true
  mountOverlay()
  void verify()
}

/**
 * Ask the device who this is.
 *
 * A refusal leaves the overlay where it is, with the Unlock button to try
 * again: cancelling a prompt is not permission to come in, and it is also
 * how a user reaches the passcode fallback on a second attempt.
 */
async function verify(): Promise<void> {
  if (busy.value) {
    return
  }

  busy.value = true

  try {
    if (await platform.biometrics.verify(i18n.global.t('client.app_lock_reason'))) {
      visible.value = false
    }
  } catch {
    // Treated as a refusal. The overlay stays.
  } finally {
    busy.value = false
  }
}

/**
 * The way out for someone who cannot pass the check: drop the session in
 * both stores and reload into the login screen.
 *
 * The lock setting itself is kept. It belongs to the device, not to the
 * session, and signing in again should not silently leave the phone open.
 */
async function signOut(): Promise<void> {
  localStore.remove(LS_KEYS.AUTH_TOKEN)
  localStore.remove(LS_KEYS.SELECTED_COMPANY)
  localStore.remove(LS_KEYS.IS_ADMIN_MODE)

  // The mirror writes are queued, and a reload can outrun them.
  await localStore.flushClientState()

  window.location.hash = '/login'
  window.location.reload()
}

/**
 * Mount the lock app, once, on a container of its own next to the app's.
 *
 * The component teleports its overlay to `body`, so this container stays
 * empty; it exists to give the second app a root the first one does not
 * manage.
 */
function mountOverlay(): void {
  if (lockApp !== null) {
    return
  }

  const container = document.createElement('div')

  container.id = 'client-app-lock'
  document.body.appendChild(container)

  lockApp = createApp({
    name: 'ClientLockRoot',
    setup() {
      return () =>
        visible.value
          ? h(ClientLockScreen, {
              busy: busy.value,
              onUnlock: () => {
                void verify()
              },
              onSignOut: () => {
                void signOut()
              },
            })
          : null
    },
  })

  // The overlay is drawn with the app's own buttons, and those resolve the
  // rest of the base components globally.
  defineGlobalComponents(lockApp)
  lockApp.use(i18n)
  lockApp.mount(container)
}
