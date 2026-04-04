<template>
  <BasePage>
    <BasePageHeader :title="$t('recurring_invoices.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('recurring_invoices.invoice', 2)"
          to="#"
          active
        />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-show="recurringInvoiceStore.totalRecurringInvoices"
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

        <router-link
          v-if="canCreate"
          to="recurring-invoices/create"
        >
          <BaseButton variant="primary" class="ml-4">
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('recurring_invoices.new_invoice') }}
          </BaseButton>
        </router-link>
      </template>
    </BasePageHeader>

    <!-- Filters -->
    <BaseFilterWrapper v-show="showFilters" @clear="clearFilter">
      <BaseInputGroup :label="$t('customers.customer', 1)">
        <BaseCustomerSelectInput
          v-model="filters.customer_id"
          :placeholder="$t('customers.type_or_click')"
          value-prop="id"
          label="name"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('recurring_invoices.status')">
        <BaseMultiselect
          v-model="filters.status"
          :options="statusList"
          searchable
          :placeholder="$t('general.select_a_status')"
          @update:model-value="setActiveTab"
          @remove="clearStatusSearch()"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('general.from')">
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

      <BaseInputGroup :label="$t('general.to')">
        <BaseDatePicker
          v-model="filters.to_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Empty State -->
    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('recurring_invoices.no_invoices')"
      :description="$t('recurring_invoices.list_of_invoices')"
    >
      <template v-if="canCreate" #actions>
        <BaseButton
          variant="primary-outline"
          @click="$router.push('/admin/recurring-invoices/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('recurring_invoices.add_new_invoice') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <!-- Table -->
    <div v-show="!showEmptyScreen" class="relative table-container">
      <div
        class="relative flex items-center justify-between h-10 mt-5 list-none border-b-2 border-line-default border-solid"
      >
        <BaseTabGroup
          class="-mb-5"
          :default-index="currentStatusIndex"
          @change="setStatusFilter"
        >
          <BaseTab :title="$t('recurring_invoices.all')" filter="ALL" />
          <BaseTab :title="$t('recurring_invoices.active')" filter="ACTIVE" />
          <BaseTab
            :title="$t('recurring_invoices.on_hold')"
            filter="ON_HOLD"
          />
        </BaseTabGroup>

        <BaseDropdown
          v-if="recurringInvoiceStore.selectedRecurringInvoices.length"
          class="absolute float-right"
        >
          <template #activator>
            <span
              class="flex text-sm font-medium cursor-pointer select-none text-primary-400"
            >
              {{ $t('general.actions') }}
              <BaseIcon name="ChevronDownIcon" class="h-5" />
            </span>
          </template>

          <BaseDropdownItem @click="removeMultipleRecurringInvoices()">
            <BaseIcon name="TrashIcon" class="mr-3 text-body" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <BaseTable
        ref="tableRef"
        :data="fetchData"
        :columns="invoiceColumns"
        :placeholder-count="
          recurringInvoiceStore.totalRecurringInvoices >= 20 ? 10 : 5
        "
        class="mt-10"
      >
        <template #header>
          <div class="absolute items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="recurringInvoiceStore.selectAllField"
              variant="primary"
              @change="recurringInvoiceStore.selectAllRecurringInvoices"
            />
          </div>
        </template>

        <template #cell-checkbox="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="row.id"
              v-model="selectField"
              :value="row.data.id"
            />
          </div>
        </template>

        <template #cell-starts_at="{ row }">
          {{ row.data.formatted_starts_at }}
        </template>

        <template #cell-customer="{ row }">
          <router-link
            :to="{ path: `recurring-invoices/${row.data.id}/view` }"
          >
            <BaseText
              :text="row.data.customer.name"
              tag="span"
              class="font-medium text-primary-500 flex flex-col"
            />
            <BaseText
              :text="row.data.customer.contact_name ?? ''"
              tag="span"
              class="text-xs text-subtle"
            />
          </router-link>
        </template>

        <template #cell-frequency="{ row }">
          {{ getFrequencyLabel(row.data.frequency) }}
        </template>

        <template #cell-status="{ row }">
          <BaseRecurringInvoiceStatusBadge
            :status="row.data.status"
            class="px-3 py-1"
          >
            <BaseRecurringInvoiceStatusLabel :status="row.data.status" />
          </BaseRecurringInvoiceStatusBadge>
        </template>

        <template #cell-total="{ row }">
          <BaseFormatMoney
            :amount="row.data.total"
            :currency="row.data.customer.currency"
          />
        </template>

        <template v-if="hasAtLeastOneAbility" #cell-actions="{ row }">
          <RecurringInvoiceDropdown
            :row="row.data"
            :table="tableRef"
            :can-edit="canEdit"
            :can-view="canView"
            :can-delete="canDelete"
          />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, onUnmounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { debouncedWatch } from '@vueuse/core'
import { useRecurringInvoiceStore } from '../store'
import RecurringInvoiceDropdown from '../components/RecurringInvoiceDropdown.vue'
import type { RecurringInvoice } from '../../../../types/domain/recurring-invoice'

interface Props {
  canCreate?: boolean
  canEdit?: boolean
  canView?: boolean
  canDelete?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canCreate: false,
  canEdit: false,
  canView: false,
  canDelete: false,
})

