<template>
  <BasePage>
    <BasePageHeader :title="$t('estimates.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem
          :title="$t('general.home')"
          :to="`/${store.companySlug}/customer/dashboard`"
        />
        <BaseBreadcrumbItem :title="$t('estimates.estimate', 2)" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-if="store.totalEstimates"
          variant="primary-outline"
          @click="toggleFilter"
        >
          {{ $t('general.filter') }}
          <template #right="slotProps">
            <BaseIcon
              v-if="!showFilters"
              name="FunnelIcon"
              :class="slotProps.class"
            />
            <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
          </template>
        </BaseButton>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper v-show="showFilters" @clear="clearFilter">
      <BaseInputGroup :label="$t('estimates.status')" class="px-3">
        <BaseSelectInput
          v-model="filters.status"
          :options="statusOptions"
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
          <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-muted" />
          <BaseIcon name="HashtagIcon" class="h-5 mr-3 text-body" />
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
    />

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="tableRef"
        :data="fetchData"
        :columns="estimateColumns"
        :placeholder-count="store.totalEstimates >= 20 ? 10 : 5"
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
              <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-muted" />
            </template>
            <router-link :to="`estimates/${row.data.id}/view`">
              <BaseDropdownItem>
                <BaseIcon name="EyeIcon" class="h-5 mr-3 text-body" />
                {{ $t('general.view') }}
              </BaseDropdownItem>
            </router-link>
          </BaseDropdown>
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { debouncedWatch } from '@vueuse/core'
import { useCustomerPortalStore } from '../store'
import type { Estimate } from '../../../types/domain/estimate'

const store = useCustomerPortalStore()
const { t } = useI18n()

const tableRef = ref<{ refresh: () => void } | null>(null)
const isFetchingInitialData = ref<boolean>(true)
const showFilters = ref<boolean>(false)

interface StatusOption {
  label: string
  value: string
}

const statusOptions = ref<StatusOption[]>([
  { label: t('estimates.draft'), value: 'DRAFT' },
  { label: t('estimates.sent'), value: 'SENT' },
  { label: t('estimates.viewed'), value: 'VIEWED' },
  { label: t('estimates.expired'), value: 'EXPIRED' },
  { label: t('estimates.accepted'), value: 'ACCEPTED' },
  { label: t('estimates.rejected'), value: 'REJECTED' },
])

interface EstimateFilters {
  status: string
  from_date: string
  to_date: string
  estimate_number: string
}

const filters = reactive<EstimateFilters>({
  status: '',
  from_date: '',
  to_date: '',
  estimate_number: '',
})

const showEmptyScreen = computed<boolean>(
  () => !store.totalEstimates && !isFetchingInitialData.value,
)

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  sortable?: boolean
}

const estimateColumns = computed<TableColumn[]>(() => [
  {
    key: 'estimate_date',
    label: t('estimates.date'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
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
])

debouncedWatch(filters, () => refreshTable(), { debounce: 500 })

interface FetchParams {
  page: number
  sort: { fieldName?: string; order?: string }
}

interface FetchResult {
  data: Estimate[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const data = {
    status: filters.status || undefined,
    estimate_number: filters.estimate_number || undefined,
    from_date: filters.from_date || undefined,
    to_date: filters.to_date || undefined,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true
  const response = await store.fetchEstimates(data)
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

function refreshTable(): void {
  tableRef.value?.refresh()
}

function clearFilter(): void {
  filters.status = ''
  filters.from_date = ''
  filters.to_date = ''
  filters.estimate_number = ''
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}
</script>
