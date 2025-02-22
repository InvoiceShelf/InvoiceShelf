<template>
  <BasePage>
    <!-- Page Header -->
    <BasePageHeader :title="$t('estimates.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem
          :title="$t('general.home')"
          :to="`/${globalStore.companySlug}/customer/dashboard`"
        />
        <BaseBreadcrumbItem
          :title="$t('estimates.estimate', 2)"
          to="#"
          active
        />
      </BaseBreadcrumb>
      <template #actions>
        <BaseButton
          v-if="estimateStore.totalEstimates"
          variant="primary-outline"
          @click="toggleFilter"
        >
          {{ $t('general.filter') }}
          <template #right="slotProps">
            <BaseIcon
              v-if="!showFilters"
              name="FilterIcon"
              :class="slotProps.class"
            />
            <BaseIcon v-else name="XIcon" :class="slotProps.class" />
          </template>
        </BaseButton>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper v-show="showFilters" @clear="clearFilter">
      <BaseInputGroup :label="$t('estimates.status')" class="px-3">
        <BaseSelectInput
          v-model="filters.status"
          :options="status"
          searchable
          :show-labels="false"
          :allow-empty="false"
          :placeholder="$t('general.select_a_status')"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('estimates.estimate_number')"
        color="black-light"
        class="px-3 mt-2"
      >
        <BaseInput v-model="filters.estimate_number">
          <BaseIcon name="DotsHorizontalIcon" class="h-5 text-gray-500" />
          <BaseIcon name="HashtagIcon" class="h-5 mr-3 text-gray-600" />
        </BaseInput>
      </BaseInputGroup>

      <BaseInputGroup :label="$t('general.from')" class="px-3">
        <BaseDatePicker
          v-model="filters.from_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <div
        class="hidden w-8 h-0 mx-4 border border-gray-400 border-solid xl:block"
        style="margin-top: 1.5rem"
      />

      <BaseInputGroup :label="$t('general.to')" class="px-3">
        <BaseDatePicker
          v-model="filters.to_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-if="showEmptyScreen"
      :title="$t('estimates.no_estimates')"
      :description="$t('estimates.list_of_estimates')"
    >
      <ObservatoryIcon class="mt-5 mb-4" />
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="table"
        :data="fetchData"
        :columns="estimateColumns"
        :placeholder-count="estimateStore.totalEstimates >= 20 ? 10 : 5"
        class="mt-10"
      >
        <template #cell-estimate_date="{ row }">
          {{ row.data.formatted_estimate_date }}
        </template>

        <template #cell-estimate_number="{ row }">
          <router-link
            :to="{ path: `estimates/${row.data.id}/view` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.estimate_number }}
          </router-link>
        </template>

        <template #cell-status="{ row }">
          <BaseEstimateStatusBadge :status="row.data.status" class="px-3 py-1">
            <BaseEstimateStatusLabel :status="row.data.status" />
          </BaseEstimateStatusBadge>
        </template>

        <template #cell-total="{ row }">
          <BaseFormatMoney :amount="row.data.total" />
        </template>

        <template #cell-actions="{ row }">
          <BaseDropdown>
            <template #activator>
              <BaseIcon name="DotsHorizontalIcon" class="h-5 text-gray-500" />
            </template>
            <router-link :to="`estimates/${row.data.id}/view`">
              <BaseDropdownItem>
                <BaseIcon name="EyeIcon" class="h-5 mr-3 text-gray-600" />
                {{ $t('general.view') }}
              </BaseDropdownItem>
            </router-link>
          </BaseDropdown>
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
import { debouncedWatch } from '@vueuse/core'
import BaseTable from '@/scripts/components/base/base-table/BaseTable.vue'
import { ref, computed, reactive, inject } from 'vue'
import { useGlobalStore } from '@/scripts/customer/stores/global'
import { useEstimateStore } from '@/scripts/customer/stores/estimate'
import { useRoute } from 'vue-router'
import ObservatoryIcon from '@/scripts/components/icons/empty/ObservatoryIcon.vue'
import { useI18n } from 'vue-i18n'
import BaseIcon from '@/scripts/components/base/BaseIcon.vue'
import BaseDropdownItem from '@/scripts/components/base/BaseDropdownItem.vue'
import BaseDropdown from '@/scripts/components/base/BaseDropdown.vue'
import BaseFormatMoney from '@/scripts/components/base/BaseFormatMoney.vue'
import BaseEstimateStatusLabel from '@/scripts/components/base/BaseEstimateStatusLabel.vue'
import BaseEstimateStatusBadge from '@/scripts/components/base/BaseEstimateStatusBadge.vue'
import BaseEmptyPlaceholder from '@/scripts/components/base/BaseEmptyPlaceholder.vue'
import BaseDatePicker from '@/scripts/components/base/BaseDatePicker.vue'
import BaseInputGroup from '@/scripts/components/base/BaseInputGroup.vue'
import BaseInput from '@/scripts/components/base/BaseInput.vue'
import BaseSelectInput from '@/scripts/components/base/BaseSelectInput.vue'
import BaseFilterWrapper from '@/scripts/components/base/BaseFilterWrapper.vue'
import BaseButton from '@/scripts/components/base/BaseButton.vue'
import BaseBreadcrumbItem from '@/scripts/components/base/BaseBreadcrumbItem.vue'
import BaseBreadcrumb from '@/scripts/components/base/BaseBreadcrumb.vue'
import BasePageHeader from '@/scripts/components/base/BasePageHeader.vue'
import BasePage from '@/scripts/components/base/BasePage.vue'

