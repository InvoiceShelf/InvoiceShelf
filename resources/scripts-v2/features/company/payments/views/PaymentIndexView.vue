<template>
  <BasePage class="payments">
    <BasePageHeader :title="$t('payments.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('payments.payment', 2)"
          to="#"
          active
        />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-show="paymentStore.paymentTotalCount"
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

        <BaseButton
          v-if="canCreate"
          variant="primary"
          class="ml-4"
          @click="$router.push('/admin/payments/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('payments.add_payment') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <!-- Filters -->
    <BaseFilterWrapper :show="showFilters" class="mt-3" @clear="clearFilter">
      <BaseInputGroup :label="$t('payments.customer')">
        <BaseCustomerSelectInput
          v-model="filters.customer_id"
          :placeholder="$t('customers.type_or_click')"
          value-prop="id"
          label="name"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('payments.payment_number')">
        <BaseInput v-model="filters.payment_number">
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup>

      <BaseInputGroup :label="$t('payments.payment_mode')">
        <BaseMultiselect
          v-model="filters.payment_mode"
          value-prop="id"
          track-by="name"
          :filter-results="false"
          label="name"
          resolve-on-load
          :delay="500"
          searchable
          :options="searchPaymentMode"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Empty State -->
    <BaseEmptyPlaceholder
      v-if="showEmptyScreen"
      :title="$t('payments.no_payments')"
      :description="$t('payments.list_of_payments')"
    >
      <template v-if="canCreate" #actions>
        <BaseButton
          variant="primary-outline"
          @click="$router.push('/admin/payments/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('payments.add_new_payment') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <!-- Table -->
    <div v-show="!showEmptyScreen" class="relative table-container">
      <div class="relative flex items-center justify-end h-5">
        <BaseDropdown v-if="paymentStore.selectedPayments.length && canDelete">
          <template #activator>
            <span
              class="flex text-sm font-medium cursor-pointer select-none text-primary-400"
            >
              {{ $t('general.actions') }}
              <BaseIcon name="ChevronDownIcon" />
            </span>
          </template>
          <BaseDropdownItem @click="removeMultiplePayments">
            <BaseIcon name="TrashIcon" class="mr-3 text-body" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <BaseTable
        ref="tableRef"
        :data="fetchData"
        :columns="paymentColumns"
        :placeholder-count="paymentStore.paymentTotalCount >= 20 ? 10 : 5"
        class="mt-3"
      >
        <template #header>
          <div class="absolute items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="selectAllFieldStatus"
              variant="primary"
              @change="paymentStore.selectAllPayments"
            />
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="row.id"
              v-model="selectField"
              :value="row.data.id"
              variant="primary"
            />
          </div>
        </template>

        <template #cell-payment_date="{ row }">
          {{ row.data.formatted_payment_date }}
        </template>

        <template #cell-payment_number="{ row }">
          <router-link
            :to="{ path: `payments/${row.data.id}/view` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.payment_number }}
          </router-link>
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

        <template #cell-payment_mode="{ row }">
          <span>
            {{ row.data.payment_method ? row.data.payment_method.name : '-' }}
          </span>
        </template>

        <template #cell-invoice_number="{ row }">
          <router-link
            v-if="row.data.invoice?.id"
            :to="`/admin/invoices/${row.data.invoice.id}/view`"
            class="font-medium text-primary-500 hover:text-primary-600"
          >
            {{ row.data.invoice.invoice_number }}
          </router-link>
          <span v-else>-</span>
        </template>

        <template #cell-amount="{ row }">
          <BaseFormatMoney
            :amount="row.data.amount"
            :currency="row.data.customer.currency"
          />
        </template>

        <template v-if="hasAtLeastOneAbility" #cell-actions="{ row }">
          <PaymentDropdown
            :row="row.data"
            :table="tableRef"
            :can-edit="canEdit"
            :can-view="canView"
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
import { usePaymentStore } from '../store'
import PaymentDropdown from '../components/PaymentDropdown.vue'
import { useUserStore } from '../../../../stores/user.store'
import { useDialogStore } from '../../../../stores/dialog.store'
import type { Payment, PaymentMethod } from '../../../../types/domain/payment'

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
  CREATE: 'create-payment',
  EDIT: 'edit-payment',
  VIEW: 'view-payment',
  DELETE: 'delete-payment',
  SEND: 'send-payment',
} as const

const paymentStore = usePaymentStore()
const userStore = useUserStore()
const dialogStore = useDialogStore()
const { t } = useI18n()

const tableRef = ref<{ refresh: () => void } | null>(null)
const showFilters = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(true)

interface PaymentFilters {
  customer_id: string | number
  payment_mode: string | number
  payment_number: string
}

const filters = reactive<PaymentFilters>({
  customer_id: '',
  payment_mode: '',
  payment_number: '',
})

const showEmptyScreen = computed<boolean>(
  () => !paymentStore.paymentTotalCount && !isFetchingInitialData.value,
)

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

const hasAtLeastOneAbility = computed<boolean>(() => {
  return canDelete.value || canEdit.value || canView.value || canSend.value
})

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  sortable?: boolean
}

const paymentColumns = computed<TableColumn[]>(() => [
  {
    key: 'status',
    sortable: false,
    thClass: 'extra w-10',
    tdClass: 'text-left text-sm font-medium extra',
  },
  {
    key: 'payment_date',
    label: t('payments.date'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  { key: 'payment_number', label: t('payments.payment_number') },
  { key: 'name', label: t('payments.customer') },
  { key: 'payment_mode', label: t('payments.payment_mode') },
  { key: 'invoice_number', label: t('payments.invoice') },
  { key: 'amount', label: t('payments.amount') },
  {
    key: 'actions',
    label: '',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

const selectField = computed<number[]>({
  get: () => paymentStore.selectedPayments,
  set: (value: number[]) => {
    paymentStore.selectPayment(value)
  },
})

const selectAllFieldStatus = computed<boolean>({
  get: () => paymentStore.selectAllField,
  set: (value: boolean) => {
    paymentStore.setSelectAllState(value)
  },
})

debouncedWatch(filters, () => setFilters(), { debounce: 500 })

onUnmounted(() => {
  if (paymentStore.selectAllField) {
    paymentStore.selectAllPayments()
  }
})

paymentStore.fetchPaymentModes({ limit: 'all' })

async function searchPaymentMode(search: string): Promise<PaymentMethod[]> {
  const res = await paymentStore.fetchPaymentModes({ search })
  return res.data.data
}

interface FetchParams {
  page: number
  filter: Record<string, unknown>
  sort: { fieldName?: string; order?: string }
}

interface FetchResult {
  data: Payment[]
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
    payment_method_id: filters.payment_mode
      ? Number(filters.payment_mode)
      : undefined,
    payment_number: filters.payment_number || undefined,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true
  const response = await paymentStore.fetchPayments(data)
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

function setFilters(): void {
  refreshTable()
}

function clearFilter(): void {
  filters.customer_id = ''
  filters.payment_mode = ''
  filters.payment_number = ''
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}

function removeMultiplePayments(): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('payments.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        const response = await paymentStore.deleteMultiplePayments()
        if (response.data.success) {
          refreshTable()
        }
      }
    })
}
</script>
