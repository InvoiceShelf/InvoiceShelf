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
          @update:modelValue="onChangeDateRange"
        />
      </BaseInputGroup>

      <div
        v-if="selectedRange.key === 'Quarter'"
        class="flex flex-row space-x-2 text-sm ml-1 mt-4"
      >
        <span
          class="text-primary-500 cursor-pointer hover:underline"
          :class="{ 'font-bold': isThisQuarterSelected }"
          @click="selectThisQuarter"
        >
          {{ $t('dateRange.this_quarter') }}
        </span>
        <span class="text-gray-300">|</span>
        <span
          class="text-primary-500 cursor-pointer hover:underline"
          :class="{ 'font-bold': isPreviousQuarterSelected }"
          @click="selectPreviousQuarter"
        >
          {{ $t('dateRange.previous_quarter') }}
        </span>
      </div>

      <div v-if="selectedRange.key === 'Quarter'" class="flex flex-col mt-4 lg:space-x-3 lg:flex-row">
        <BaseInputGroup :label="$t('reports.year')">
          <BaseMultiselect
            v-model="selectedYear"
            :options="years"
            :searchable="true"
            :placeholder="$t('reports.year')"
            @update:modelValue="onChangeQuarterYear"
          />
        </BaseInputGroup>

        <div
          class="
            hidden
            w-5
            h-0
            mx-4
            border border-gray-400 border-solid
            xl:block
          "
          style="margin-top: 2.5rem"
        />

        <BaseInputGroup :label="$t('reports.quarter')">
          <BaseMultiselect
            v-model="selectedQuarter"
            :options="quarters"
            value-prop="value"
            track-by="value"
            label="label"
            object
            :placeholder="$t('reports.quarter')"
            @update:modelValue="onChangeQuarterYear"
          />
        </BaseInputGroup>
      </div>

      <div class="flex flex-col mt-6 lg:space-x-3 lg:flex-row">
        <BaseInputGroup :label="$t('reports.taxes.from_date')">
          <BaseDatePicker v-model="formData.from_date" />
        </BaseInputGroup>

        <div
          class="
            hidden
            w-5
            h-0
            mx-4
            border border-gray-400 border-solid
            xl:block
          "
          style="margin-top: 2.5rem"
        />

        <BaseInputGroup :label="$t('reports.taxes.to_date')">
          <BaseDatePicker v-model="formData.to_date" />
        </BaseInputGroup>
      </div>

      <BaseButton
        variant="primary-outline"
        class="content-center hidden mt-0 w-md md:flex md:mt-8"
        type="submit"
        @click.prevent="getReports"
      >
        {{ $t('reports.update_report') }}
      </BaseButton>
    </div>
    <div class="col-span-8">
      <iframe
        :src="getReportUrl"
        class="
          hidden
          w-full
          h-screen
          border-gray-100 border-solid
          rounded
          md:flex
        "
      />
      <a
        class="
          flex
          items-center
          justify-center
          h-10
          px-5
          py-1
          text-sm
          font-medium
          leading-none
          text-center text-white
          rounded
          whitespace-nowrap
          md:hidden
          bg-primary-500
        "
        @click="viewReportsPDF"
      >
        <BaseIcon name="DocumentTextIcon" class="h-5 mr-2" />
        <span>{{ $t('reports.view_pdf') }}</span>
      </a>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, reactive } from 'vue'
import moment from 'moment'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '@/scripts/admin/stores/global'
const globalStore = useGlobalStore()

globalStore.downloadReport = downloadReport

const { t } = useI18n()

const currentYear = new Date().getFullYear()
const years = computed(() => globalStore.availableYears)
const selectedYear = ref(currentYear)

const quarters = ref([
  { label: 'Q1', value: 1 },
  { label: 'Q2', value: 2 },
  { label: 'Q3', value: 3 },
  { label: 'Q4', value: 4 },
])
const selectedQuarter = ref(quarters.value[moment().quarter() - 1])

const isThisQuarterSelected = computed(() => {
  const current = moment()
  return (
    parseInt(selectedYear.value) === parseInt(current.year()) &&
    parseInt(selectedQuarter.value.value) === parseInt(current.quarter())
  )
})

