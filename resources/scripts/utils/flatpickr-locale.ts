import type { CustomLocale, Locale } from 'flatpickr/dist/types/locale'
import { Arabic } from 'flatpickr/dist/l10n/ar.js'
import { Czech } from 'flatpickr/dist/l10n/cs.js'
import { German } from 'flatpickr/dist/l10n/de.js'
import { Greek } from 'flatpickr/dist/l10n/gr.js'
import { english } from 'flatpickr/dist/l10n/default.js'
import { Spanish } from 'flatpickr/dist/l10n/es.js'
import { Persian } from 'flatpickr/dist/l10n/fa.js'
import { Finnish } from 'flatpickr/dist/l10n/fi.js'
import { French } from 'flatpickr/dist/l10n/fr.js'
import { Hindi } from 'flatpickr/dist/l10n/hi.js'
import { Croatian } from 'flatpickr/dist/l10n/hr.js'
import { Indonesian } from 'flatpickr/dist/l10n/id.js'
import { Italian } from 'flatpickr/dist/l10n/it.js'
import { Japanese } from 'flatpickr/dist/l10n/ja.js'
import { Korean } from 'flatpickr/dist/l10n/ko.js'
import { Lithuanian } from 'flatpickr/dist/l10n/lt.js'
import { Latvian } from 'flatpickr/dist/l10n/lv.js'
import { Dutch } from 'flatpickr/dist/l10n/nl.js'
import { Polish } from 'flatpickr/dist/l10n/pl.js'
import { Portuguese } from 'flatpickr/dist/l10n/pt.js'
import { Romanian } from 'flatpickr/dist/l10n/ro.js'
import { Russian } from 'flatpickr/dist/l10n/ru.js'
import { Slovak } from 'flatpickr/dist/l10n/sk.js'
import { Slovenian } from 'flatpickr/dist/l10n/sl.js'
import { Serbian } from 'flatpickr/dist/l10n/sr.js'
import { Swedish } from 'flatpickr/dist/l10n/sv.js'
import { Thai } from 'flatpickr/dist/l10n/th.js'
import { Turkish } from 'flatpickr/dist/l10n/tr.js'
import { Vietnamese } from 'flatpickr/dist/l10n/vn.js'
import { Mandarin } from 'flatpickr/dist/l10n/zh.js'

/**
 * flatpickr's month and weekday names for the app's languages; anything
 * without one of its own falls back to English.
 */
const localeMap: Record<string, CustomLocale | Locale> = {
  ar: Arabic,
  cs: Czech,
  de: German,
  el: Greek,
  en: english,
  es: Spanish,
  fa: Persian,
  fi: Finnish,
  fr: French,
  hi: Hindi,
  hr: Croatian,
  id: Indonesian,
  it: Italian,
  ja: Japanese,
  ko: Korean,
  lt: Lithuanian,
  lv: Latvian,
  nl: Dutch,
  pl: Polish,
  pt: Portuguese,
  pt_BR: Portuguese,
  ro: Romanian,
  ru: Russian,
  sk: Slovak,
  sl: Slovenian,
  sr: Serbian,
  sv: Swedish,
  th: Thai,
  tr: Turkish,
  vi: Vietnamese,
  zh: Mandarin,
}

export function flatpickrLocale(language: string | null | undefined): CustomLocale | Locale {
  return (language && localeMap[language]) || english
}
