<template>
  <BaseInputGroup
    :label="$t('recurring_invoices.frequency.select_frequency')"
    :content-loading="contentLoading"
    :error="isCustom ? null : fieldError"
    required
  >
    <BaseMultiselect
      :model-value="selected?.value ?? null"
      :content-loading="contentLoading"
      :options="presets"
      :disabled="disabled"
      :can-deselect="false"
      label="label"
      value-prop="value"
      @update:model-value="selectPreset"
    />
  </BaseInputGroup>

  <BaseInputGroup
    v-if="isCustom"
    :label="$t('recurring_invoices.frequency.title')"
    :content-loading="contentLoading"
    :error="fieldError"
    required
  >
    <BaseInput
      :model-value="modelValue ?? ''"
      :content-loading="contentLoading"
      :disabled="disabled"
      :loading="isLoadingPreview"
      :invalid="!!fieldError"
      placeholder="0 0 1 * *"
      @update:model-value="typeCustom"
    />
  </BaseInputGroup>

  <div
    v-if="showPreview && !contentLoading"
    :class="previewClass"
    aria-live="polite"
  >
    <p class="text-sm font-medium text-heading">
      {{ $t('recurring_invoices.frequency.upcoming') }}
    </p>
    <p v-if="isLoadingPreview && !upcoming.length" class="mt-1 text-sm text-muted">
      {{ $t('general.loading') }}
    </p>
    <p v-else-if="!upcoming.length" class="mt-1 text-sm text-muted">—</p>
    <ol v-else class="mt-2 flex flex-wrap gap-2 text-sm">
      <li
        v-for="date in upcoming"
        :key="date"
        class="rounded-md border border-line-light bg-surface-secondary px-2 py-1 text-body"
      >
        {{ formatDay(date) }}
      </li>
    </ol>
  </div>
</template>

<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import flatpickr from 'flatpickr'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { parseDate } from '@/scripts/utils/format-date'
import { extractValidationErrors, getErrorTranslationKey } from '@/scripts/utils/error-handling'
import {
  recurrenceService,
  type RecurrencePreview,
} from '@/scripts/api/services/recurrence.service'
import { CUSTOM_FREQUENCY, useFrequencyPresets } from './use-frequency-presets'

interface Props {
  /** The cron expression. */
  modelValue: string | null
  /** The schedule's start date, Y-m-d. */
  startsAt?: string | null
  /**
   * The schedule's first run may fall on the start day itself (recurring
   * bills and expenses). The preview endpoint answers with runs strictly
   * after the date it is given, so it is asked from the day before.
   */
  inclusiveStart?: boolean
  /** Offer the hourly and every-minute presets. */
  subDaily?: boolean
  /** A validation error for the frequency, from the form's own save. */
  error?: string | null
  showPreview?: boolean
  previewClass?: string
  contentLoading?: boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  startsAt: null,
  inclusiveStart: false,
  subDaily: true,
  error: null,
  showPreview: true,
  previewClass: 'col-span-full',
  contentLoading: false,
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
  /** The previewed runs, or null when the frequency cannot be previewed. */
  preview: [value: RecurrencePreview | null]
}>()

const { t } = useI18n()
const company = useCompanyStore()
const { presets, presetFor } = useFrequencyPresets(t, {
  subDaily: props.subDaily,
})

/** Set once the user picks the custom entry, even for a preset's cron. */
const customChosen = ref(false)
const previewError = ref<string | null>(null)
const isLoadingPreview = ref(false)
const upcoming = ref<string[]>([])

/** Nothing is selected until there is a frequency or the custom entry. */
const selected = computed(() => {
  if (customChosen.value) return presetFor(null)
  return props.modelValue ? presetFor(props.modelValue) : null
})
const isCustom = computed(() => selected.value?.value === CUSTOM_FREQUENCY)
const fieldError = computed(() => props.error || previewError.value)

function selectPreset(value: string | null): void {
  if (!value) return
  if (value === CUSTOM_FREQUENCY) {
    // Keep the current expression as a starting point for editing.
    customChosen.value = true
    return
  }
  customChosen.value = false
  emit('update:modelValue', value)
}

function typeCustom(value: string | number): void {
  const frequency = String(value ?? '')
  emit('update:modelValue', frequency === '' ? null : frequency)
}

/** The day before a Y-m-d date, as Y-m-d. */
function dayBefore(date: string): string {
  const [year, month, day] = date.slice(0, 10).split('-').map(Number)
  const previous = new Date(Date.UTC(year, month - 1, day - 1))
  return previous.toISOString().slice(0, 10)
}

function formatDay(date: string): string {
  const parsed = parseDate(date)
  return parsed
    ? flatpickr.formatDate(
        parsed,
        company.selectedCompanySettings?.carbon_date_format || 'Y-m-d',
      )
    : date
}

let request = 0
let timer: ReturnType<typeof setTimeout> | undefined

async function loadPreview(): Promise<void> {
  const frequency = props.modelValue?.trim()
  const current = ++request

  if (!frequency) {
    upcoming.value = []
    previewError.value = null
    emit('preview', null)
    return
  }

  const startsAt = props.startsAt ? String(props.startsAt).slice(0, 10) : ''

  isLoadingPreview.value = true

  try {
    const preview = await recurrenceService.preview({
      frequency,
      ...(startsAt
        ? { starts_at: props.inclusiveStart ? dayBefore(startsAt) : startsAt }
        : {}),
    })
    if (current !== request) return
    upcoming.value = preview.upcoming ?? []
    previewError.value = null
    emit('preview', preview)
  } catch (err) {
    if (current !== request) return
    upcoming.value = []
    const messages = Object.values(extractValidationErrors(err)).flat()
    previewError.value = messages.length
      ? messages
          .map((message) => {
            const key = getErrorTranslationKey(message)
            return key ? t(key) : message
          })
          .join(' ')
      : null
    emit('preview', null)
  } finally {
    if (current === request) isLoadingPreview.value = false
  }
}

watch(
  () => [props.modelValue, props.startsAt, props.inclusiveStart],
  () => {
    clearTimeout(timer)
    timer = setTimeout(loadPreview, 400)
  },
)

// A preview on first render, so an existing schedule shows its dates.
loadPreview()

onUnmounted(() => {
  clearTimeout(timer)
  request++
})
</script>