const isPreviousQuarterSelected = computed(() => {
  const prev = moment().subtract(1, 'quarter')
  return (
    parseInt(selectedYear.value) === parseInt(prev.year()) &&
    parseInt(selectedQuarter.value.value) === parseInt(prev.quarter())
  )
})

const dateRange = reactive([
  {
    label: t('dateRange.today'),
    key: 'Today',
  },
  {
    label: t('dateRange.this_week'),
    key: 'This Week',
  },
  {
    label: t('dateRange.this_month'),
    key: 'This Month',
  },
  {
    label: t('dateRange.this_year'),
    key: 'This Year',
  },
  {
    label: t('dateRange.previous_week'),
    key: 'Previous Week',
  },
  {
    label: t('dateRange.previous_month'),
    key: 'Previous Month',
  },
  {
    label: t('dateRange.previous_year'),
    key: 'Previous Year',
  },
  {
    label: t('dateRange.quarter'),
    key: 'Quarter',
  },
  {
    label: t('dateRange.custom'),
    key: 'Custom',
  },
])

const selectedRange = ref(dateRange[2])

const formData = reactive({
  from_date: moment().startOf('month').format('YYYY-MM-DD').toString(),
  to_date: moment().endOf('month').format('YYYY-MM-DD').toString(),
})

let url = ref(null)

const getReportUrl = computed(() => {
  return url.value
})
const companyStore = useCompanyStore()

const getSelectedCompany = computed(() => {
  return companyStore.selectedCompany
})

let siteURL = ref(null)

onMounted(() => {
  siteURL.value = `/reports/tax-summary/${getSelectedCompany.value.unique_hash}`
  url.value = dateRangeUrl.value
  globalStore.fetchAvailableYears()
})

const dateRangeUrl = computed(() => {
  return `${siteURL.value}?from_date=${moment(formData.from_date).format(
    'YYYY-MM-DD'
  )}&to_date=${moment(formData.to_date).format('YYYY-MM-DD')}`
})

let range = ref(new Date())

watch(range.value, (newRange) => {
  formData.from_date = moment(newRange).startOf('year').toString()
  formData.to_date = moment(newRange).endOf('year').toString()
})

function getThisDate(type, time) {
  return moment()[type](time).format('YYYY-MM-DD')
}

function getPreDate(type, time) {
  return moment().subtract(1, time)[type](time).format('YYYY-MM-DD')
}

function selectPreviousQuarter() {
  let current = moment()
  let prev = current.subtract(1, 'quarter')
  selectedYear.value = prev.year()
  selectedQuarter.value = quarters.value[prev.quarter() - 1]
  onChangeQuarterYear()
}

function selectThisQuarter() {
  let current = moment()
  selectedYear.value = current.year()
  selectedQuarter.value = quarters.value[current.quarter() - 1]
  onChangeQuarterYear()
}

function onChangeQuarterYear() {
  if (selectedRange.value.key === 'Quarter') {
    let quarterValue = selectedQuarter.value.value
    let yearValue = selectedYear.value

    let startMonth = (quarterValue - 1) * 3
    formData.from_date = moment().year(yearValue).month(startMonth).startOf('month').format('YYYY-MM-DD')
    formData.to_date = moment().year(yearValue).month(startMonth + 2).endOf('month').format('YYYY-MM-DD')
  }
}

function onChangeDateRange() {
  let key = selectedRange.value.key

  switch (key) {
    case 'Quarter':
      onChangeQuarterYear()
      break
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

    case 'Previous Year':
      formData.from_date = getPreDate('startOf', 'year')
      formData.to_date = getPreDate('endOf', 'year')
      break

    default:
      break
  }
}

async function viewReportsPDF() {
  let data = await getReports()
  window.open(getReportUrl.value, '_blank')
  return data
}

function getReports() {
  url.value = dateRangeUrl.value
  return true
}

function downloadReport() {
  if (!getReports()) {
    return false
  }

  window.open(getReportUrl.value + '&download=true')
  setTimeout(() => {
    url.value = dateRangeUrl.value
  }, 200)
}
</script>
