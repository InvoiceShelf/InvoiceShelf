const rtlLocales = new Set(['ar', 'fa'])

export function getDocumentDirection(locale) {
  const language = String(locale || 'en')
    .toLowerCase()
    .split(/[-_]/, 1)[0]

  return rtlLocales.has(language) ? 'rtl' : 'ltr'
}

export function syncDocumentLocale(
  locale,
  documentElement = document.documentElement,
) {
  const normalizedLocale = String(locale || 'en').replaceAll('_', '-')

  documentElement.lang = normalizedLocale
  documentElement.dir = getDocumentDirection(normalizedLocale)
}
