import { reactive, ref, watch, onMounted } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { defaultMonthRange } from '@/scripts/utils/date-range'
import { isValidDate } from '@/scripts/utils/format-date'
import {
  reportPresets,
  CUSTOM_PERIOD,
  type PeriodValue,
} from '@/scripts/utils/period'

/** Share a report period through the URL without changing the other report filters. */
export function useReportPeriod(onNavigate?: () => void) {
  const route = useRoute(),
    router = useRouter(),
    { t } = useI18n()
  const presets = reportPresets(t)
  function range() {
    const from = route.query.from_date,
      to = route.query.to_date
    return typeof from === 'string' &&
      typeof to === 'string' &&
      /^\d{4}-\d{2}-\d{2}$/.test(from) &&
      /^\d{4}-\d{2}-\d{2}$/.test(to) &&
      isValidDate(from) &&
      isValidDate(to) &&
      from <= to
      ? { from, to }
      : defaultMonthRange()
  }
  const initial = range()
  const formData = reactive({ from_date: initial.from, to_date: initial.to })
  const period = ref<PeriodValue>({
    preset: route.query.from_date ? CUSTOM_PERIOD : presets[2].key,
    ...initial,
  })
  watch(
    period,
    (value) => {
      if (value.from && value.to) {
        formData.from_date = value.from
        formData.to_date = value.to
      }
    },
    { flush: 'sync' },
  )
  watch(
    () => [route.query.from_date, route.query.to_date],
    () => {
      const next = range()
      if (next.from === formData.from_date && next.to === formData.to_date)
        return
      period.value = { preset: CUSTOM_PERIOD, ...next }
      onNavigate?.()
    },
    { flush: 'sync' },
  )
  function syncUrl(extra: LocationQueryRaw = {}) {
    return router.replace({
      query: {
        ...route.query,
        report: route.query.report || 'sales',
        ...extra,
        from_date: formData.from_date,
        to_date: formData.to_date,
      },
    })
  }
  onMounted(() => {
    void syncUrl()
  })
  return { period, presets, formData, syncUrl }
}
