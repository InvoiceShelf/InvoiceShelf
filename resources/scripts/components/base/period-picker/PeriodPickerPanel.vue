<template>
  <div class="flex flex-col">
    <template v-if="!customOpen">
      <p v-if="summary" class="px-3 pb-2 text-xs text-muted">
        {{ $t('dateRange.showing', { range: summary }) }}
      </p>

      <div class="flex flex-col gap-0.5" role="radiogroup" :aria-label="$t('dateRange.period')">
        <button
          v-for="preset in presets"
          :key="preset.key"
          type="button"
          role="radio"
          :aria-checked="modelValue.preset === preset.key"
          :class="rowClass(modelValue.preset === preset.key)"
          @click="emit('select', presetValue(preset))"
        >
          {{ preset.label }}
          <BaseIcon
            v-if="modelValue.preset === preset.key"
            name="CheckIcon"
            class="w-4.5 h-4.5 text-primary-600 shrink-0"
          />
        </button>

        <button
          v-if="allowCustom"
          ref="customRow"
          type="button"
          role="radio"
          :aria-checked="modelValue.preset === CUSTOM_PERIOD"
          :class="rowClass(modelValue.preset === CUSTOM_PERIOD)"
          @click="openCustom"
        >
          <span class="flex flex-col min-w-0">
            {{ $t('dateRange.custom_range') }}
            <span v-if="modelValue.preset === CUSTOM_PERIOD && currentRange" class="text-xs font-normal truncate text-muted">
              {{ currentRange }}
            </span>
          </span>
          <BaseIcon name="ChevronRightIcon" class="w-4.5 h-4.5 text-subtle shrink-0" />
        </button>
      </div>
    </template>

    <!-- The custom range takes the presets' place: two taps pick the first and last day -->
    <div v-else class="flex flex-col gap-3">
      <button
        ref="backButton"
        type="button"
        class="flex items-center self-start gap-1.5 h-9 pl-1.5 pr-3 text-sm font-medium rounded-lg text-heading hover:bg-hover-strong focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
        @click="closeCustom"
      >
        <BaseIcon name="ChevronLeftIcon" class="w-4.5 h-4.5 text-muted" />
        {{ $t('dateRange.custom_range') }}
      </button>

      <div class="flex justify-center">
        <FlatPickr :model-value="calendarStart" :config="calendarConfig" class="hidden" />
      </div>

      <p
        class="px-1 text-sm text-center tabular-nums"
        :class="rangeError ? 'text-danger' : 'text-heading'"
        aria-live="polite"
      >
        {{ rangeError || draftLabel }}
      </p>

      <div class="flex gap-2" :class="isPhone ? '*:flex-1' : 'justify-end'">
        <BaseButton variant="white" :size="isPhone ? 'md' : 'sm'" @click="closeCustom">
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton variant="primary" :size="isPhone ? 'md' : 'sm'" :disabled="!canApply" @click="apply">
          {{ $t('general.apply') }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { format } from 'date-fns'
import FlatPickr from 'vue-flatpickr-component'
import 'flatpickr/dist/flatpickr.css'
import type { BaseOptions } from 'flatpickr/dist/types/options'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useUserStore } from '@/scripts/stores/user.store'
import { flatpickrLocale } from '@/scripts/utils/flatpickr-locale'
import {
  CUSTOM_PERIOD,
  MAX_PERIOD_YEARS,
  formatPeriodRange,
  isAcceptedRange,
  presetValue,
} from '@/scripts/utils/period'
import type { PeriodPreset, PeriodValue } from '@/scripts/utils/period'

/**
 * What BasePeriodPicker shows in its popover or sheet: the presets, then a
 * custom range picked on an inline calendar. Inline, because flatpickr's own
 * popup is attached to <body> and a click on it would read as a click outside
 * the popover or sheet, closing it.
 */
interface Props {
  modelValue: PeriodValue
  presets: PeriodPreset[]
  allowCustom?: boolean
  summary?: string
}

const props = withDefaults(defineProps<Props>(), {
  allowCustom: true,
  summary: '',
})

interface Emits {
  (e: 'select', value: PeriodValue): void
}

const emit = defineEmits<Emits>()

const { t } = useI18n()
const { isPhone } = useBreakpoints()
const userStore = useUserStore()

const locale = computed<string | undefined>(() => userStore.currentUserSettings.language)

const customOpen = ref<boolean>(false)
const draftFrom = ref<string | null>(props.modelValue.from ?? null)
const draftTo = ref<string | null>(props.modelValue.to ?? null)

// Set when the calendar opens and left alone while it is in use: values that
// followed the picked dates would reset the calendar after every tap
const calendarConfig: Partial<BaseOptions> = {
  inline: true,
  mode: 'range',
  dateFormat: 'Y-m-d',
  locale: flatpickrLocale(locale.value),
  onChange: [onCalendarChange],
}

const calendarStart = shallowRef<string[] | null>(startingDates())

function startingDates(): string[] | null {
  return draftFrom.value && draftTo.value ? [draftFrom.value, draftTo.value] : null
}

const draftLabel = computed<string>(() => {
  if (draftFrom.value && draftTo.value) {
    return formatPeriodRange(draftFrom.value, draftTo.value, locale.value)
  }

  return draftFrom.value
    ? t('dateRange.pick_end')
    : t('dateRange.pick_start')
})

const rangeError = computed<string>(() => {
  if (draftFrom.value && draftTo.value && !isAcceptedRange(draftFrom.value, draftTo.value)) {
    return t('dateRange.too_long', { years: MAX_PERIOD_YEARS })
  }

  return ''
})

// The custom range in force, shown under its row
const currentRange = computed<string>(() => {
  return props.modelValue.from && props.modelValue.to
    ? formatPeriodRange(props.modelValue.from, props.modelValue.to, locale.value)
    : ''
})

const canApply = computed<boolean>(() => !!draftFrom.value && !!draftTo.value && !rangeError.value)

function rowClass(active: boolean): string {
  return [
    'flex items-center justify-between w-full gap-3 px-3 text-left transition-colors rounded-lg',
    'focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus',
    isPhone.value ? 'min-h-12 py-1.5 text-base' : 'min-h-9 py-1 text-sm',
    active ? 'bg-hover-strong text-heading font-medium' : 'text-body hover:bg-hover-strong hover:text-heading',
  ].join(' ')
}

const customRow = ref<HTMLButtonElement | null>(null)
const backButton = ref<HTMLButtonElement | null>(null)

// Switching views replaces the focused button, so focus follows into the new one
async function openCustom(): Promise<void> {
  calendarStart.value = startingDates()
  customOpen.value = true
  await nextTick()
  backButton.value?.focus()
}

async function closeCustom(): Promise<void> {
  customOpen.value = false
  draftFrom.value = props.modelValue.from ?? null
  draftTo.value = props.modelValue.to ?? null
  await nextTick()
  customRow.value?.focus()
}

function onCalendarChange(selected: Date[]): void {
  draftFrom.value = selected[0] ? format(selected[0], 'yyyy-MM-dd') : null
  draftTo.value = selected[1] ? format(selected[1], 'yyyy-MM-dd') : null
}

function apply(): void {
  if (!canApply.value) {
    return
  }

  emit('select', { preset: CUSTOM_PERIOD, from: draftFrom.value, to: draftTo.value })
}
</script>
