<template>
  <BasePage>
    <BasePageHeader :title="$t('invoices.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('invoices.invoice', 2)" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-show="invoiceStore.invoiceTotalCount"
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

        <router-link v-if="canCreate" to="invoices/create">
          <BaseButton variant="primary" class="ml-4">
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('invoices.new_invoice') }}
          </BaseButton>
        </router-link>
      </template>
    </BasePageHeader>

    <!-- Filters -->
    <BaseFilterWrapper
      v-show="showFilters"
      :row-on-xl="true"
      @clear="clearFilter"
    >
      <BaseInputGroup :label="$t('customers.customer', 1)">
        <BaseCustomerSelectInput
          v-model="filters.customer_id"
          :placeholder="$t('customers.type_or_click')"
          value-prop="id"
          label="name"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('invoices.status')">
        <BaseMultiselect
          v-model="filters.status"
          :groups="true"
          :options="statusOptions"
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

      <BaseInputGroup :label="$t('general.to')" class="mt-2">
        <BaseDatePicker
          v-model="filters.to_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('invoices.invoice_number')">
        <BaseInput v-model="filters.invoice_number">
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Empty State -->
    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('invoices.no_invoices')"
      :description="$t('invoices.list_of_invoices')"
    >
      <template v-if="canCreate" #actions>
        <BaseButton
          variant="primary-outline"
          @click="$router.push('/admin/invoices/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('invoices.add_new_invoice') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <!-- Table -->
    <div v-show="!showEmptyScreen" class="relative table-container">
      <div
        class="relative flex items-center justify-between h-10 mt-5 list-none border-b-2 border-line-default border-solid"
      >
        <BaseTabGroup class="-mb-5" @change="setStatusFilter">
          <BaseTab :title="$t('general.all')" filter="" />
          <BaseTab :title="$t('general.draft')" filter="DRAFT" />
          <BaseTab :title="$t('general.sent')" filter="SENT" />
          <BaseTab :title="$t('general.due')" filter="DUE" />
        </BaseTabGroup>

        <BaseDropdown
          v-if="invoiceStore.selectedInvoices.length && canDelete"
          class="absolute float-right"
        >
          <template #activator>
            <span
              class="flex text-sm font-medium cursor-pointer select-none text-primary-400"
            >
              {{ $t('general.actions') }}
              <BaseIcon name="ChevronDownIcon" />
            </span>
          </template>

          <BaseDropdownItem @click="removeMultipleInvoices">
            <BaseIcon name="TrashIcon" class="mr-3 text-body" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <BaseTable
        ref="tableRef"
        :data="fetchData"
        :columns="invoiceColumns"
        :placeholder-count="invoiceStore.invoiceTotalCount >= 20 ? 10 : 5"
        :key="tableKey"
        class="mt-10"
      >
        <template #header>
          <div class="absolute items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="invoiceStore.selectAllField"
              variant="primary"
              @change="invoiceStore.selectAllInvoices"
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

        <template #cell-name="{ row }">
          <BaseText :text="row.data.customer.name" />
        </template>

        <template #cell-invoice_number="{ row }">
          <router-link
            :to="{ path: `invoices/${row.data.id}/view` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.invoice_number }}
          </router-link>
        </template>

        <template #cell-invoice_date="{ row }">
          {{ row.data.formatted_invoice_date }}
        </template>

        <template #cell-total="{ row }">
          <BaseFormatMoney
            :amount="row.data.total"
            :currency="row.data.customer.currency"
          />
        </template>

        <template #cell-status="{ row }">
          <BaseInvoiceStatusBadge :status="row.data.status" class="px-3 py-1">
            <BaseInvoiceStatusLabel :status="row.data.status" />
          </BaseInvoiceStatusBadge>
        </template>

        <template #cell-due_amount="{ row }">
          <div class="flex justify-between">
            <BaseFormatMoney
              :amount="row.data.due_amount"
              :currency="row.data.currency"
            />

            <BasePaidStatusBadge
              v-if="row.data.overdue"
              status="OVERDUE"
              class="px-1 py-0.5 ml-2"
            >
              {{ $t('invoices.overdue') }}
            </BasePaidStatusBadge>

            <BasePaidStatusBadge
              :status="row.data.paid_status"
              class="px-1 py-0.5 ml-2"
            >
              <BaseInvoiceStatusLabel :status="row.data.paid_status" />
            </BasePaidStatusBadge>
          </div>
        </template>

        <template v-if="hasAtLeastOneAbility" #cell-actions="{ row }">
          <InvoiceDropdown
            :row="row.data"
            :table="tableRef"
            :can-edit="canEdit"
            :can-view="canView"
            :can-create="canCreate"
            :can-delete="canDelete"
            :can-send="canSend"
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
import { useInvoiceStore } from '../store'
import InvoiceDropdown from '../components/InvoiceDropdown.vue'
import type { Invoice } from '../../../../types/domain/invoice'

interface Props {
  canCreate?: boolean
  canEdit?: boolean
  canView?: boolean
  canDelete?: boolean
  canSend?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canCreate: false,
  canEdit: false,
  canView: false,
  canDelete: false,
  canSend: false,
})

