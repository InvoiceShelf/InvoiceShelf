import { createApp } from 'vue'
import type { App } from 'vue'
import type { Router } from 'vue-router'
import App_ from './App.vue'
import router from './router'
import { createAppI18n, setI18nLanguage } from './plugins/i18n'
import type { AppI18n } from './plugins/i18n'
import { createAppPinia } from './plugins/pinia'
import { installTooltipDirective } from './plugins/tooltip'
import { defineGlobalComponents } from './global-components'

/**
 * Callback signature for the `booting` hook.
 * Receives the Vue app instance and the router so that modules /
 * plugins can register additional routes, components, or providers.
 */
type BootCallback = (app: App, router: Router) => void

/**
 * Bootstrap class for InvoiceShelf.
 *
 * External code (e.g. dynamically loaded modules) can call
 * `window.InvoiceShelf.booting(callback)` to hook into the app
 * before it mounts.
 *
 * Call `start()` to install all plugins, execute boot callbacks,
 * and mount the application.
 */
export default class InvoiceShelf {
  private bootingCallbacks: BootCallback[] = []
  private messages: Record<string, Record<string, unknown>> = {}
  private i18n: AppI18n | null = null
  private app: App

  constructor() {
    this.app = createApp(App_)
  }

  /**
   * Register a callback that will be invoked before the app mounts.
   */
  booting(callback: BootCallback): void {
    this.bootingCallbacks.push(callback)
  }

  /**
   * Merge additional i18n message bundles (typically from modules).
   */
  addMessages(moduleMessages: Record<string, Record<string, unknown>>): void {
    for (const [locale, msgs] of Object.entries(moduleMessages)) {
      this.messages[locale] = {
        ...this.messages[locale],
        ...msgs,
      }
    }
  }

  /**
   * Dynamically load and activate a language.
   */
  async loadLanguage(locale: string): Promise<void> {
    if (this.i18n) {
      await setI18nLanguage(this.i18n, locale)
    }
  }

  /**
   * Execute all registered boot callbacks, install plugins,
   * and mount the app to `document.body`.
   *
   * Async so the install wizard's pre-DB language choice can be loaded
   * before the first render — see the `install_language` localStorage key
   * set by features/installation/views/LanguageView.vue.
   */
  async start(): Promise<void> {
    // Execute boot callbacks so modules can register routes / components
    this.executeCallbacks()

    // Register global components
    defineGlobalComponents(this.app)

    // i18n
    this.i18n = createAppI18n(this.messages)

    // If the install wizard's Language step set a locale before the DB
    // existed, honor it now so the rest of the wizard renders in the right
    // language. Falls through to 'en' silently on any failure.
    const installLanguage = this.readInstallLanguage()
    if (installLanguage && installLanguage !== 'en') {
      try {
        await setI18nLanguage(this.i18n, installLanguage)
      } catch {
        // Locale file missing or load failed — fall back to en, no-op.
      }
    }

    // Install plugins
    this.app.use(router)
    this.app.use(this.i18n)
    this.app.use(createAppPinia())

    // Directives
    installTooltipDirective(this.app)

    // Mount
    this.app.mount('body')
  }

  // ---- private ----

  private executeCallbacks(): void {
    for (const callback of this.bootingCallbacks) {
      callback(this.app, router)
    }
  }

  /**
   * Read the install-wizard language choice from localStorage. Wrapped in
   * try/catch because localStorage can throw in private-browsing edge cases.
   */
  private readInstallLanguage(): string | null {
    try {
      return localStorage.getItem('install_language')
    } catch {
      return null
    }
  }
}
