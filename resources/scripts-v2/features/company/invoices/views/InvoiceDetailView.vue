<template>
  <BasePage v-if="invoiceData" class="xl:pl-96 xl:ml-8">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <div class="text-sm mr-3">
          <BaseButton
            v-if="invoiceData.status === 'DRAFT' && canEdit"
            :disabled="isMarkAsSent"
            variant="primary-outline"
            @click="onMarkAsSent"
          >
            {{ $t('invoices.mark_as_sent') }}
          </BaseButton>
        </div>

        <BaseButton
          v-if="invoiceData.status === 'DRAFT' && canSend"
          variant="primary"
          class="text-sm"
          @click="onSendInvoice"
        >
          {{ $t('invoices.send_invoice') }}
        </BaseButton>

        <!-- Record Payment -->
        <router-link
          v-if="canCreatePayment"
          :to="`/admin/payments/${$route.params.id}/create`"
        >
          <BaseButton
            v-if="invoiceData.status === 'SENT' || invoiceData.status === 'VIEWED'"
            variant="primary"
          >
            {{ $t('invoices.record_payment') }}
          </BaseButton>
        </router-link>

        <!-- Invoice Dropdown -->
        <InvoiceDropdown
          class="ml-3"
          :row="invoiceData"
          :load-data="() => loadInvoices()"
          :can-edit="canEdit"
          :can-view="canView"
          :can-create="canCreate"
          :can-delete="canDelete"
          :can-send="canSend"
        />
      </template>
    </BasePageHeader>

    <!-- Sidebar -->
    <div
      class="fixed top-0 left-0 hidden h-full pt-16 pb-[6.4rem] ml-56 bg-surface xl:ml-64 w-88 xl:block"
    >
      <div
        class="flex items-center justify-between px-4 pt-8 pb-2 border border-line-default border-solid height-full"
      >
        <div class="mb-6">
          <BaseInput
            v-model="searchData.searchText"
            :placeholder="$t('general.search')"
            type="text"
            variant="gray"
            @input="onSearched()"
          >
            <template #right>
              <BaseIcon name="MagnifyingGlassIcon" class="h-5 text-subtle" />
            </template>
          </BaseInput>
        </div>

        <div class="flex mb-6 ml-3" role="group" aria-label="First group">
          <BaseDropdown class="ml-3" position="bottom-start">
            <template #activator>
              <BaseButton size="md" variant="gray">
                <BaseIcon name="FunnelIcon" />
              </BaseButton>
            </template>
            <div
              class="px-2 py-1 pb-2 mb-1 mb-2 text-sm border-b border-line-default border-solid"
            >
              {{ $t('general.sort_by') }}
            </div>

            <BaseDropdownItem class="flex px-1 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_invoice_date"
                  v-model="searchData.orderByField"
                  :label="$t('reports.invoices.invoice_date')"
                  size="sm"
                  name="filter"
                  value="invoice_date"
                  @update:model-value="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-1 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_due_date"
                  v-model="searchData.orderByField"
                  :label="$t('invoices.due_date')"
                  value="due_date"
                  size="sm"
                  name="filter"
                  @update:model-value="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-1 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_invoice_number"
                  v-model="searchData.orderByField"
                  :label="$t('invoices.invoice_number')"
                  value="invoice_number"
                  size="sm"
                  name="filter"
                  @update:model-value="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>
          </BaseDropdown>

          <BaseButton class="ml-1" size="md" variant="gray" @click="sortData">
            <BaseIcon v-if="getOrderBy" name="BarsArrowUpIcon" />
            <BaseIcon v-else name="BarsArrowDownIcon" />
          </BaseButton>
        </div>
      </div>

      <div
        ref="invoiceListSection"
        class="h-full overflow-y-scroll border-l border-line-default border-solid base-scroll"
      >
        <div v-for="(invoice, index) in invoiceList" :key="index">
          <router-link
            v-if="invoice"
            :id="'invoice-' + invoice.id"
            :to="`/admin/invoices/${invoice.id}/view`"
            :class="[
              'flex justify-between side-invoice p-4 cursor-pointer hover:bg-hover-strong items-center border-l-4 border-l-transparent',
              {
                'bg-surface-tertiary border-l-4 border-l-primary-500 border-solid':
                  hasActiveUrl(invoice.id),
              },
            ]"
            style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
          >
            <div class="flex-2">
              <BaseText
                :text="invoice.customer?.name ?? ''"
                class="pr-2 mb-2 text-sm not-italic font-normal leading-5 text-heading capitalize truncate"
              />
              <div
                class="mt-1 mb-2 text-xs not-italic font-medium leading-5 text-body"
              >
                {{ invoice.invoice_number }}
              </div>
              <BaseEstimateStatusBadge
                :status="invoice.status"
                class="px-1 text-xs"
              >
                <BaseInvoiceStatusLabel :status="invoice.status" />
              </BaseEstimateStatusBadge>
            </div>

            <div class="flex-1 whitespace-nowrap right">
              <BaseFormatMoney
                class="mb-2 text-xl not-italic font-semibold leading-8 text-right text-heading block"
                :amount="invoice.total"
                :currency="invoice.customer?.currency"
              />
              <div
                class="text-sm not-italic font-normal leading-5 text-right text-body est-date"
              >
                {{ invoice.formatted_invoice_date }}
              </div>
            </div>
          </router-link>
        </div>

        <div v-if="isLoading" class="flex justify-center p-4 items-center">
          <LoadingIcon class="h-6 m-1 animate-spin text-primary-400" />
        </div>
        <p
          v-if="!invoiceList?.length && !isLoading"
          class="flex justify-center px-4 mt-5 text-sm text-body"
        >
          {{ $t('invoices.no_matching_invoices') }}
        </p>
      </div>
    </div>

    <!-- PDF Preview -->
    <div class="flex flex-col min-h-0 mt-8 overflow-hidden" style="height: 75vh">
      <iframe
        :src="shareableLink"
        class="flex-1 border border-gray-400 border-solid bg-surface rounded-xl frame-style"
      />
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useInvoiceStore } from '../store'
import InvoiceDropdown from '../components/InvoiceDropdown.vue'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'
import type { Invoice } from '../../../../types/domain/invoice'

