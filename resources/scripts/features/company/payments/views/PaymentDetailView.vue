<template>
  <div class="flex min-h-full">

    <BasePage class="min-w-0">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('payments.payment', 2)" to="/admin/payments" />
        </BaseBreadcrumb>

        <template #actions>
          <BaseButton
            v-if="canSend"
            :content-loading="isFetching"
            variant="primary"
            @click="onPaymentSend"
          >
            <template #left="slotProps">
              <BaseIcon name="PaperAirplaneIcon" :class="slotProps.class" />
            </template>
            {{ $t('payments.send_payment_receipt') }}
          </BaseButton>

          <PaymentDropdown
            :content-loading="isFetching"
            :row="paymentData"
            :can-edit="canEdit"
            :can-view="canView"
            :can-delete="canDelete"
            :can-send="canSend"
          />
        </template>
      </BasePageHeader>

      <!-- What the receipt says, without opening it -->
      <BaseStatStrip v-if="currentPayment.id" :columns="allocatedInvoices.length ? 5 : 4">
        <BaseStat :label="$t('payments.amount')" emphasis>
          <BaseFormatMoney :amount="currentPayment.amount" :currency="paymentCurrency" />
        </BaseStat>
        <BaseStat :label="$t('payments.date')">
          {{ currentPayment.formatted_payment_date }}
        </BaseStat>
        <BaseStat :label="$t('payments.payment_mode')">
          {{ currentPayment.payment_method?.name || '-' }}
        </BaseStat>
        <BaseStat v-if="allocatedInvoices.length" :label="$t('payments.invoice')">
          <template v-for="(invoice, index) in allocatedInvoices" :key="invoice.id">
            <span v-if="index > 0">, </span>
            <router-link :to="`/admin/invoices/${invoice.id}/view`" class="hover:text-primary-600">
              {{ invoice.invoice_number }}
            </router-link>
          </template>
        </BaseStat>
        <BaseStat :label="$t('payments.customer')" :wide="!allocatedInvoices.length">
          <router-link
            v-if="currentPayment.customer?.id"
            :to="`/admin/customers/${currentPayment.customer.id}/view`"
            class="hover:text-primary-600"
          >
            {{ currentPayment.customer.name }}
          </router-link>
        </BaseStat>
      </BaseStatStrip>

      <BaseCard v-if="currentPayment.id">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 mb-2">
          <h2 class="font-semibold text-section text-heading">{{ $t('payments.allocations') }}</h2>
          <div class="text-sm text-muted">
            {{ $t('payments.unapplied_credit') }}:
            <BaseFormatMoney :amount="paymentUnallocatedAmount" :currency="currentPayment.customer?.currency" class="font-medium text-heading" />
          </div>
        </div>
        <div v-if="paymentAllocations.length" class="divide-y divide-line-light">
          <div v-for="allocation in paymentAllocations" :key="allocation.id ?? allocation.invoice_id" class="flex items-center justify-between gap-4 py-3 text-sm">
            <div>
              <router-link v-if="allocation.invoice" :to="`/admin/invoices/${allocation.invoice.id}/view`" class="font-medium text-primary-600 hover:text-primary-700">
                {{ allocation.invoice.invoice_number }}
              </router-link>
              <span v-else class="font-medium text-heading">{{ $t('payments.invoice') }}</span>
              <span v-if="allocation.invoice?.formatted_invoice_date" class="block mt-0.5 text-xs text-muted">{{ allocation.invoice.formatted_invoice_date }}</span>
            </div>
            <BaseFormatMoney :amount="allocation.amount" :currency="currentPayment.customer?.currency" class="font-medium text-heading" />
          </div>
        </div>
        <p v-else class="py-2 text-sm text-muted">{{ $t('payments.no_allocations') }}</p>
      </BaseCard>

      <BasePdfPreview
        :src="shareableLink"
        :title="currentPayment.payment_number ? `${currentPayment.payment_number}.pdf` : ''"
      />
    </BasePage>

    <!-- The other payments, beside the one on screen (wide screens only) -->
    <RecordListPane
      ref="listPane"
      :search="searchData.searchText"
      :sort-options="sortOptions"
      :sort-field="searchData.orderByField"
      :ascending="getOrderBy"
      :loading="isLoading"
      :empty="!paymentList?.length"
      :empty-text="$t('payments.no_matching_payments')"
      @update:search="onSearchText"
      @update:sort-field="setSortField"
      @toggle-order="sortData"
    >
      <RecordListItem
        v-for="payment in (paymentList ?? []).filter(Boolean)"
        :id="'payment-' + payment.id"
        :key="payment.id"
        :to="`/admin/payments/${payment.id}/view`"
        :active="hasActiveUrl(payment.id)"
        :title="payment.customer?.name ?? ''"
        :subtitle="payment.payment_number"
        :meta="payment.formatted_payment_date"
      >
        <template #amount>
          <BaseFormatMoney :amount="payment.amount" :currency="payment.customer?.currency" />
        </template>
      </RecordListItem>
    </RecordListPane>

    <SendPaymentModal />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { usePaymentStore } from '../store'
import PaymentDropdown from '../components/PaymentDropdown.vue'
import SendPaymentModal from '../components/SendPaymentModal.vue'
import RecordListPane from '@/scripts/components/layout/RecordListPane.vue'
import RecordListItem from '@/scripts/components/layout/RecordListItem.vue'
import { useUserStore } from '../../../../stores/user.store'
import { useModalStore } from '../../../../stores/modal.store'
import type { Payment, PaymentAllocation } from '../../../../types/domain/payment'
import type { Invoice } from '../../../../types/domain/invoice'

