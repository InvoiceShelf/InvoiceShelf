<template>
  <BasePage>
    <BasePageHeader :title="$t('invoices.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem
          :title="$t('general.home')"
          :to="`/${store.companySlug}/customer/dashboard`"
        />
        <BaseBreadcrumbItem :title="$t('invoices.invoice', 2)" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-show="store.totalInvoices"
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
      <BaseInputGroup :label="$t('invoices.status')" class="px-3">
        <BaseSelectInput
          v-model="filters.status"
          :options="statusOptions"
          searchable
          :allow-empty="false"
          :placeholder="$t('general.select_a_status')"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('invoices.invoice_number')"
        color="black-light"
        class="px-3 mt-2"
      >
        <BaseInput v-model="filters.invoice_number">
          <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-muted" />
          <BaseIcon name="HashtagIcon" class="h-5 ml-3 text-body" />
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
      :title="$t('invoices.no_invoices')"
      :description="$t('invoices.list_of_invoices')"
    />

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="tableRef"
        :data="fetchData"
        :columns="invoiceColumns"
        :placeholder-count="store.totalInvoices >= 20 ? 10 : 5"
        class="mt-10"
      >
        <template #cell-invoice_date="{ row }">
          {{ row.data.formatted_invoice_date }}
        </template>

        <template #cell-invoice_number="{ row }">
          <router-link
            :to="{ path: `invoices/${row.data.id}/view` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.invoice_number }}
          </router-link>
        </template>

        <template #cell-due_amount="{ row }">
          <BaseFormatMoney
            :amount="row.data.total"
            :currency="row.data.customer?.currency"
          />
        </template>

        <template #cell-status="{ row }">
          <BaseInvoiceStatusBadge :status="row.data.status" class="px-3 py-1">
            <BaseInvoiceStatusLabel :status="row.data.status" />
          </BaseInvoiceStatusBadge>
        </template>

        <template #cell-paid_status="{ row }">
          <BaseInvoiceStatusBadge :status="row.data.paid_status" class="px-3 py-1">
            <BaseInvoiceStatusLabel :status="row.data.paid_status" />
          </BaseInvoiceStatusBadge>
        </template>

        <template #cell-actions="{ row }">
          <BaseDropdown>
            <template #activator>
              <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-muted" />
            </template>
            <router-link :to="`invoices/${row.data.id}/view`">
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
import type { Invoice } from '../../../types/domain/invoice'

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
  { label: t('general.draft'), value: 'DRAFT' },
  { label: t('general.due'), value: 'DUE' },
  { label: t('general.sent'), value: 'SENT' },
  { label: t('invoices.viewed'), value: 'VIEWED' },
  { label: t('invoices.completed'), value: 'COMPLETED' },
])

interface InvoiceFilters {
  status: string
  from_date: string
  to_date: string
  invoice_number: string
}

const filters = reactive<InvoiceFilters>({
  status: '',
  from_date: '',
  to_date: '',
  invoice_number: '',
})

const showEmptyScreen = computed<boolean>(
  () => !store.totalInvoices && !isFetchingInitialData.value,
)

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  sortable?: boolean
}

const invoiceColumns = computed<TableColumn[]>(() => [
  {
    key: 'invoice_date',
    label: t('invoices.date'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  { key: 'invoice_number', label: t('invoices.number') },
  { key: 'status', label: t('invoices.status') },
  { key: 'paid_status', label: t('invoices.paid_status') },
  {
    key: 'due_amount',
    label: t('dashboard.recent_invoices_card.amount_due'),
  },
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
  data: Invoice[]
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
    invoice_number: filters.invoice_number || undefined,
    from_date: filters.from_date || undefined,
    to_date: filters.to_date || undefined,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true
  const response = await store.fetchInvoices(data)
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
  filters.invoice_number = ''
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}
</script>
