<template>
  <BasePage>
    <BasePageHeader :title="$t('payments.title')">
      <template #breadcrumbs>
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('general.home')"
            :to="`/${store.companySlug}/customer/dashboard`"
          />
          <BaseBreadcrumbItem :title="$t('payments.payment', 2)" to="#" active />
        </BaseBreadcrumb>
      </template>

      <template #actions>
        <BaseButton
          v-show="store.totalPayments"
          variant="primary-outline"
          @click="toggleFilter"
        >
          {{ $t('general.filter') }}
          <template #right="slotProps">
            <BaseIcon
              v-if="!showFilters"
              :class="slotProps.class"
              name="FunnelIcon"
            />
            <BaseIcon v-else :class="slotProps.class" name="XMarkIcon" />
          </template>
        </BaseButton>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper v-show="showFilters" @clear="clearFilter">
      <BaseInputGroup :label="$t('payments.payment_number')" class="px-3">
        <BaseInput
          v-model="filters.payment_number"
          :placeholder="$t('payments.payment_number')"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('payments.payment_mode')" class="px-3">
        <BaseMultiselect
          v-model="filters.payment_mode"
          value-prop="id"
          track-by="name"
          :filter-results="false"
          label="name"
          resolve-on-load
          :delay="100"
          searchable
          :options="searchPaymentModes"
          :placeholder="$t('payments.payment_mode')"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-if="showEmptyScreen"
      :title="$t('payments.no_payments')"
      :description="$t('payments.list_of_payments')"
    />

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="tableRef"
        :data="fetchData"
        :columns="paymentColumns"
        :placeholder-count="store.totalPayments >= 20 ? 10 : 5"
        class="mt-10"
      >
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

        <template #cell-payment_mode="{ row }">
          <span>
            {{ row.data.payment_method?.name ?? $t('payments.not_selected') }}
          </span>
        </template>

        <template #cell-invoice_number="{ row }">
          <span>
            {{ row.data.invoice?.invoice_number ?? $t('payments.no_invoice') }}
          </span>
        </template>

        <template #cell-amount="{ row }">
          <BaseFormatMoney
            :amount="row.data.amount"
            :currency="store.currency"
          />
        </template>

        <template #cell-actions="{ row }">
          <BaseDropdown>
            <template #activator>
              <BaseIcon name="EllipsisHorizontalIcon" class="w-5 text-muted" />
            </template>
            <router-link :to="`payments/${row.data.id}/view`">
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
import type { Payment, PaymentMethod } from '../../../types/domain/payment'

const store = useCustomerPortalStore()
const { t } = useI18n()

const tableRef = ref<{ refresh: () => void } | null>(null)
const isFetchingInitialData = ref<boolean>(true)
const showFilters = ref<boolean>(false)

interface PaymentFilters {
  payment_mode: string | number
  payment_number: string
}

const filters = reactive<PaymentFilters>({
  payment_mode: '',
  payment_number: '',
})

const showEmptyScreen = computed<boolean>(
  () => !store.totalPayments && !isFetchingInitialData.value,
)

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  sortable?: boolean
}

const paymentColumns = computed<TableColumn[]>(() => [
  {
    key: 'payment_date',
    label: t('payments.date'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  { key: 'payment_number', label: t('payments.payment_number') },
  { key: 'payment_mode', label: t('payments.payment_mode') },
  { key: 'invoice_number', label: t('invoices.invoice_number') },
  { key: 'amount', label: t('payments.amount') },
  {
    key: 'actions',
    label: '',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

debouncedWatch(filters, () => refreshTable(), { debounce: 500 })

async function searchPaymentModes(search: string): Promise<PaymentMethod[]> {
  return store.fetchPaymentModes(search)
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
    payment_method_id: filters.payment_mode || undefined,
    payment_number: filters.payment_number || undefined,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true
  const response = await store.fetchPayments(data)
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
  filters.payment_mode = ''
  filters.payment_number = ''
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}
</script>
