<script setup lang="ts">
import { until } from '@vueuse/core'
import { ref, computed, onMounted } from 'vue'
import { formatDate } from '@/scripts/utils/format-date'
import { useCompanyStore } from '../../../../stores/company.store'
import { useGlobalStore } from '../../../../stores/global.store'
import { useReportDownload } from '../useReportDownload'
import { useReportPeriod } from '../use-report-period'
import ReportPdfPane from '../components/ReportPdfPane.vue'

const globalStore = useGlobalStore()
const companyStore = useCompanyStore()

// The report's dates: a preset or a custom range, This month to start with
const { presets, period, formData, syncUrl } = useReportPeriod(() =>
  getReports(false),
)
const url = ref<string | null>(null)
const pdfPane = ref<InstanceType<typeof ReportPdfPane> | null>(null)
const siteURL = ref<string | null>(null)

const getReportUrl = computed<string | null>(() => url.value)

const selectedCompany = computed(() => companyStore.selectedCompany)

const dateRangeUrl = computed<string>(() => {
  return `${siteURL.value}?from_date=${formatDate(formData.from_date)}&to_date=${formatDate(formData.to_date)}`
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

  siteURL.value = `/reports/tax-summary/${selectedCompany.value?.unique_hash}`
  url.value = dateRangeUrl.value
})

function getReports(persist = true): boolean {
  if (persist) void syncUrl()
  url.value = dateRangeUrl.value
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
      <BaseInputGroup :label="$t('reports.taxes.date_range')">
        <BasePeriodPicker
          v-model="period"
          :presets="presets"
          block
          position="bottom-start"
        />
      </BaseInputGroup>

      <!-- Phones open the PDF instead; the pane it updates is desktop-only -->
      <div class="hidden mt-6 md:block">
        <BaseButton
          variant="primary"
          class="justify-center w-full"
          type="submit"
          @click.prevent="getReports()"
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