const invoiceStore = useInvoiceStore()
const { t } = useI18n()

const tableRef = ref<{ refresh: () => void } | null>(null)
const tableKey = ref<number>(0)
const showFilters = ref<boolean>(false)
const isRequestOngoing = ref<boolean>(true)
const activeTab = ref<string>('general.draft')

interface StatusOption {
  label: string
  value: string
}

interface StatusGroup {
  label: string
  options: StatusOption[]
}

const statusOptions = ref<StatusGroup[]>([
  {
    label: t('invoices.status'),
    options: [
      { label: t('general.draft'), value: 'DRAFT' },
      { label: t('general.due'), value: 'DUE' },
      { label: t('general.sent'), value: 'SENT' },
      { label: t('invoices.viewed'), value: 'VIEWED' },
      { label: t('invoices.completed'), value: 'COMPLETED' },
    ],
  },
  {
    label: t('invoices.paid_status'),
    options: [
      { label: t('invoices.unpaid'), value: 'UNPAID' },
      { label: t('invoices.paid'), value: 'PAID' },
      { label: t('invoices.partially_paid'), value: 'PARTIALLY_PAID' },
    ],
  },
])

interface InvoiceFilters {
  customer_id: string | number
  status: string
  from_date: string
  to_date: string
  invoice_number: string
}

const filters = reactive<InvoiceFilters>({
  customer_id: '',
  status: '',
  from_date: '',
  to_date: '',
  invoice_number: '',
})

const showEmptyScreen = computed<boolean>(
  () => !invoiceStore.invoiceTotalCount && !isRequestOngoing.value,
)

const selectField = computed<number[]>({
  get: () => invoiceStore.selectedInvoices,
  set: (value: number[]) => {
    invoiceStore.selectInvoice(value)
  },
})

const hasAtLeastOneAbility = computed<boolean>(() => {
  return props.canDelete || props.canEdit || props.canView || props.canSend
})

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  placeholderClass?: string
  sortable?: boolean
}

const invoiceColumns = computed<TableColumn[]>(() => [
  {
    key: 'checkbox',
    thClass: 'extra w-10',
    tdClass: 'font-medium text-heading',
    placeholderClass: 'w-10',
    sortable: false,
  },
  {
    key: 'invoice_date',
    label: t('invoices.date'),
    thClass: 'extra',
    tdClass: 'font-medium',
  },
  { key: 'invoice_number', label: t('invoices.number') },
  { key: 'name', label: t('invoices.customer') },
  { key: 'status', label: t('invoices.status') },
  {
    key: 'due_amount',
    label: t('dashboard.recent_invoices_card.amount_due'),
  },
  {
    key: 'total',
    label: t('invoices.total'),
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'actions',
    label: t('invoices.action'),
    tdClass: 'text-right text-sm font-medium',
    thClass: 'text-right',
    sortable: false,
  },
])

debouncedWatch(filters, () => setFilters(), { debounce: 500 })

onUnmounted(() => {
  if (invoiceStore.selectAllField) {
    invoiceStore.selectAllInvoices()
  }
})

function clearStatusSearch(): void {
  filters.status = ''
  refreshTable()
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
    customer_id: filters.customer_id ? Number(filters.customer_id) : undefined,
    status: filters.status || undefined,
    from_date: filters.from_date || undefined,
    to_date: filters.to_date || undefined,
    invoice_number: filters.invoice_number || undefined,
    orderByField: sort.fieldName || 'created_at',
    orderBy: (sort.order || 'desc') as 'asc' | 'desc',
    page,
  }

  isRequestOngoing.value = true
  const response = await invoiceStore.fetchInvoices(data)
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
    case t('general.draft'):
      filters.status = 'DRAFT'
      break
    case t('general.sent'):
      filters.status = 'SENT'
      break
    case t('general.due'):
      filters.status = 'DUE'
      break
    default:
      filters.status = ''
      break
  }
}

function setFilters(): void {
  invoiceStore.$patch((state) => {
    state.selectedInvoices = []
    state.selectAllField = false
  })
  tableKey.value += 1
  refreshTable()
}

function clearFilter(): void {
  filters.customer_id = ''
  filters.status = ''
  filters.from_date = ''
  filters.to_date = ''
  filters.invoice_number = ''
  activeTab.value = t('general.all')
}

async function removeMultipleInvoices(): Promise<void> {
  const confirmed = window.confirm(t('invoices.confirm_delete'))
  if (!confirmed) return

  const res = await invoiceStore.deleteMultipleInvoices()
  if (res.data.success) {
    refreshTable()
    invoiceStore.$patch((state) => {
      state.selectedInvoices = []
      state.selectAllField = false
    })
  }
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}

function setActiveTab(val: string): void {
  const tabMap: Record<string, string> = {
    DRAFT: t('general.draft'),
    SENT: t('general.sent'),
    DUE: t('general.due'),
    COMPLETED: t('invoices.completed'),
    PAID: t('invoices.paid'),
    UNPAID: t('invoices.unpaid'),
    PARTIALLY_PAID: t('invoices.partially_paid'),
    VIEWED: t('invoices.viewed'),
  }
  activeTab.value = tabMap[val] ?? t('general.all')
}
</script>
