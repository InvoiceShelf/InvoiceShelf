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
      aria-hidden="true"
      viewBox="0 0 20 20"
      fill="currentColor"
      class="absolute z-10 w-4 h-4 -translate-y-1/2 cursor-pointer top-1/2 left-3 text-subtle"
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
      v-bind="passthroughAttrs"
      :disabled="disabled"
      :config="config"
      :class="[defaultInputClass, inputInvalidClass, inputDisabledClass]"
    />
  </div>
</template>

<script setup lang="ts">
import FlatPickr from 'vue-flatpickr-component'
import 'flatpickr/dist/flatpickr.css'
import type { CustomLocale, Locale } from 'flatpickr/dist/types/locale'
import { computed, reactive, watch, ref, useAttrs, useSlots } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { flatpickrLocale } from '@/scripts/utils/flatpickr-locale'
import { useFormField } from '@/scripts/composables/use-form-field'

interface FlatPickrInstance {
  fp: { open: () => void; altInput?: HTMLInputElement }
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
    'font-base pl-9 py-2 outline-hidden block w-full md:text-sm tabular border-control-border rounded-lg text-heading',
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
const fpLocale = flatpickrLocale(userStore.currentUserSettings.language)

interface FlatPickrConfig {
  altInput: boolean
  enableTime: boolean
  time_24hr: boolean
  locale: CustomLocale | Locale
  altFormat?: string
  onReady: Array<(dates: Date[], value: string, instance: { altInput?: HTMLInputElement }) => void>
}

const attrs = useAttrs()
const { attrs: fieldAttrs } = useFormField({ invalid: () => props.invalid })

// The id and ARIA attributes belong on the visible copy only (see below)
const passthroughAttrs = computed(() => Object.fromEntries(
  Object.entries(attrs).filter(([key]) => key !== 'id' && !key.startsWith('aria-')),
))

/**
 * flatpickr hides the real input and shows a copy (its alt input) formatted
 * for people. The copy is the one users focus, so the label, description and
 * state go on it.
 */
function applyFieldAttrs(input?: HTMLInputElement): void {
  if (!input) {
    return
  }

  const passed = Object.fromEntries(
    Object.entries(attrs).filter(([key]) => key === 'id' || key.startsWith('aria-')),
  )

  for (const [key, value] of Object.entries({ ...fieldAttrs.value, ...passed })) {
    if (value === undefined || value === null || value === false) {
      input.removeAttribute(key)
    } else {
      input.setAttribute(key, String(value))
    }
  }
}

const config = reactive<FlatPickrConfig>({
  altInput: true,
  enableTime: props.enableTime,
  time_24hr: props.time24hr,
  locale: fpLocale,
  onReady: [(_dates, _value, instance) => applyFieldAttrs(instance.altInput)],
})

watch(fieldAttrs, () => applyFieldAttrs(dp.value?.fp?.altInput))

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
    return 'border-danger focus:border-danger focus:ring-danger/20'
  }

  return ''
})

const inputDisabledClass = computed<string>(() => {
  if (props.disabled) {
    return 'border border-solid rounded-lg outline-hidden placeholder-subtle bg-surface-secondary text-muted border-line-light cursor-not-allowed'
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