const { t } = useI18n()

// utils

const utils = inject('utils')
const route = useRoute()
//  Local state
const table = ref(null)
let showFilters = ref(false)
let isFetchingInitialData = ref(true)

const status = ref([
  {label: t('estimates.draft'), value: 'DRAFT'},
  {label: t('estimates.sent'), value: 'SENT'},
  {label: t('estimates.viewed'), value: 'VIEWED'},
  {label: t('estimates.expired'), value: 'EXPIRED'},
  {label: t('estimates.accepted'), value: 'ACCEPTED'},
  {label: t('estimates.rejected'), value: 'REJECTED'},
])
const filters = reactive({
  status: '',
  from_date: '',
  to_date: '',
  estimate_number: '',
})

// store
const globalStore = useGlobalStore()
const estimateStore = useEstimateStore()

// computed

const estimateColumns = computed(() => {
  return [
    {
      key: 'estimate_date',
      label: t('estimates.date'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    { key: 'estimate_number', label: t('estimates.number', 2) },
    { key: 'status', label: t('estimates.status') },
    { key: 'total', label: t('estimates.total') },
    {
      key: 'actions',
      thClass: 'text-right',
      tdClass: 'text-right text-sm font-medium',
      sortable: false,
    },
  ]
})

const showEmptyScreen = computed(() => {
  return !estimateStore.totalEstimates && !isFetchingInitialData.value
})

const currency = computed(() => {
  return globalStore.currency
})
// watch

debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 }
)

// methods

function refreshTable() {
  table.value.refresh()
}

function setFilters() {
  refreshTable()
}

function clearFilter() {
  filters.status = ''
  filters.from_date = ''
  filters.to_date = ''
  filters.estimate_number = ''
}

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }

  showFilters.value = !showFilters.value
}

async function fetchData({ page, sort }) {
  let data = {
    status: filters.status.value,
    estimate_number: filters.estimate_number,
    from_date: filters.from_date,
    to_date: filters.to_date,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true

  let response = await estimateStore.fetchEstimate(
    data,
    globalStore.companySlug
  )

  isFetchingInitialData.value = false
  return {
    data: response.data.data,
    pagination: {
      totalPages: response.data.meta.last_page,
      currentPage: page,
      totalCount: response.data.meta.total,
      limit: 10,
    },
  }
}
</script>