interface Props {
  canEdit?: boolean
  canView?: boolean
  canCreate?: boolean
  canDelete?: boolean
  canSend?: boolean
  canCreatePayment?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canEdit: false,
  canView: false,
  canCreate: false,
  canDelete: false,
  canSend: false,
  canCreatePayment: false,
})

const invoiceStore = useInvoiceStore()
const { t } = useI18n()
const route = useRoute()

const invoiceData = ref<Invoice | null>(null)
const isMarkAsSent = ref<boolean>(false)
const isLoading = ref<boolean>(false)

const invoiceList = ref<Invoice[] | null>(null)
const currentPageNumber = ref<number>(1)
const lastPageNumber = ref<number>(1)
const invoiceListSection = ref<HTMLElement | null>(null)

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

const pageTitle = computed<string>(() => invoiceData.value?.invoice_number ?? '')

const getOrderBy = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || searchData.orderBy === null
})

const shareableLink = computed<string>(() => {
  return `/invoices/pdf/${invoiceData.value?.unique_hash ?? ''}`
})

watch(route, (to) => {
  if (to.name === 'invoices.view') {
    loadInvoice()
  }
})

async function onMarkAsSent(): Promise<void> {
  const confirmed = window.confirm(t('invoices.invoice_mark_as_sent'))
  if (!confirmed) return

  isMarkAsSent.value = false
  await invoiceStore.markAsSent({
    id: invoiceData.value!.id,
    status: 'SENT',
  })
  invoiceData.value!.status = 'SENT' as Invoice['status']
  isMarkAsSent.value = true
  isMarkAsSent.value = false
}

function onSendInvoice(): void {
  const modalStore = (window as Record<string, unknown>).__modalStore as
    | { openModal: (opts: Record<string, unknown>) => void }
    | undefined
  modalStore?.openModal({
    title: t('invoices.send_invoice'),
    componentName: 'SendInvoiceModal',
    id: invoiceData.value!.id,
    data: invoiceData.value,
  })
}

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

async function loadInvoices(
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
  const response = await invoiceStore.fetchInvoices({
    page: pageNumber,
    ...params,
  } as never)
  isLoading.value = false

  invoiceList.value = invoiceList.value ?? []
  invoiceList.value = [...invoiceList.value, ...response.data.data]

  currentPageNumber.value = pageNumber ?? 1
  lastPageNumber.value = response.data.meta.last_page

  const invoiceFound = invoiceList.value.find(
    (inv) => inv.id === Number(route.params.id),
  )

  if (
    !fromScrollListener &&
    !invoiceFound &&
    currentPageNumber.value < lastPageNumber.value &&
    Object.keys(params).length === 0
  ) {
    loadInvoices(++currentPageNumber.value)
  }

  if (invoiceFound && !fromScrollListener) {
    setTimeout(() => scrollToInvoice(), 500)
  }
}

function scrollToInvoice(): void {
  const el = document.getElementById(`invoice-${route.params.id}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
    el.classList.add('shake')
    addScrollListener()
  }
}

function addScrollListener(): void {
  invoiceListSection.value?.addEventListener('scroll', (ev) => {
    const target = ev.target as HTMLElement
    if (
      target.scrollTop > 0 &&
      target.scrollTop + target.clientHeight > target.scrollHeight - 200
    ) {
      if (currentPageNumber.value < lastPageNumber.value) {
        loadInvoices(++currentPageNumber.value, true)
      }
    }
  })
}

async function loadInvoice(): Promise<void> {
  const response = await invoiceStore.fetchInvoice(Number(route.params.id))
  if (response.data) {
    invoiceData.value = { ...response.data.data } as Invoice
  }
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null

function onSearched(): void {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    invoiceList.value = []
    loadInvoices()
  }, 500)
}

function sortData(): void {
  if (searchData.orderBy === 'asc') {
    searchData.orderBy = 'desc'
  } else {
    searchData.orderBy = 'asc'
  }
  onSearched()
}

// Initialize
loadInvoices()
loadInvoice()
</script>
