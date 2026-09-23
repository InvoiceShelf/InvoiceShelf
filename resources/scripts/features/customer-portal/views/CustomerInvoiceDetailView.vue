<template>
  <div class="flex min-h-full">
    <!--
      The customer's other invoices (wide screens only). The portal publishes
      no top inset, so the pane is sized below its fixed header here.
    -->

    <BasePage class="min-w-0">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('invoices.invoice', 2)"
            :to="`/${store.companySlug}/customer/invoices`"
          />
        </BaseBreadcrumb>

        <div v-if="currentInvoice" class="flex flex-wrap items-center gap-1.5 mt-2">
          <BaseInvoiceStatusBadge :status="currentInvoice.status">
            <BaseInvoiceStatusLabel :status="currentInvoice.status" />
          </BaseInvoiceStatusBadge>
          <BasePaidStatusBadge v-if="currentInvoice.overdue" status="OVERDUE">
            {{ $t('invoices.overdue') }}
          </BasePaidStatusBadge>
          <BasePaidStatusBadge v-else :status="currentInvoice.paid_status">
            <BaseInvoiceStatusLabel :status="currentInvoice.paid_status" />
          </BasePaidStatusBadge>
        </div>

        <template #actions>
          <BaseButton
            :disabled="isSendingEmail"
            variant="white"
            tag="a"
            :href="downloadLink"
            download
          >
            <template #left="slotProps">
              <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
            </template>
            {{ $t('invoices.download') }}
          </BaseButton>

          <BaseButton
            v-if="canPay"
            variant="primary"
            @click="payInvoice"
          >
            {{ $t('invoices.pay_invoice') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- What the invoice says, without opening it -->
      <BaseStatStrip v-if="currentInvoice" :columns="4">
        <BaseStat :label="$t('dashboard.recent_invoices_card.amount_due')" emphasis>
          <BaseFormatMoney :amount="currentInvoice.due_amount" :currency="currentInvoice.currency" />
        </BaseStat>
        <BaseStat :label="$t('invoices.total')">
          <BaseFormatMoney :amount="currentInvoice.total" :currency="currentInvoice.currency" />
        </BaseStat>
        <BaseStat :label="$t('invoices.invoice_date')">
          {{ currentInvoice.formatted_invoice_date }}
        </BaseStat>
        <BaseStat :label="$t('invoices.due_date')">
          <span :class="currentInvoice.overdue ? 'text-status-red' : ''">
            {{ currentInvoice.formatted_due_date || '-' }}
          </span>
        </BaseStat>
      </BaseStatStrip>

      <BasePdfPreview
        :src="shareableLink"
        :title="currentInvoice ? `${currentInvoice.invoice_number}.pdf` : ''"
      />
    </BasePage>

    <RecordListPane
      ref="listPane"
      class="!h-[calc(100dvh-5.5rem)]"
      :search="searchData.invoice_number"
      :sort-options="sortOptions"
      :sort-field="searchData.orderByField"
      :ascending="isAscending"
      :empty="!store.invoices.length"
      :empty-text="$t('invoices.no_matching_invoices')"
      @update:search="onSearchText"
      @update:sort-field="setSortField"
      @toggle-order="sortData"
    >
      <RecordListItem
        v-for="inv in store.invoices"
        :id="'invoice-' + inv.id"
        :key="inv.id"
        :to="`/${store.companySlug}/customer/invoices/${inv.id}/view`"
        :active="hasActiveUrl(inv.id)"
        :title="inv.invoice_number"
        :meta="inv.formatted_invoice_date"
      >
        <template #badges>
          <BaseInvoiceStatusBadge :status="inv.status">
            <BaseInvoiceStatusLabel :status="inv.status" />
          </BaseInvoiceStatusBadge>
        </template>
        <template #amount>
          <BaseFormatMoney :amount="inv.total" :currency="inv.currency" />
        </template>
      </RecordListItem>
    </RecordListPane>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDebounceFn } from '@vueuse/core'
import { useCustomerPortalStore } from '../store'
import RecordListPane from '@/scripts/components/layout/RecordListPane.vue'
import RecordListItem from '@/scripts/components/layout/RecordListItem.vue'
import type { Invoice } from '../../../types/domain/invoice'
import { scrollBehavior } from '@/scripts/utils/motion'

const store = useCustomerPortalStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const invoice = ref<Partial<Invoice>>({})
const isSendingEmail = ref<boolean>(false)
const listPane = ref<InstanceType<typeof RecordListPane> | null>(null)

const searchData = reactive<{
  orderBy: string
  orderByField: string
  invoice_number: string
}>({
  orderBy: '',
  orderByField: '',
  invoice_number: '',
})

const pageTitle = computed<string>(() => {
  return store.selectedViewInvoice?.invoice_number ?? ''
})

const currentInvoice = computed<Invoice | null>(() => store.selectedViewInvoice)

const sortOptions = computed(() => [
  { value: 'invoice_date', label: t('invoices.invoice_date') },
  { value: 'due_date', label: t('invoices.due_date') },
  { value: 'invoice_number', label: t('invoices.invoice_number') },
])

const isAscending = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || !searchData.orderBy
})

const shareableLink = computed<string | false>(() => {
  return invoice.value.unique_hash
    ? `/invoices/pdf/${invoice.value.unique_hash}`
    : false
})

const downloadLink = computed<string>(() => {
  return `/invoices/pdf/${invoice.value.unique_hash ?? ''}`
})

const canPay = computed<boolean>(() => {
  return (
    store.selectedViewInvoice?.paid_status !== 'PAID' &&
    store.enabledModules.includes('Payments')
  )
})

watch(() => route.params.id, () => {
  loadInvoice()
})

onMounted(() => {
  loadInvoices()
  loadInvoice()
})

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

async function loadInvoices(): Promise<void> {
  await store.fetchInvoices({ limit: 'all' })
  setTimeout(() => scrollToInvoice(), 500)
}

async function loadInvoice(): Promise<void> {
  const id = route.params.id
  if (!id) return
  const response = await store.fetchViewInvoice(id as string)
  if (response.data?.data) {
    invoice.value = response.data.data
  }
}

function scrollToInvoice(): void {
  const el = document.getElementById(`invoice-${route.params.id}`)
  const list = listPane.value?.listEl
  if (el && list) {
    // Scroll the list pane alone; scrollIntoView would also move the page
    list.scrollTo({ top: el.offsetTop - list.offsetTop - 8, behavior: scrollBehavior() })
    el.classList.add('shake')
  }
}

async function onSearch(): Promise<void> {
  const params: Record<string, string> = {}
  if (searchData.invoice_number) params.invoice_number = searchData.invoice_number
  if (searchData.orderBy) params.orderBy = searchData.orderBy
  if (searchData.orderByField) params.orderByField = searchData.orderByField
  await store.searchInvoices(params)
}

const onSearchDebounced = useDebounceFn(onSearch, 500)

function onSearchText(value: string): void {
  searchData.invoice_number = value
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

function payInvoice(): void {
  if (!store.selectedViewInvoice) return
  router.push({
    name: 'invoice.portal.payment',
    params: {
      id: String(store.selectedViewInvoice.id),
      company: (store.selectedViewInvoice.company as { slug: string } | undefined)?.slug ?? store.companySlug,
    },
  })
}
</script>
