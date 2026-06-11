<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { presetRange, defaultMonthRange } from '@/scripts/utils/date-range'
import { formatDate } from '@/scripts/utils/format-date'
import { useCompanyStore } from '../../../../stores/company.store'
import { useGlobalStore } from '../../../../stores/global.store'

interface DateRangeOption {
  label: string
  key: string
}

interface ReportFormData {
  from_date: string
  to_date: string
}

const { t } = useI18n()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()

const dateRange = reactive<DateRangeOption[]>([
  { label: t('dateRange.today'), key: 'Today' },
  { label: t('dateRange.this_week'), key: 'This Week' },
  { label: t('dateRange.this_month'), key: 'This Month' },
  { label: t('dateRange.this_quarter'), key: 'This Quarter' },
  { label: t('dateRange.this_year'), key: 'This Year' },
  { label: t('dateRange.previous_week'), key: 'Previous Week' },
  { label: t('dateRange.previous_month'), key: 'Previous Month' },
  { label: t('dateRange.previous_quarter'), key: 'Previous Quarter' },
  { label: t('dateRange.previous_year'), key: 'Previous Year' },
  { label: t('dateRange.custom'), key: 'Custom' },
])

const selectedRange = ref<DateRangeOption>(dateRange[2])
const url = ref<string | null>(null)
const siteURL = ref<string | null>(null)

const initialRange = defaultMonthRange()
const formData = reactive<ReportFormData>({
  from_date: initialRange.from,
  to_date: initialRange.to,
})

const getReportUrl = computed<string | null>(() => url.value)

const selectedCompany = computed(() => companyStore.selectedCompany)

const dateRangeUrl = computed<string>(() => {
  return `${siteURL.value}?from_date=${formatDate(formData.from_date)}&to_date=${formatDate(formData.to_date)}`
})

globalStore.downloadReport = downloadReport

onMounted(() => {
  siteURL.value = `/reports/expenses/${selectedCompany.value?.unique_hash}`
  url.value = dateRangeUrl.value
})

function onChangeDateRange(): void {
  if (selectedRange.value.key === 'Custom') return

  const { from, to } = presetRange(selectedRange.value.key)
  formData.from_date = from
  formData.to_date = to
}

function getReports(): boolean {
  url.value = dateRangeUrl.value
  return true
}

async function viewReportsPDF(): Promise<void> {
  getReports()
  window.open(getReportUrl.value ?? '', '_blank')
}

function downloadReport(): void {
  if (!getReports()) return

  window.open(getReportUrl.value + '&download=true')
  setTimeout(() => {
    url.value = dateRangeUrl.value
  }, 200)
}
</script>

<template>
  <div class="grid gap-8 md:grid-cols-12 pt-10">
    <div class="col-span-8 md:col-span-4">
      <BaseInputGroup
        :label="$t('reports.sales.date_range')"
        class="col-span-12 md:col-span-8"
      >
        <BaseMultiselect
          v-model="selectedRange"
          :options="dateRange"
          value-prop="key"
          track-by="key"
          label="label"
          object
          @update:model-value="onChangeDateRange"
        />
      </BaseInputGroup>

      <div class="flex flex-col mt-6 lg:space-x-3 lg:flex-row">
        <BaseInputGroup :label="$t('reports.expenses.from_date')">
          <BaseDatePicker v-model="formData.from_date" />
        </BaseInputGroup>

        <div
          class="hidden w-5 h-0 mx-4 border border-gray-400 border-solid xl:block"
          style="margin-top: 2.5rem"
        />

        <BaseInputGroup :label="$t('reports.expenses.to_date')">
          <BaseDatePicker v-model="formData.to_date" />
        </BaseInputGroup>
      </div>

      <BaseButton
        variant="primary"
        class="hidden w-full mt-6 md:flex justify-center"
        type="submit"
        @click.prevent="getReports"
      >
        <template #left="slotProps">
          <BaseIcon name="ArrowPathIcon" :class="slotProps.class" />
        </template>
        {{ $t('reports.update_report') }}
      </BaseButton>
    </div>

    <div class="col-span-8">
      <iframe
        :src="getReportUrl ?? undefined"
        class="hidden w-full h-screen border-line-light border-solid rounded md:flex"
      />

      <a
        class="flex items-center justify-center h-10 px-5 py-1 text-sm font-medium leading-none text-center text-white rounded whitespace-nowrap md:hidden bg-primary-500 cursor-pointer"
        @click="viewReportsPDF"
      >
        <BaseIcon name="DocumentTextIcon" class="h-5 mr-2" />
        <span>{{ $t('reports.view_pdf') }}</span>
      </a>
    </div>
  </div>
</template>
