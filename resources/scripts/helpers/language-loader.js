/**
 * Dynamic language loader utility
 * Loads language files on demand to reduce bundle size
 */

const loadedLanguages = new Set()
const languageCache = new Map()

/**
 * Dynamically import a language file
 * @param {string} locale - Language code (e.g., 'en', 'fr', 'pt_BR')
 * @returns {Promise<Object>} - Language messages object
 */
export async function loadLanguage(locale) {
  // Return cached language if already loaded
  if (languageCache.has(locale)) {
    return languageCache.get(locale)
  }

  try {
    // Dynamic import of language file
    const languageModule = await import(`../../../lang/${locale === 'pt_BR' ? 'pt-br' : locale}.json`)
    const messages = languageModule.default || languageModule

    // Cache the loaded language
    languageCache.set(locale, messages)
    loadedLanguages.add(locale)

    return messages
  } catch (error) {
    console.warn(`Failed to load language: ${locale}`, error)

    // Fallback to English if available
    if (locale !== 'en' && !languageCache.has('en')) {
      try {
        const fallbackModule = await import('../../../lang/en.json')
        const fallbackMessages = fallbackModule.default || fallbackModule
        languageCache.set('en', fallbackMessages)
        return fallbackMessages
      } catch (fallbackError) {
        console.error('Failed to load fallback language (en)', fallbackError)
        return {}
      }
    }

    return languageCache.get('en') || {}
  }
}

/**
 * Load and set language in i18n instance
 * @param {Object} i18n - Vue i18n instance
 * @param {string} locale - Language code to load
 * @returns {Promise<void>}
 */
export async function setI18nLanguage(i18n, locale) {
  // Load the language if not already loaded
  if (!loadedLanguages.has(locale)) {
    const messages = await loadLanguage(locale)
    i18n.global.setLocaleMessage(locale, messages)
  }

  // Set the locale
  i18n.global.locale.value = locale
}

/**
 * Check if a language is already loaded
 * @param {string} locale - Language code
 * @returns {boolean}
 */
export function isLanguageLoaded(locale) {
  return loadedLanguages.has(locale)
}
