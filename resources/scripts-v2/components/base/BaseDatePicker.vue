<template>
  <BaseContentPlaceholders v-if="contentLoading">
    <BaseContentPlaceholdersBox
      :rounded="true"
      :class="`w-full ${computedContainerClass}`"
      style="height: 38px"
    />
  </BaseContentPlaceholders>

  <div v-else :class="computedContainerClass" class="relative flex flex-row">
    <svg
      v-if="showCalendarIcon && !hasIconSlot"
      viewBox="0 0 20 20"
      fill="currentColor"
      class="
        absolute
        w-4
        h-4
        mx-2
        my-2.5
        text-sm
        not-italic
        font-black
        text-subtle
        cursor-pointer
      "
      @click="onClickDp"
    >
      <path
        fill-rule="evenodd"
        d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
        clip-rule="evenodd"
      ></path>
    </svg>

    <slot v-if="showCalendarIcon && hasIconSlot" name="icon" />

    <FlatPickr
      ref="dp"
      v-model="date"
      v-bind="$attrs"
      :disabled="disabled"
      :config="config"
      :class="[defaultInputClass, inputInvalidClass, inputDisabledClass]"
    />
  </div>
</template>

<script setup lang="ts">
import FlatPickr from 'vue-flatpickr-component'
import 'flatpickr/dist/flatpickr.css'
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
import type { CustomLocale, Locale } from 'flatpickr/dist/types/locale'
import { computed, reactive, watch, ref, useSlots } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'

interface FlatPickrInstance {
  fp: { open: () => void }
}

const dp = ref<FlatPickrInstance | null>(null)

interface Props {
  modelValue?: string | Date
  contentLoading?: boolean
  placeholder?: string | null
  invalid?: boolean
  enableTime?: boolean
  disabled?: boolean
  showCalendarIcon?: boolean
  containerClass?: string
  defaultInputClass?: string
  time24hr?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: () => new Date(),
  contentLoading: false,
  placeholder: null,
  invalid: false,
  enableTime: false,
  disabled: false,
  showCalendarIcon: true,
  containerClass: '',
  defaultInputClass:
    'font-base pl-8 py-2 outline-hidden focus:ring-primary-400 focus:outline-hidden focus:border-primary-400 block w-full sm:text-sm border-line-default rounded-md text-heading',
  time24hr: false,
})

interface Emits {
  (e: 'update:modelValue', value: string | Date): void
}

const emit = defineEmits<Emits>()

const slots = useSlots()

const companyStore = useCompanyStore()

const userStore = useUserStore()

// Localize Flatpicker
const lang: string = userStore.currentUserSettings.language

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

const fpLocale = localeMap[lang] ?? english

interface FlatPickrConfig {
  altInput: boolean
  enableTime: boolean
  time_24hr: boolean
  locale: CustomLocale | Locale
  altFormat?: string
}

const config = reactive<FlatPickrConfig>({
  altInput: true,
  enableTime: props.enableTime,
  time_24hr: props.time24hr,
  locale: fpLocale,
})

const date = computed<string | Date>({
  get: () => props.modelValue,
  set: (value: string | Date) => {
    emit('update:modelValue', value)
  },
})

const carbonFormat = computed<string | undefined>(() => {
  return companyStore.selectedCompanySettings?.carbon_date_format
})

const carbonFormatWithTime = computed<string>(() => {
  let format: string =
    companyStore.selectedCompanySettings?.carbon_date_format ?? ''
  if (companyStore.selectedCompanySettings?.invoice_use_time === 'YES') {
    format +=
      ' ' + (companyStore.selectedCompanySettings?.carbon_time_format ?? '')
  }
  return format.replace('g', 'h').replace('a', 'K')
})

const hasIconSlot = computed<boolean>(() => {
  return !!slots.icon
})

const computedContainerClass = computed<string>(() => {
  const containerClass = `${props.containerClass} `

  return containerClass
})

const inputInvalidClass = computed<string>(() => {
  if (props.invalid) {
    return 'border-red-400 ring-red-400 focus:ring-red-400 focus:border-red-400'
  }

  return ''
})

const inputDisabledClass = computed<string>(() => {
  if (props.disabled) {
    return 'border border-solid rounded-md outline-hidden input-field box-border-2 base-date-picker-input placeholder-gray-400 bg-surface-muted text-body border-line-default'
  }

  return ''
})

function onClickDp(): void {
  dp.value?.fp.open()
}

watch(
  () => props.enableTime,
  () => {
    if (props.enableTime) {
      config.enableTime = props.enableTime
    }
  },
  { immediate: true }
)

watch(
  () => carbonFormat,
  () => {
    if (!props.enableTime) {
      config.altFormat = carbonFormat.value ? carbonFormat.value : 'd M Y'
    } else {
      config.altFormat = carbonFormat.value
        ? `${carbonFormatWithTime.value}`
        : 'd M Y H:i'
    }
  },
  { immediate: true }
)
</script>
