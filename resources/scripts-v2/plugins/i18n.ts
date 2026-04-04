import { createI18n } from 'vue-i18n'
import type { I18n, I18nOptions } from 'vue-i18n'
import en from '../../../lang/en.json'

/**
 * Locale-to-filename mapping for language files whose filename does
 * not match the locale code exactly.
 */
const LOCALE_FILE_MAP: Record<string, string> = {
  zh_CN: 'zh-cn',
  pt_BR: 'pt-br',
}

/** Tracks which languages have already been loaded. */
const loadedLanguages = new Set<string>(['en'])

/** In-memory cache of loaded message objects keyed by locale. */
const languageCache = new Map<string, Record<string, unknown>>()

/**
 * Dynamically import a language JSON file for a given locale.
 */
async function loadLanguageMessages(
  locale: string
): Promise<Record<string, unknown>> {
  if (languageCache.has(locale)) {
    return languageCache.get(locale)!
  }

  const fileName = LOCALE_FILE_MAP[locale] ?? locale

  try {
    const mod: { default: Record<string, unknown> } = await import(
      `../../../lang/${fileName}.json`
    )
    const messages = mod.default ?? mod
    languageCache.set(locale, messages)
    loadedLanguages.add(locale)
    return messages
  } catch (error: unknown) {
    console.warn(`Failed to load language: ${locale}`, error)

    // Fall back to English
    if (locale !== 'en' && !languageCache.has('en')) {
      try {
        const fallback: { default: Record<string, unknown> } = await import(
          '../../../lang/en.json'
        )
        const fallbackMessages = fallback.default ?? fallback
        languageCache.set('en', fallbackMessages)
        return fallbackMessages
      } catch (fallbackError: unknown) {
        console.error('Failed to load fallback language (en)', fallbackError)
        return {}
      }
    }

    return languageCache.get('en') ?? {}
  }
}

/**
 * Load a language and activate it on the given i18n instance.
 */
export async function setI18nLanguage(
  i18n: I18n<Record<string, unknown>, Record<string, unknown>, Record<string, unknown>, string, false>,
  locale: string
): Promise<void> {
  if (!loadedLanguages.has(locale)) {
    const messages = await loadLanguageMessages(locale)
    i18n.global.setLocaleMessage(locale, messages)
  }

  i18n.global.locale.value = locale
}

/**
 * Check whether a language has already been loaded.
 */
export function isLanguageLoaded(locale: string): boolean {
  return loadedLanguages.has(locale)
}

/** Type alias for the i18n instance created by this module. */
export type AppI18n = I18n<
  Record<string, unknown>,
  Record<string, unknown>,
  Record<string, unknown>,
  string,
  false
>

/**
 * Create and return the vue-i18n plugin instance.
 *
 * Only the English bundle is included synchronously; all other
 * languages are loaded on demand via `setI18nLanguage`.
 */
export function createAppI18n(
  extraMessages?: Record<string, Record<string, unknown>>
): AppI18n {
  const messages: Record<string, Record<string, unknown>> = {
    en: en as unknown as Record<string, unknown>,
    ...extraMessages,
  }

  const options: I18nOptions = {
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    globalInjection: true,
    messages,
  }

  return createI18n(options) as AppI18n
}
