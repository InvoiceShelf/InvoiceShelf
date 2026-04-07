<template>
  <BasePage>
    <BasePageHeader>
      <template #default>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-semibold text-heading">
            {{ $t('invoices.title') }}
          </h1>
          <BaseDropdown position="bottom-start" width-class="w-44">
            <template #activator>
              <button
                class="flex items-center gap-1 px-2 py-1 text-sm font-medium text-muted hover:text-heading rounded-md hover:bg-surface-secondary transition-colors"
              >
                <span class="text-xs text-primary-500 bg-primary-50 px-2 py-0.5 rounded-full">
                  {{ viewMode === 'one-time' ? $t('invoices.one_time') : $t('recurring_invoices.recurring') }}
                </span>
                <BaseIcon name="ChevronDownIcon" class="w-4 h-4 text-muted" />
              </button>
            </template>
            <BaseDropdownItem
              :class="{ 'bg-primary-50 text-primary-600': viewMode === 'one-time' }"
              @click="setViewMode('one-time')"
            >
              <BaseIcon name="DocumentTextIcon" class="w-4 h-4 mr-2 text-subtle" />
              {{ $t('invoices.one_time') }}
              <BaseIcon v-if="viewMode === 'one-time'" name="CheckIcon" class="w-4 h-4 ml-auto text-primary-500" />
            </BaseDropdownItem>
            <BaseDropdownItem
              v-if="canViewRecurring"
              :class="{ 'bg-primary-50 text-primary-600': viewMode === 'recurring' }"
              @click="setViewMode('recurring')"
            >
              <BaseIcon name="ArrowPathIcon" class="w-4 h-4 mr-2 text-subtle" />
              {{ $t('recurring_invoices.recurring') }}
              <BaseIcon v-if="viewMode === 'recurring'" name="CheckIcon" class="w-4 h-4 ml-auto text-primary-500" />
            </BaseDropdownItem>
          </BaseDropdown>
        </div>
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
          <BaseBreadcrumbItem :title="$t('invoices.invoice', 2)" to="#" active />
        </BaseBreadcrumb>
      </template>

      <template #actions>
        <BaseButton
          v-show="viewMode === 'one-time' ? invoiceStore.invoiceTotalCount : recurringInvoiceStore.totalRecurringInvoices"
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
          :to="viewMode === 'recurring' ? 'invoices/create?recurring=1' : 'invoices/create'"
        >
          <BaseButton variant="primary" class="ml-4">
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('invoices.new_invoice') }}
          </BaseButton>
        </router-link>
      </template>
    </BasePageHeader>

    <!-- Filters (one-time) -->
    <BaseFilterWrapper
      v-show="showFilters && viewMode === 'one-time'"
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

    <!-- Filters (recurring) -->
    <BaseFilterWrapper
      v-show="showFilters && viewMode === 'recurring'"
      @clear="clearRecurringFilter"
    >
      <BaseInputGroup :label="$t('customers.customer', 1)">
        <BaseCustomerSelectInput
          v-model="recurringFilters.customer_id"
          :placeholder="$t('customers.type_or_click')"
          value-prop="id"
          label="name"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('recurring_invoices.status')">
        <BaseMultiselect
          v-model="recurringFilters.status"
          :options="recurringStatusList"
          searchable
          :placeholder="$t('general.select_a_status')"
          @update:model-value="setRecurringActiveTab"
          @remove="clearRecurringStatusSearch()"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('general.from')">
        <BaseDatePicker
          v-model="recurringFilters.from_date"
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
          v-model="recurringFilters.to_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- One-time invoices section -->
    <template v-if="viewMode === 'one-time'">
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
          class="relative flex items-center justify-between mt-5 list-none"
        >
          <BaseTabGroup @change="setStatusFilter">
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
          class="mt-4"
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
            <router-link
              v-if="row.data.customer?.id"
              :to="`/admin/customers/${row.data.customer.id}/view`"
              class="font-medium text-primary-500 hover:text-primary-600"
            >
              {{ row.data.customer.name }}
            </router-link>
            <span v-else>{{ row.data.customer?.name ?? '-' }}</span>
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
              :can-create-payment="canCreatePayment"
              :can-create-estimate="canCreateEstimate"
            />
          </template>
        </BaseTable>
      </div>
    </template>

    <!-- Recurring invoices section -->
    <template v-else-if="canViewRecurring">
      <!-- Empty State -->
      <BaseEmptyPlaceholder
        v-show="showRecurringEmptyScreen"
        :title="$t('recurring_invoices.no_invoices')"
        :description="$t('recurring_invoices.list_of_invoices')"
      >
        <template v-if="canCreate" #actions>
          <BaseButton
            variant="primary-outline"
            @click="$router.push('/admin/invoices/create?recurring=1')"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('recurring_invoices.add_new_invoice') }}
          </BaseButton>
        </template>
      </BaseEmptyPlaceholder>

      <div v-show="!showRecurringEmptyScreen" class="relative table-container">
        <!-- Recurring tabs -->
        <div class="relative flex items-center justify-between mt-5 list-none">
          <BaseTabGroup @change="setRecurringStatusFilter">
            <BaseTab :title="$t('recurring_invoices.all')" filter="ALL" />
            <BaseTab :title="$t('recurring_invoices.active')" filter="ACTIVE" />
            <BaseTab :title="$t('recurring_invoices.on_hold')" filter="ON_HOLD" />
          </BaseTabGroup>

          <BaseDropdown
            v-if="recurringInvoiceStore.selectedRecurringInvoices.length && canRecurringDelete"
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

            <BaseDropdownItem @click="removeMultipleRecurringInvoices">
              <BaseIcon name="TrashIcon" class="mr-3 text-body" />
              {{ $t('general.delete') }}
            </BaseDropdownItem>
          </BaseDropdown>
        </div>

        <!-- Recurring table -->
        <BaseTable
          ref="recurringTableRef"
          :data="fetchRecurringData"
          :columns="recurringColumns"
          :placeholder-count="recurringInvoiceStore.totalRecurringInvoices >= 20 ? 10 : 5"
          class="mt-4"
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
                v-model="recurringSelectField"
                :value="row.data.id"
              />
            </div>
          </template>

          <!-- starts_at column -->
          <template #cell-starts_at="{ row }">
            {{ row.data.formatted_starts_at }}
          </template>

          <!-- customer column -->
          <template #cell-customer="{ row }">
            <router-link
              v-if="row.data.customer?.id"
              :to="`/admin/customers/${row.data.customer.id}/view`"
              class="flex flex-col"
            >
              <span class="font-medium text-primary-500 hover:text-primary-600">
                {{ row.data.customer.name }}
              </span>
              <span v-if="row.data.customer.contact_name" class="text-xs text-subtle">
                {{ row.data.customer.contact_name }}
              </span>
            </router-link>
            <span v-else>-</span>
          </template>

          <!-- frequency column -->
          <template #cell-frequency="{ row }">
            {{ getFrequencyLabel(row.data.frequency) }}
          </template>

          <!-- status column -->
          <template #cell-status="{ row }">
            <BaseRecurringInvoiceStatusBadge :status="row.data.status" class="px-3 py-1">
              <BaseRecurringInvoiceStatusLabel :status="row.data.status" />
            </BaseRecurringInvoiceStatusBadge>
          </template>

          <!-- total column -->
          <template #cell-total="{ row }">
            <BaseFormatMoney
              :amount="row.data.total"
              :currency="row.data.customer?.currency"
            />
          </template>

          <!-- actions column -->
          <template v-if="hasRecurringAtLeastOneAbility" #cell-actions="{ row }">
            <RecurringInvoiceDropdown
              :row="row.data"
              :table="recurringTableRef"
              :can-edit="canRecurringEdit"
              :can-view="canRecurringView"
              :can-delete="canRecurringDelete"
            />
          </template>
        </BaseTable>
      </div>
    </template>
  </BasePage>

  <SendInvoiceModal />
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { debouncedWatch } from '@vueuse/core'
import { useInvoiceStore } from '../store'
import { useRecurringInvoiceStore } from '../../recurring-invoices/store'
import InvoiceDropdown from '../components/InvoiceDropdown.vue'
import SendInvoiceModal from '../components/SendInvoiceModal.vue'
import RecurringInvoiceDropdown from '../../recurring-invoices/components/RecurringInvoiceDropdown.vue'
import { useUserStore } from '../../../../stores/user.store'
import { useDialogStore } from '../../../../stores/dialog.store'
import type { Invoice } from '../../../../types/domain/invoice'
import type { RecurringInvoice } from '../../../../types/domain/recurring-invoice'

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

