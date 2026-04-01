import { createApp } from 'vue'
import App from '@/scripts/App.vue'
import { createI18n } from 'vue-i18n'
import messages from '/lang/locales'
import router from '@/scripts/router/index'
import utils from '@/scripts/helpers/utilities.js'
import _ from 'lodash'
import { VTooltip } from 'v-tooltip'
import { setI18nLanguage } from '@/scripts/helpers/language-loader.js'

const app = createApp(App)

export default class InvoiceShelf {
  constructor() {
    this.bootingCallbacks = []
    this.messages = messages
    this.i18n = null
  }

  booting(callback) {
    this.bootingCallbacks.push(callback)
  }

  executeCallbacks() {
    this.bootingCallbacks.forEach((callback) => {
      callback(app, router)
    })
  }

  addMessages(moduleMessages = []) {
    _.merge(this.messages, moduleMessages)
  }

  /**
   * Dynamically load and set a language
   * @param {string} locale - Language code to load
   * @returns {Promise<void>}
   */
  async loadLanguage(locale) {
    if (this.i18n) {
      await setI18nLanguage(this.i18n, locale)
    }
  }

  start() {
    this.executeCallbacks()

    app.provide('$utils', utils)

    this.i18n = createI18n({
      legacy: false,
      locale: 'en',
      fallbackLocale: 'en',
      globalInjection: true,
      messages: this.messages,
    })

    window.i18n = this.i18n

    // Expose language loader globally
    window.loadLanguage = this.loadLanguage.bind(this)

    const { createPinia } = window.pinia

    app.use(router)
    app.use(this.i18n)
    app.use(createPinia())
    app.provide('utils', utils)
    app.directive('tooltip', VTooltip)

    app.mount('body')
  }
}