const recurringInvoiceStore = useRecurringInvoiceStore()
const { t } = useI18n()

// Initialize frequencies with translations
recurringInvoiceStore.initFrequencies(t)

const tableRef = ref<{ refresh: () => void } | null>(null)
const showFilters = ref<boolean>(false)
const isRequestOngoing = ref<boolean>(true)
const activeTab = ref<string>('recurring-invoices.all')

interface StatusOption {
  label: string
  value: string
}

const statusList = ref<StatusOption[]>([
  { label: t('recurring_invoices.active'), value: 'ACTIVE' },
  { label: t('recurring_invoices.on_hold'), value: 'ON_HOLD' },
  { label: t('recurring_invoices.all'), value: 'ALL' },
])

interface RecurringInvoiceFilters {
  customer_id: string | number
  status: string
  from_date: string
  to_date: string
}

const filters = reactive<RecurringInvoiceFilters>({
  customer_id: '',
  status: '',
  from_date: '',
  to_date: '',
})

const showEmptyScreen = computed<boolean>(
  () =>
    !recurringInvoiceStore.totalRecurringInvoices && !isRequestOngoing.value,
)

const hasAtLeastOneAbility = computed<boolean>(() => {
  return props.canDelete || props.canEdit || props.canView
})

const selectField = computed<number[]>({
  get: () => recurringInvoiceStore.selectedRecurringInvoices,
  set: (value: number[]) => {
    recurringInvoiceStore.selectRecurringInvoice(value)
  },
})

const currentStatusIndex = computed<number>(() => {
  return statusList.value.findIndex(
    (status) => status.value === filters.status,
  )
})

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  sortable?: boolean
}

const invoiceColumns = computed<TableColumn[]>(() => [
  {
    key: 'checkbox',
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'starts_at',
    label: t('recurring_invoices.starts_at'),
    thClass: 'extra',
    tdClass: 'font-medium',
  },
  { key: 'customer', label: t('invoices.customer') },
  { key: 'frequency', label: t('recurring_invoices.frequency.title') },
  { key: 'status', label: t('invoices.status') },
  { key: 'total', label: t('invoices.total') },
  {
    key: 'actions',
    label: t('recurring_invoices.action'),
    tdClass: 'text-right text-sm font-medium',
    thClass: 'text-right',
    sortable: false,
  },
])

debouncedWatch(filters, () => setFilters(), { debounce: 500 })

onUnmounted(() => {
  if (recurringInvoiceStore.selectAllField) {
    recurringInvoiceStore.selectAllRecurringInvoices()
  }
})

function getFrequencyLabel(frequencyFormat: string): string {
  const frequencyObj = recurringInvoiceStore.frequencies.find(
    (f) => f.value === frequencyFormat,
  )
  return frequencyObj ? frequencyObj.label : `CUSTOM: ${frequencyFormat}`
}

function refreshTable(): void {
  tableRef.value?.refresh()
}

interface FetchParams {
  page: number
  filter: Record<string, unknown>
  sort: { fieldName?: string; order?: string }
}

interface FetchResult {
  data: RecurringInvoice[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

async function fetchData({
  page,
  sort,
}: FetchParams): Promise<FetchResult> {
  const data = {
    customer_id: filters.customer_id
      ? Number(filters.customer_id)
      : undefined,
    status: filters.status || undefined,
    from_date: filters.from_date || undefined,
    to_date: filters.to_date || undefined,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isRequestOngoing.value = true
  const response = await recurringInvoiceStore.fetchRecurringInvoices(
    data as never,
  )
  isRequestOngoing.value = false

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

function setStatusFilter(val: { title: string }): void {
  if (activeTab.value === val.title) return
  activeTab.value = val.title

  switch (val.title) {
    case t('recurring_invoices.active'):
      filters.status = 'ACTIVE'
      break
    case t('recurring_invoices.on_hold'):
      filters.status = 'ON_HOLD'
      break
    case t('recurring_invoices.all'):
      filters.status = 'ALL'
      break
  }
}

function setFilters(): void {
  recurringInvoiceStore.$patch((state) => {
    state.selectedRecurringInvoices = []
    state.selectAllField = false
  })
  refreshTable()
}

function clearFilter(): void {
  filters.customer_id = ''
  filters.status = ''
  filters.from_date = ''
  filters.to_date = ''
  activeTab.value = t('general.all')
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}

function clearStatusSearch(): void {
  filters.status = ''
  refreshTable()
}

function setActiveTab(val: string): void {
  const tabMap: Record<string, string> = {
    ACTIVE: t('recurring_invoices.active'),
    ON_HOLD: t('recurring_invoices.on_hold'),
    ALL: t('recurring_invoices.all'),
  }
  activeTab.value = tabMap[val] ?? t('general.all')
}

async function removeMultipleRecurringInvoices(): Promise<void> {
  const confirmed = window.confirm(t('invoices.confirm_delete'))
  if (!confirmed) return

  const res = await recurringInvoiceStore.deleteMultipleRecurringInvoices()
  if (res.data.success) {
    refreshTable()
    recurringInvoiceStore.$patch((state) => {
      state.selectedRecurringInvoices = []
      state.selectAllField = false
    })
  }
}
</script>