interface Props {
  canEdit?: boolean
  canView?: boolean
  canDelete?: boolean
  canSend?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canEdit: false,
  canView: false,
  canDelete: false,
  canSend: false,
})

const ABILITIES = {
  EDIT: 'edit-payment',
  VIEW: 'view-payment',
  DELETE: 'delete-payment',
  SEND: 'send-payment',
} as const

const paymentStore = usePaymentStore()
const userStore = useUserStore()
const modalStore = useModalStore()
const { t } = useI18n()
const route = useRoute()

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

const paymentData = ref<Payment | Record<string, unknown>>({})
const isFetching = ref<boolean>(false)
const isLoading = ref<boolean>(false)

const paymentList = ref<Payment[] | null>(null)
const currentPageNumber = ref<number>(1)
const lastPageNumber = ref<number>(1)
const listPane = ref<InstanceType<typeof RecordListPane> | null>(null)
const paymentListSection = computed<HTMLElement | null>(() => listPane.value?.listEl ?? null)

interface SearchData {
  orderBy: string | null
  orderByField: string | null
  searchText: string | null
}

const searchData = reactive<SearchData>({
  orderBy: null,
  orderByField: null,
  searchText: null,
})

const pageTitle = computed<string>(() => {
  return (paymentData.value as Payment).payment_number ?? ''
})

const getOrderBy = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || searchData.orderBy === null
})

const shareableLink = computed<string | false>(() => {
  const hash = (paymentData.value as Payment).unique_hash
  return hash ? `/payments/pdf/${hash}` : false
})

const currentPayment = computed<Payment>(() => paymentData.value as Payment)
const paymentAllocations = computed<PaymentAllocation[]>(() => (paymentData.value as Payment).allocations ?? [])
const paymentUnallocatedAmount = computed<number>(() => (paymentData.value as Payment).unallocated_amount ?? 0)
const paymentCurrency = computed(() => currentPayment.value.currency ?? currentPayment.value.customer?.currency ?? null)

// The invoices this payment settles, if it was allocated to any
const allocatedInvoices = computed<Invoice[]>(() => {
  return paymentAllocations.value
    .map((allocation) => allocation.invoice)
    .filter((invoice): invoice is Invoice => !!invoice)
})

const sortOptions = computed(() => [
  { value: 'payment_date', label: t('payments.date') },
  { value: 'payment_number', label: t('payments.payment_number') },
])

function setSortField(field: string): void {
  searchData.orderByField = field
  onSearch()
}

function onSearchText(value: string): void {
  searchData.searchText = value
  onSearch()
}

watch(route, () => {
  loadPayment()
})

loadPayments()
loadPayment()

let searchTimeout: ReturnType<typeof setTimeout> | null = null

function onSearch(): void {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    paymentList.value = []
    loadPayments()
  }, 500)
}

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

async function loadPayments(
  pageNumber?: number,
  fromScrollListener = false,
): Promise<void> {
  if (isLoading.value) return

  const params: Record<string, unknown> = {}

  if (searchData.searchText) {
    params.search = searchData.searchText
  }
  if (searchData.orderBy != null) {
    params.orderBy = searchData.orderBy
  }
  if (searchData.orderByField != null) {
    params.orderByField = searchData.orderByField
  }

  isLoading.value = true
  const response = await paymentStore.fetchPayments({
    page: pageNumber,
    ...params,
  } as never)
  isLoading.value = false

  paymentList.value = paymentList.value ?? []
  paymentList.value = [...paymentList.value, ...response.data.data]

  currentPageNumber.value = pageNumber ?? 1
  lastPageNumber.value = response.data.meta.last_page

  const paymentFound = paymentList.value.find(
    (p) => p.id === Number(route.params.id),
  )

  if (
    !fromScrollListener &&
    !paymentFound &&
    currentPageNumber.value < lastPageNumber.value &&
    Object.keys(params).length === 0
  ) {
    loadPayments(++currentPageNumber.value)
  }

  if (paymentFound && !fromScrollListener) {
    setTimeout(() => scrollToPayment(), 500)
  }
}

async function loadPayment(): Promise<void> {
  if (!route.params.id) return

  isFetching.value = true
  const response = await paymentStore.fetchPayment(Number(route.params.id))

  if (response.data) {
    isFetching.value = false
    paymentData.value = { ...response.data.data } as Payment
  }
}

function scrollToPayment(): void {
  const el = document.getElementById(`payment-${route.params.id}`)
  const list = paymentListSection.value
  if (el && list) {
    // Scroll the list pane alone; scrollIntoView would also move the page
    list.scrollTo({ top: el.offsetTop - list.offsetTop - 8, behavior: 'smooth' })
    el.classList.add('shake')
    addScrollListener()
  }
}

function addScrollListener(): void {
  paymentListSection.value?.addEventListener('scroll', (ev) => {
    const target = ev.target as HTMLElement
    if (
      target.scrollTop > 0 &&
      target.scrollTop + target.clientHeight > target.scrollHeight - 200
    ) {
      if (currentPageNumber.value < lastPageNumber.value) {
        loadPayments(++currentPageNumber.value, true)
      }
    }
  })
}

function sortData(): void {
  if (searchData.orderBy === 'asc') {
    searchData.orderBy = 'desc'
  } else {
    searchData.orderBy = 'asc'
  }
  onSearch()
}

function onPaymentSend(): void {
  modalStore.openModal({
    title: t('payments.send_payment'),
    componentName: 'SendPaymentModal',
    id: (paymentData.value as Payment).id,
    data: paymentData.value,
    variant: 'lg',
    refreshData: () => loadPayment(),
  })
}
</script>
