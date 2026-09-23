<script setup lang="ts">
import { until } from '@vueuse/core'
import { ref, computed, onMounted, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { defaultMonthRange } from '@/scripts/utils/date-range'
import { presetValue, reportPresets } from '@/scripts/utils/period'
import type { PeriodValue } from '@/scripts/utils/period'
import { formatDate } from '@/scripts/utils/format-date'
import { useCompanyStore } from '../../../../stores/company.store'
import { useGlobalStore } from '../../../../stores/global.store'
import { useReportDownload } from '../useReportDownload'
import ReportPdfPane from '../components/ReportPdfPane.vue'

interface ReportTypeOption {
  label: string
  value: string
}

interface ReportFormData {
  from_date: string
  to_date: string
}

const { t } = useI18n()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()

// The report's dates: a preset or a custom range, This month to start with
const presets = reportPresets(t)
const period = ref<PeriodValue>(presetValue(presets[2]))
const reportTypes = ref<ReportTypeOption[]>([
  { label: t('reports.sales.sort.by_customer'), value: 'By Customer' },
  { label: t('reports.sales.sort.by_item'), value: 'By Item' },
])
const selectedType = ref<string>('By Customer')
const url = ref<string | null>(null)
const pdfPane = ref<InstanceType<typeof ReportPdfPane> | null>(null)
const customerSiteURL = ref<string | null>(null)
const itemsSiteURL = ref<string | null>(null)

const initialRange = defaultMonthRange()
const formData = reactive<ReportFormData>({
  from_date: initialRange.from,
  to_date: initialRange.to,
})

const getReportUrl = computed<string | null>(() => url.value)

const selectedCompany = computed(() => companyStore.selectedCompany)

const customerDateRangeUrl = computed<string>(() => {
  return `${customerSiteURL.value}?from_date=${formatDate(formData.from_date)}&to_date=${formatDate(formData.to_date)}`
})

const itemDateRangeUrl = computed<string>(() => {
  return `${itemsSiteURL.value}?from_date=${formatDate(formData.from_date)}&to_date=${formatDate(formData.to_date)}`
})

// The header button lives on the layout above, so the tab on screen lends it
// its own parameters. They are read at click time, which is why it is a
// callback and not the URL itself.
globalStore.downloadReport = useReportDownload(() => {
  getReports()

  return url.value
})

onMounted(async () => {
  // A direct visit can arrive before the company has loaded: wait for its hash
  await until(() => selectedCompany.value?.unique_hash).toBeTruthy()

  customerSiteURL.value = `/reports/sales/customers/${selectedCompany.value?.unique_hash}`
  itemsSiteURL.value = `/reports/sales/items/${selectedCompany.value?.unique_hash}`
  getInitialReport()
})

watch(period, (value) => {
  if (value.from && value.to) {
    formData.from_date = value.from
    formData.to_date = value.to
  }
})

function getInitialReport(): void {
  if (selectedType.value === 'By Customer') {
    url.value = customerDateRangeUrl.value
    return
  }
  url.value = itemDateRangeUrl.value
}

function getReports(): boolean {
  if (selectedType.value === 'By Customer') {
    url.value = customerDateRangeUrl.value
    return true
  }
  url.value = itemDateRangeUrl.value
  return true
}

// The update button is desktop-only, so this is where a phone applies what it
// typed into the form: the fresh path is handed straight to the pane.
function viewReportsPDF(): void {
  getReports()
  void pdfPane.value?.view(url.value)
}
</script>

<template>
  <div class="grid gap-8 md:grid-cols-12 pt-10">
    <div class="col-span-8 md:col-span-4">
      <BaseInputGroup :label="$t('reports.sales.date_range')" class="mb-6">
        <BasePeriodPicker v-model="period" :presets="presets" block position="bottom-start" />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('reports.sales.report_type')"
        class="col-span-12 md:col-span-8"
      >
        <BaseMultiselect
          v-model="selectedType"
          :options="reportTypes"
          :placeholder="$t('reports.sales.report_type')"
          class="mt-1"
          @update:model-value="getInitialReport"
        />
      </BaseInputGroup>

      <!-- Phones open the PDF instead; the pane it updates is desktop-only -->
      <div class="hidden mt-6 md:block">
        <BaseButton
          variant="primary"
          class="justify-center w-full"
          type="submit"
          @click.prevent="getReports"
        >
          <template #left="slotProps">
            <BaseIcon name="ArrowPathIcon" :class="slotProps.class" />
          </template>
          {{ $t('reports.update_report') }}
        </BaseButton>
      </div>
    </div>

    <div class="col-span-8">
      <ReportPdfPane
        ref="pdfPane"
        :path="getReportUrl"
        class="hidden md:block"
      />

      <button
        type="button"
        class="flex items-center justify-center w-full gap-2 px-5 text-sm font-medium transition-colors rounded-lg h-11 md:hidden bg-btn-primary text-on-primary hover:bg-btn-primary-hover"
        @click="viewReportsPDF"
      >
        <BaseIcon name="DocumentTextIcon" class="w-5 h-5" />
        {{ $t('reports.view_pdf') }}
      </button>
    </div>
  </div>
</template>
