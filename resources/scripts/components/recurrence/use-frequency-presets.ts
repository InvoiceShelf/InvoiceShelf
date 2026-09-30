import { computed, type ComputedRef } from 'vue'

export interface FrequencyOption {
  label: string
  value: string
}

/** The preset that stands for "type a cron expression yourself". */
export const CUSTOM_FREQUENCY = 'CUSTOM'

export interface FrequencyPresetOptions {
  /**
   * Offer the hourly and every-minute presets. Schedules that make one
   * record per day (recurring bills and expenses) leave them out.
   */
  subDaily?: boolean
}

type Translate = (key: string) => string

/**
 * The frequencies a schedule offers, as cron expressions, ending with
 * the custom entry.
 */
export function frequencyPresets(
  t: Translate,
  { subDaily = true }: FrequencyPresetOptions = {},
): FrequencyOption[] {
  return [
    // Common business intervals
    { label: t('recurring_invoices.frequency.every_week'), value: '0 0 * * 0' },
    { label: t('recurring_invoices.frequency.every_2_weeks'), value: '0 0 */14 * *' },
    { label: t('recurring_invoices.frequency.every_month'), value: '0 0 1 * *' },
    { label: t('recurring_invoices.frequency.every_2_months'), value: '0 0 1 */2 *' },
    { label: t('recurring_invoices.frequency.every_quarter'), value: '0 0 1 */3 *' },
    { label: t('recurring_invoices.frequency.every_6_month'), value: '0 0 1 */6 *' },
    { label: t('recurring_invoices.frequency.every_year'), value: '0 0 1 1 *' },
    // Less common intervals
    { label: t('recurring_invoices.frequency.every_day'), value: '0 0 * * *' },
    { label: t('recurring_invoices.frequency.every_15_days_at_midnight'), value: '0 5 */15 * *' },
    ...(subDaily
      ? [
          { label: t('recurring_invoices.frequency.every_hour'), value: '0 * * * *' },
          { label: t('recurring_invoices.frequency.every_minute'), value: '* * * * *' },
        ]
      : []),
    // Custom cron expression
    { label: t('recurring_invoices.frequency.custom'), value: CUSTOM_FREQUENCY },
  ]
}

/**
 * The preset a cron expression matches, or the custom entry when it
 * matches none.
 */
export function presetForFrequency(
  presets: FrequencyOption[],
  frequency: string | null | undefined,
): FrequencyOption {
  return (
    presets.find(
      (preset) => preset.value !== CUSTOM_FREQUENCY && preset.value === frequency?.trim(),
    ) ??
    presets.find((preset) => preset.value === CUSTOM_FREQUENCY) ?? {
      label: CUSTOM_FREQUENCY,
      value: CUSTOM_FREQUENCY,
    }
  )
}

/**
 * The frequency presets, kept in step with the active locale, and a way
 * to name any cron expression by its preset.
 */
export function useFrequencyPresets(
  t: Translate,
  options: FrequencyPresetOptions = {},
): {
  presets: ComputedRef<FrequencyOption[]>
  presetFor: (frequency: string | null | undefined) => FrequencyOption
  labelFor: (frequency: string | null | undefined) => string
} {
  const presets = computed(() => frequencyPresets(t, options))

  const presetFor = (frequency: string | null | undefined) =>
    presetForFrequency(presets.value, frequency)

  /** A preset's name, or the cron expression itself when it is custom. */
  const labelFor = (frequency: string | null | undefined) => {
    const preset = presetFor(frequency)

    return preset.value === CUSTOM_FREQUENCY ? frequency || '—' : preset.label
  }

  return { presets, presetFor, labelFor }
}