const ABILITIES = {
  CREATE: 'create-invoice',
  EDIT: 'edit-invoice',
  VIEW: 'view-invoice',
  DELETE: 'delete-invoice',
  SEND: 'send-invoice',
} as const

const RECURRING_ABILITIES = {
  CREATE: 'create-recurring-invoice',
  EDIT: 'edit-recurring-invoice',
  VIEW: 'view-recurring-invoice',
  DELETE: 'delete-recurring-invoice',
} as const

const invoiceStore = useInvoiceStore()
const recurringInvoiceStore = useRecurringInvoiceStore()
const userStore = useUserStore()
const dialogStore = useDialogStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

// ----------------------------------------------------------------
// Recurring invoice ability
// ----------------------------------------------------------------

const canViewRecurring = computed<boolean>(() => {
  return userStore.hasAbilities(RECURRING_ABILITIES.VIEW)
})

// ----------------------------------------------------------------
// View mode toggle
// ----------------------------------------------------------------

const storedViewMode = localStorage.getItem('invoiceViewMode') as 'one-time' | 'recurring' | null
const viewMode = ref<'one-time' | 'recurring'>(
  storedViewMode === 'recurring' && !canViewRecurring.value ? 'one-time' : (storedViewMode ?? 'one-time')
)

onMounted(() => {
  if (route.query.view === 'recurring' && canViewRecurring.value) {
    viewMode.value = 'recurring'
  }
  recurringInvoiceStore.initFrequencies(t)
})

