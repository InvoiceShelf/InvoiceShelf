<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import moment from 'moment'
import { useI18n } from 'vue-i18n'
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

const formData = reactive<ReportFormData>({
  from_date: moment().startOf('month').format('YYYY-MM-DD'),
  to_date: moment().endOf('month').format('YYYY-MM-DD'),
})

const getReportUrl = computed<string | null>(() => url.value)

const selectedCompany = computed(() => companyStore.selectedCompany)

const dateRangeUrl = computed<string>(() => {
  return `${siteURL.value}?from_date=${moment(formData.from_date).format(
    'YYYY-MM-DD'
  )}&to_date=${moment(formData.to_date).format('YYYY-MM-DD')}`
})

globalStore.downloadReport = downloadReport

onMounted(() => {
  siteURL.value = `/reports/tax-summary/${selectedCompany.value?.unique_hash}`
  url.value = dateRangeUrl.value
})

function getThisDate(type: string, time: string): string {
  return (moment() as Record<string, unknown> as Record<string, (t: string) => moment.Moment>)[type](time).format('YYYY-MM-DD')
}

function getPreDate(type: string, time: string): string {
  return (moment().subtract(1, time as moment.unitOfTime.DurationConstructor) as Record<string, unknown> as Record<string, (t: string) => moment.Moment>)[type](time).format('YYYY-MM-DD')
}

function onChangeDateRange(): void {
  const key = selectedRange.value.key

  switch (key) {
    case 'Today':
      formData.from_date = moment().format('YYYY-MM-DD')
      formData.to_date = moment().format('YYYY-MM-DD')
      break
    case 'This Week':
      formData.from_date = getThisDate('startOf', 'isoWeek')
      formData.to_date = getThisDate('endOf', 'isoWeek')
      break
    case 'This Month':
      formData.from_date = getThisDate('startOf', 'month')
      formData.to_date = getThisDate('endOf', 'month')
      break
    case 'This Quarter':
      formData.from_date = getThisDate('startOf', 'quarter')
      formData.to_date = getThisDate('endOf', 'quarter')
      break
    case 'This Year':
      formData.from_date = getThisDate('startOf', 'year')
      formData.to_date = getThisDate('endOf', 'year')
      break
    case 'Previous Week':
      formData.from_date = getPreDate('startOf', 'isoWeek')
      formData.to_date = getPreDate('endOf', 'isoWeek')
      break
    case 'Previous Month':
      formData.from_date = getPreDate('startOf', 'month')
      formData.to_date = getPreDate('endOf', 'month')
      break
    case 'Previous Quarter':
      formData.from_date = getPreDate('startOf', 'quarter')
      formData.to_date = getPreDate('endOf', 'quarter')
      break
    case 'Previous Year':
      formData.from_date = getPreDate('startOf', 'year')
      formData.to_date = getPreDate('endOf', 'year')
      break
  }
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
        :label="$t('reports.taxes.date_range')"
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
        <BaseInputGroup :label="$t('reports.taxes.from_date')">
          <BaseDatePicker v-model="formData.from_date" />
        </BaseInputGroup>

        <div
          class="hidden w-5 h-0 mx-4 border border-gray-400 border-solid xl:block"
          style="margin-top: 2.5rem"
        />

        <BaseInputGroup :label="$t('reports.taxes.to_date')">
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
