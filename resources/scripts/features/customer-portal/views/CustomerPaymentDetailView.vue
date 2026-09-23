<template>
  <div class="flex min-h-full">
    <!--
      The customer's other payments (wide screens only). The portal publishes
      no top inset, so the pane is sized below its fixed header here.
    -->

    <BasePage class="min-w-0">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('payments.payment', 2)"
            :to="`/${store.companySlug}/customer/payments`"
          />
        </BaseBreadcrumb>

        <template #actions>
          <BaseButton
            :disabled="isSendingEmail"
            variant="white"
            tag="a"
            download
            :href="downloadLink"
          >
            <template #left="slotProps">
              <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
            </template>
            {{ $t('general.download') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- What the receipt says, without opening it -->
      <BaseStatStrip v-if="currentPayment" :columns="allocatedInvoices.length ? 4 : 3">
        <BaseStat :label="$t('payments.amount')" emphasis>
          <BaseFormatMoney :amount="currentPayment.amount" :currency="currentPayment.currency" />
        </BaseStat>
        <BaseStat :label="$t('payments.date')">
          {{ currentPayment.formatted_payment_date }}
        </BaseStat>
        <BaseStat :label="$t('payments.payment_mode')">
          {{ currentPayment.payment_method?.name || '-' }}
        </BaseStat>
        <BaseStat v-if="allocatedInvoices.length" :label="$t('payments.invoice')" wide>
          <template v-for="(inv, index) in allocatedInvoices" :key="inv.id">
            <span v-if="index > 0">, </span>
            <router-link
              :to="`/${store.companySlug}/customer/invoices/${inv.id}/view`"
              class="hover:text-primary-600"
            >
              {{ inv.invoice_number }}
            </router-link>
          </template>
        </BaseStat>
      </BaseStatStrip>

      <BasePdfPreview
        :src="shareableLink"
        :title="currentPayment ? `${currentPayment.payment_number}.pdf` : ''"
      />
    </BasePage>

    <RecordListPane
      ref="listPane"
      class="!h-[calc(100dvh-5.5rem)]"
      :search="searchData.payment_number"
      :sort-options="sortOptions"
      :sort-field="searchData.orderByField"
      :ascending="isAscending"
      :empty="!store.payments.length"
      :empty-text="$t('payments.no_matching_payments')"
      @update:search="onSearchText"
      @update:sort-field="setSortField"
      @toggle-order="sortData"
    >
      <RecordListItem
        v-for="pmt in store.payments"
        :id="'payment-' + pmt.id"
        :key="pmt.id"
        :to="`/${store.companySlug}/customer/payments/${pmt.id}/view`"
        :active="hasActiveUrl(pmt.id)"
        :title="pmt.payment_number"
        :meta="pmt.formatted_payment_date"
      >
        <template #amount>
          <BaseFormatMoney :amount="pmt.amount" :currency="pmt.currency" />
        </template>
      </RecordListItem>
    </RecordListPane>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDebounceFn } from '@vueuse/core'
import { useCustomerPortalStore } from '../store'
import RecordListPane from '@/scripts/components/layout/RecordListPane.vue'
import RecordListItem from '@/scripts/components/layout/RecordListItem.vue'
import type { Payment } from '../../../types/domain/payment'
import type { Invoice } from '../../../types/domain/invoice'

const store = useCustomerPortalStore()
const route = useRoute()
const { t } = useI18n()

const payment = ref<Partial<Payment>>({})
const isSendingEmail = ref<boolean>(false)
const listPane = ref<InstanceType<typeof RecordListPane> | null>(null)

const searchData = reactive<{
  orderBy: string
  orderByField: string
  payment_number: string
}>({
  orderBy: '',
  orderByField: '',
  payment_number: '',
})

const pageTitle = computed<string>(() => {
  return store.selectedViewPayment?.payment_number ?? ''
})

const currentPayment = computed<Payment | null>(() => store.selectedViewPayment)

// The invoices this payment settles, if it was allocated to any
const allocatedInvoices = computed<Invoice[]>(() => {
  return (currentPayment.value?.allocations ?? [])
    .map((allocation) => allocation.invoice)
    .filter((inv): inv is Invoice => !!inv)
})

const sortOptions = computed(() => [
  { value: 'invoice_number', label: t('invoices.title') },
  { value: 'payment_date', label: t('payments.date') },
  { value: 'payment_number', label: t('payments.payment_number') },
])

const isAscending = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || !searchData.orderBy
})

const shareableLink = computed<string | false>(() => {
  return payment.value.unique_hash
    ? `/payments/pdf/${payment.value.unique_hash}`
    : false
})

const downloadLink = computed<string>(() => {
  return `/payments/pdf/${payment.value.unique_hash ?? ''}`
})

watch(() => route.params.id, () => {
  loadPayment()
})

onMounted(() => {
  loadPayments()
  loadPayment()
})

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

async function loadPayments(): Promise<void> {
  await store.fetchPayments({ limit: 'all' })
  setTimeout(() => scrollToPayment(), 500)
}

async function loadPayment(): Promise<void> {
  const id = route.params.id
  if (!id) return
  const response = await store.fetchViewPayment(id as string)
  if (response.data?.data) {
    payment.value = response.data.data
  }
}

function scrollToPayment(): void {
  const el = document.getElementById(`payment-${route.params.id}`)
  const list = listPane.value?.listEl
  if (el && list) {
    // Scroll the list pane alone; scrollIntoView would also move the page
    list.scrollTo({ top: el.offsetTop - list.offsetTop - 8, behavior: 'smooth' })
    el.classList.add('shake')
  }
}

async function onSearch(): Promise<void> {
  const params: Record<string, string> = {}
  if (searchData.payment_number) params.payment_number = searchData.payment_number
  if (searchData.orderBy) params.orderBy = searchData.orderBy
  if (searchData.orderByField) params.orderByField = searchData.orderByField
  await store.searchPayments(params)
}

const onSearchDebounced = useDebounceFn(onSearch, 500)

function onSearchText(value: string): void {
  searchData.payment_number = value
  onSearchDebounced()
}

function setSortField(field: string): void {
  searchData.orderByField = field
  onSearchDebounced()
}

function sortData(): void {
  searchData.orderBy = searchData.orderBy === 'asc' ? 'desc' : 'asc'
  onSearch()
}
</script>