function setViewMode(mode: 'one-time' | 'recurring'): void {
  if (mode === 'recurring' && !canViewRecurring.value) return
  viewMode.value = mode
  localStorage.setItem('invoiceViewMode', mode)
  router.replace({
    query: mode === 'recurring' ? { view: 'recurring' } : {},
  })
}

// ----------------------------------------------------------------
// One-time invoice state
// ----------------------------------------------------------------

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

const canCreate = computed<boolean>(() => {
  return props.canCreate || userStore.hasAbilities(ABILITIES.CREATE)
})

const canEdit = computed<boolean>(() => {
  return props.canEdit || userStore.hasAbilities(ABILITIES.EDIT)
})

const canView = computed<boolean>(() => {
  return props.canView || userStore.hasAbilities(ABILITIES.VIEW)
})

const canDelete = computed<boolean>(() => {
  return props.canDelete || userStore.hasAbilities(ABILITIES.DELETE)
})

const canSend = computed<boolean>(() => {
  return props.canSend || userStore.hasAbilities(ABILITIES.SEND)
})

const canCreatePayment = computed<boolean>(() => {
  return userStore.hasAbilities('create-payment')
})

const canCreateEstimate = computed<boolean>(() => {
  return userStore.hasAbilities('create-estimate')
})

const hasAtLeastOneAbility = computed<boolean>(() => {
  return canDelete.value || canEdit.value || canView.value || canSend.value
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
  if (recurringInvoiceStore.selectAllField) {
    recurringInvoiceStore.selectAllRecurringInvoices()
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

function removeMultipleInvoices(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('invoices.confirm_delete'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await invoiceStore.deleteMultipleInvoices()
      if (response.data.success) {
        refreshTable()
        invoiceStore.$patch((state) => {
          state.selectedInvoices = []
          state.selectAllField = false
        })
      }
    }
  })
}

function toggleFilter(): void {
  if (showFilters.value) {
    if (viewMode.value === 'one-time') {
      clearFilter()
    } else {
      clearRecurringFilter()
    }
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

// ----------------------------------------------------------------
// Recurring invoice state
// ----------------------------------------------------------------

const recurringTableRef = ref<{ refresh: () => void } | null>(null)
const isRecurringRequestOngoing = ref<boolean>(true)
const recurringActiveTab = ref<string>('recurring_invoices.all')

const recurringStatusList = ref<StatusOption[]>([
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

const recurringFilters = reactive<RecurringInvoiceFilters>({
  customer_id: '',
  status: '',
  from_date: '',
  to_date: '',
})

const showRecurringEmptyScreen = computed<boolean>(
  () =>
    !recurringInvoiceStore.totalRecurringInvoices &&
    !isRecurringRequestOngoing.value,
)

const recurringSelectField = computed<number[]>({
  get: () => recurringInvoiceStore.selectedRecurringInvoices,
  set: (value: number[]) => {
    recurringInvoiceStore.selectRecurringInvoice(value)
  },
})

const canRecurringEdit = computed<boolean>(() => {
  return userStore.hasAbilities(RECURRING_ABILITIES.EDIT)
})

const canRecurringView = computed<boolean>(() => {
  return userStore.hasAbilities(RECURRING_ABILITIES.VIEW)
})

const canRecurringDelete = computed<boolean>(() => {
  return userStore.hasAbilities(RECURRING_ABILITIES.DELETE)
})

const hasRecurringAtLeastOneAbility = computed<boolean>(() => {
  return canRecurringDelete.value || canRecurringEdit.value || canRecurringView.value
})

const recurringColumns = computed<TableColumn[]>(() => [
  {
    key: 'checkbox',
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
    sortable: false,
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

debouncedWatch(recurringFilters, () => setRecurringFilters(), { debounce: 500 })

interface RecurringFetchResult {
  data: RecurringInvoice[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

async function fetchRecurringData({
  page,
  sort,
}: FetchParams): Promise<RecurringFetchResult> {
  const data = {
    customer_id: recurringFilters.customer_id
      ? Number(recurringFilters.customer_id)
      : undefined,
    status: recurringFilters.status || undefined,
    from_date: recurringFilters.from_date || undefined,
    to_date: recurringFilters.to_date || undefined,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isRecurringRequestOngoing.value = true
  const response = await recurringInvoiceStore.fetchRecurringInvoices(
    data as never,
  )
  isRecurringRequestOngoing.value = false

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

function getFrequencyLabel(frequencyFormat: string): string {
  const frequencyObj = recurringInvoiceStore.frequencies.find(
    (f) => f.value === frequencyFormat,
  )
  return frequencyObj ? frequencyObj.label : `CUSTOM: ${frequencyFormat}`
}

function refreshRecurringTable(): void {
  recurringTableRef.value?.refresh()
}

function setRecurringStatusFilter(val: { title: string }): void {
  if (recurringActiveTab.value === val.title) return
  recurringActiveTab.value = val.title

  switch (val.title) {
    case t('recurring_invoices.active'):
      recurringFilters.status = 'ACTIVE'
      break
    case t('recurring_invoices.on_hold'):
      recurringFilters.status = 'ON_HOLD'
      break
    default:
      recurringFilters.status = ''
      break
  }
}

function setRecurringFilters(): void {
  recurringInvoiceStore.$patch((state) => {
    state.selectedRecurringInvoices = []
    state.selectAllField = false
  })
  refreshRecurringTable()
}

function clearRecurringFilter(): void {
  recurringFilters.customer_id = ''
  recurringFilters.status = ''
  recurringFilters.from_date = ''
  recurringFilters.to_date = ''
  recurringActiveTab.value = t('recurring_invoices.all')
}

function clearRecurringStatusSearch(): void {
  recurringFilters.status = ''
  refreshRecurringTable()
}

function setRecurringActiveTab(val: string): void {
  const tabMap: Record<string, string> = {
    ACTIVE: t('recurring_invoices.active'),
    ON_HOLD: t('recurring_invoices.on_hold'),
    ALL: t('recurring_invoices.all'),
  }
  recurringActiveTab.value = tabMap[val] ?? t('recurring_invoices.all')
}

function removeMultipleRecurringInvoices(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('invoices.confirm_delete'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response =
        await recurringInvoiceStore.deleteMultipleRecurringInvoices()
      if (response.data.success) {
        refreshRecurringTable()
        recurringInvoiceStore.$patch((state) => {
          state.selectedRecurringInvoices = []
          state.selectAllField = false
        })
      }
    }
  })
}
</script>
