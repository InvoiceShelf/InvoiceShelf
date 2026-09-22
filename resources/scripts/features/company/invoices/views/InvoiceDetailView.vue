<template>
  <div v-if="invoiceData" class="flex min-h-full">
    <!-- The other invoices, beside the one on screen (wide screens only) -->
    <aside
      class="
        sticky top-0 hidden xl:flex flex-col w-80 shrink-0 border-r bg-surface border-line-light
        h-[calc(100dvh-var(--app-top-inset))]
      "
    >
      <div class="flex items-center gap-2 p-3 border-b border-line-light">
        <BaseInput
          v-model="searchData.searchText"
          :placeholder="$t('general.search')"
          type="text"
          container-class="flex-1"
          @input="onSearched()"
        >
          <template #left>
            <BaseIcon name="MagnifyingGlassIcon" class="w-4 h-4 text-subtle" />
          </template>
        </BaseInput>

        <BaseDropdown position="bottom-start" width-class="w-56">
          <template #activator>
            <span
              class="flex items-center justify-center border rounded-lg w-9 h-9 border-line-default text-muted hover:bg-hover"
              :aria-label="$t('general.sort_by')"
            >
              <BaseIcon name="FunnelIcon" class="w-4 h-4" />
            </span>
          </template>
          <p class="px-3 pt-2 pb-1 text-xs font-medium text-muted">
            {{ $t('general.sort_by') }}
          </p>
          <BaseDropdownItem
            v-for="option in sortOptions"
            :key="option.value"
            @click="setSortField(option.value)"
          >
            <span class="flex-1">{{ option.label }}</span>
            <BaseIcon
              v-if="searchData.orderByField === option.value"
              name="CheckIcon"
              class="w-4 h-4 text-primary-600"
            />
          </BaseDropdownItem>
        </BaseDropdown>

        <button
          type="button"
          class="flex items-center justify-center border rounded-lg w-9 h-9 shrink-0 border-line-default text-muted hover:bg-hover"
          :aria-label="$t('general.sort_by')"
          @click="sortData"
        >
          <BaseIcon :name="getOrderBy ? 'BarsArrowUpIcon' : 'BarsArrowDownIcon'" class="w-4 h-4" />
        </button>
      </div>

      <div
        ref="invoiceListSection"
        class="flex-1 min-h-0 overflow-y-auto"
      >
        <router-link
          v-for="invoice in (invoiceList ?? []).filter(Boolean)"
          :id="'invoice-' + invoice.id"
          :key="invoice.id"
          :to="`/admin/invoices/${invoice.id}/view`"
          :aria-current="hasActiveUrl(invoice.id) ? 'page' : undefined"
          :class="[
            'flex items-start justify-between gap-3 px-4 py-3 border-b border-line-light transition-colors',
            hasActiveUrl(invoice.id) ? 'bg-primary-50' : 'hover:bg-hover',
          ]"
        >
          <div class="min-w-0">
            <p class="text-sm font-medium truncate text-heading">
              {{ invoice.customer?.name ?? '' }}
            </p>
            <p class="mt-0.5 text-xs text-muted">
              {{ invoice.invoice_number }}
            </p>
            <div class="flex flex-wrap gap-1 mt-2">
              <BaseInvoiceStatusBadge :status="invoice.status">
                <BaseInvoiceStatusLabel :status="invoice.status" />
              </BaseInvoiceStatusBadge>
              <span
                v-if="invoice.type !== 'CREDIT_NOTE' && invoice.credited_status === 'FULL'"
                class="inline-flex items-center h-6 px-2 text-xs font-medium rounded-full bg-status-yellow-bg text-status-yellow"
              >
                {{ $t('invoices.cancelled') }}
              </span>
              <span
                v-else-if="invoice.type !== 'CREDIT_NOTE' && invoice.credited_status === 'PARTIAL'"
                class="inline-flex items-center h-6 px-2 text-xs font-medium rounded-full bg-status-yellow-bg text-status-yellow"
              >
                {{ $t('invoices.partially_credited') }}
              </span>
            </div>
          </div>

          <div class="text-right shrink-0">
            <BaseFormatMoney
              class="block text-sm font-semibold text-heading"
              :amount="invoice.total"
              :currency="invoice.customer?.currency"
            />
            <p class="mt-0.5 text-xs text-muted">
              {{ invoice.formatted_invoice_date }}
            </p>
          </div>
        </router-link>

        <div v-if="isLoading" class="flex items-center justify-center p-4">
          <LoadingIcon class="w-5 h-5 animate-spin text-subtle" />
        </div>
        <p
          v-if="!invoiceList?.length && !isLoading"
          class="px-4 py-8 text-sm text-center text-muted"
        >
          {{ $t('invoices.no_matching_invoices') }}
        </p>
      </div>
    </aside>

    <BasePage class="min-w-0">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('invoices.invoice', 2)" to="/admin/invoices" />
        </BaseBreadcrumb>

        <div class="flex flex-wrap items-center gap-1.5 mt-2">
          <BaseInvoiceStatusBadge :status="invoiceData.status">
            <BaseInvoiceStatusLabel :status="invoiceData.status" />
          </BaseInvoiceStatusBadge>
          <BasePaidStatusBadge v-if="invoiceData.overdue" status="OVERDUE">
            {{ $t('invoices.overdue') }}
          </BasePaidStatusBadge>
          <BasePaidStatusBadge
            v-else-if="invoiceData.type !== 'CREDIT_NOTE'"
            :status="invoiceData.paid_status"
          >
            <BaseInvoiceStatusLabel :status="invoiceData.paid_status" />
          </BasePaidStatusBadge>
        </div>

        <template v-if="!isPhone" #actions>
          <BaseButton
            v-if="invoiceData.status === 'DRAFT' && canEdit"
            :disabled="isMarkAsSent"
            variant="white"
            @click="onMarkAsSent"
          >
            {{ $t('invoices.mark_as_sent') }}
          </BaseButton>

          <BaseButton
            v-if="invoiceData.status === 'DRAFT' && canSend"
            variant="primary"
            @click="onSendInvoice"
          >
            <template #left="slotProps">
              <BaseIcon name="PaperAirplaneIcon" :class="slotProps.class" />
            </template>
            {{ $t('invoices.send_invoice') }}
          </BaseButton>

          <router-link
            v-if="canRecordPayment"
            :to="`/admin/payments/${$route.params.id}/create`"
          >
            <BaseButton variant="primary">
              <template #left="slotProps">
                <BaseIcon name="BanknotesIcon" :class="slotProps.class" />
              </template>
              {{ $t('invoices.record_payment') }}
            </BaseButton>
          </router-link>

          <InvoiceDropdown
            :row="invoiceData"
            :load-data="refreshInvoiceList"
            :can-edit="canEdit"
            :can-view="canView"
            :can-create="canCreate"
            :can-delete="canDelete"
            :can-send="canSend"
            :can-create-payment="canCreatePayment"
            :can-create-estimate="canCreateEstimate"
          />
        </template>
      </BasePageHeader>

      <!-- What the document says, without opening it -->
      <dl
        class="grid grid-cols-2 gap-px mb-6 overflow-hidden border rounded-xl bg-line-light border-line-light shadow-card md:grid-cols-5"
      >
        <div class="col-span-2 px-4 py-3.5 md:col-span-1 bg-surface md:px-5">
          <dt class="text-xs font-medium text-muted">{{ $t('invoices.customer') }}</dt>
          <dd class="mt-1 text-sm font-medium truncate text-heading">
            <router-link
              v-if="invoiceData.customer?.id"
              :to="`/admin/customers/${invoiceData.customer.id}/view`"
              class="hover:text-primary-600"
            >
              {{ invoiceData.customer.name }}
            </router-link>
          </dd>
        </div>
        <div class="px-4 py-3.5 bg-surface md:px-5">
          <dt class="text-xs font-medium text-muted">{{ $t('invoices.invoice_date') }}</dt>
          <dd class="mt-1 text-sm font-medium tabular text-heading">{{ invoiceData.formatted_invoice_date }}</dd>
        </div>
        <div class="px-4 py-3.5 bg-surface md:px-5">
          <dt class="text-xs font-medium text-muted">{{ $t('invoices.due_date') }}</dt>
          <dd
            :class="invoiceData.overdue ? 'text-status-red' : 'text-heading'"
            class="mt-1 text-sm font-medium tabular"
          >
            {{ invoiceData.formatted_due_date || '-' }}
          </dd>
        </div>
        <div class="px-4 py-3.5 bg-surface md:px-5">
          <dt class="text-xs font-medium text-muted">{{ $t('invoices.total') }}</dt>
          <dd class="mt-1 text-sm font-medium text-heading">
            <BaseFormatMoney :amount="invoiceData.total" :currency="documentCurrency" />
          </dd>
        </div>
        <div class="px-4 py-3.5 bg-surface md:px-5">
          <dt class="text-xs font-medium text-muted">{{ $t('dashboard.recent_invoices_card.amount_due') }}</dt>
          <dd class="mt-1 text-base font-semibold text-heading">
            <BaseFormatMoney :amount="invoiceData.due_amount" :currency="documentCurrency" />
          </dd>
        </div>
      </dl>

      <!-- Credit note: link back to the invoice it reverses -->
      <div
        v-if="invoiceData.type === 'CREDIT_NOTE'"
        class="px-4 py-3 mb-6 text-sm rounded-xl bg-status-red-bg text-status-red"
      >
        <div class="flex flex-wrap items-center gap-2">
          <span class="font-semibold">{{ $t('invoices.credit_note') }}</span>
          <span v-if="invoiceData.related_invoice">
            {{ $t('invoices.original_invoice') }}:
            <router-link
              :to="`/admin/invoices/${invoiceData.related_invoice.id}/view`"
              class="font-medium underline"
            >
              {{ invoiceData.related_invoice.invoice_number }}
            </router-link>
          </span>
        </div>
        <p v-if="invoiceData.credit_reason" class="mt-1 text-xs">
          {{ $t('invoices.credit_note_reason') }}: {{ invoiceData.credit_reason }}
        </p>
      </div>

      <!-- Credited: links to the credit notes that reverse this invoice. A
           partial credit reads softer than a full reversal, since the invoice
           is still live for the remainder. -->
      <div
        v-if="invoiceData.type !== 'CREDIT_NOTE' && isCredited"
        class="flex flex-wrap items-center gap-2 px-4 py-3 mb-6 text-sm rounded-xl bg-status-yellow-bg text-status-yellow"
      >
        <span class="font-semibold">
          {{ isFullyCredited ? $t('invoices.cancelled') : $t('invoices.partially_credited') }}
        </span>
        <span>
          {{
            isFullyCredited
              ? $t('invoices.cancelled_via_credit_note')
              : $t('invoices.partially_credited_via_credit_notes')
          }}:
          <router-link
            v-for="creditNote in invoiceData.credit_notes"
            :key="creditNote.id"
            :to="`/admin/invoices/${creditNote.id}/view`"
            class="ml-1 font-medium underline"
          >
            {{ creditNote.invoice_number }}
          </router-link>
        </span>
        <span v-if="invoiceData.credited_total" class="font-medium">
          {{ $t('invoices.credited_amount') }}:
          <BaseFormatMoney
            :amount="invoiceData.credited_total"
            :currency="invoiceData.customer?.currency"
          />
        </span>
      </div>

      <BaseCard v-if="invoicePaymentAllocations.length" class="mb-6">
        <h2 class="mb-2 font-semibold text-section text-heading">{{ $t('invoices.allocated_payments') }}</h2>
        <div class="divide-y divide-line-light">
          <div v-for="allocation in invoicePaymentAllocations" :key="allocation.id" class="flex items-center justify-between gap-4 py-3 text-sm">
            <div>
              <router-link v-if="allocation.payment" :to="`/admin/payments/${allocation.payment.id}/view`" class="font-medium text-primary-600 hover:text-primary-700">
                {{ allocation.payment.payment_number }}
              </router-link>
              <span v-else class="font-medium text-heading">{{ $t('payments.payment') }}</span>
              <span v-if="allocation.payment?.formatted_payment_date" class="block mt-0.5 text-xs text-muted">{{ allocation.payment.formatted_payment_date }}</span>
            </div>
            <BaseFormatMoney :amount="allocation.amount" :currency="invoiceData.customer?.currency" class="font-medium text-heading" />
          </div>
        </div>
      </BaseCard>

      <BasePdfPreview
        ref="pdfPreview"
        :src="shareableLink"
        :title="`${invoiceData.invoice_number}.pdf`"
      />

      <!-- Phones: the next step for this invoice, within thumb reach -->
      <BaseActionBar>
        <BaseButton
          v-if="invoiceData.status === 'DRAFT' && canSend"
          variant="primary"
          class="flex-1"
          @click="onSendInvoice"
        >
          <template #left="slotProps">
            <BaseIcon name="PaperAirplaneIcon" :class="slotProps.class" />
          </template>
          {{ $t('invoices.send_invoice') }}
        </BaseButton>
        <BaseButton
          v-else-if="canRecordPayment"
          variant="primary"
          class="flex-1"
          @click="$router.push(`/admin/payments/${$route.params.id}/create`)"
        >
          <template #left="slotProps">
            <BaseIcon name="BanknotesIcon" :class="slotProps.class" />
          </template>
          {{ $t('invoices.record_payment') }}
        </BaseButton>
        <BaseButton variant="white" class="flex-1" @click="openPdf">
          <template #left="slotProps">
            <BaseIcon name="ArrowUpOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ $t('pdf.open_pdf') }}
        </BaseButton>
        <InvoiceDropdown
          :row="invoiceData"
          :load-data="refreshInvoiceList"
          :can-edit="canEdit"
          :can-view="canView"
          :can-create="canCreate"
          :can-delete="canDelete"
          :can-send="canSend"
          :can-create-payment="canCreatePayment"
          :can-create-estimate="canCreateEstimate"
        />
      </BaseActionBar>
    </BasePage>

    <SendInvoiceModal />
    <CreditNoteModal />
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useInvoiceStore } from '../store'
import InvoiceDropdown from '../components/InvoiceDropdown.vue'
import SendInvoiceModal from '../components/SendInvoiceModal.vue'
import CreditNoteModal from '../components/CreditNoteModal.vue'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'
import BasePdfPreview from '@/scripts/components/base/BasePdfPreview.vue'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useUserStore } from '../../../../stores/user.store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useModalStore } from '../../../../stores/modal.store'
import type { Invoice, InvoicePaymentAllocation } from '../../../../types/domain/invoice'

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

const ABILITIES = {
  EDIT: 'edit-invoice',
  VIEW: 'view-invoice',
  CREATE: 'create-invoice',
  DELETE: 'delete-invoice',
  SEND: 'send-invoice',
  CREATE_PAYMENT: 'create-payment',
} as const

const invoiceStore = useInvoiceStore()
const userStore = useUserStore()
const dialogStore = useDialogStore()
const modalStore = useModalStore()
const { t } = useI18n()
const route = useRoute()

const canEdit = computed<boolean>(() => {
  return props.canEdit || userStore.hasAbilities(ABILITIES.EDIT)
})

const canView = computed<boolean>(() => {
  return props.canView || userStore.hasAbilities(ABILITIES.VIEW)
})

const canCreate = computed<boolean>(() => {
  return props.canCreate || userStore.hasAbilities(ABILITIES.CREATE)
})

const canDelete = computed<boolean>(() => {
  return props.canDelete || userStore.hasAbilities(ABILITIES.DELETE)
})

const canSend = computed<boolean>(() => {
  return props.canSend || userStore.hasAbilities(ABILITIES.SEND)
})

const canCreatePayment = computed<boolean>(() => {
  return (
    props.canCreatePayment || userStore.hasAbilities(ABILITIES.CREATE_PAYMENT)
  )
})

const canCreateEstimate = computed<boolean>(() => {
  return userStore.hasAbilities('create-estimate')
})

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

const { isPhone } = useBreakpoints()
const pdfPreview = ref<InstanceType<typeof BasePdfPreview> | null>(null)

const documentCurrency = computed(() => invoiceData.value?.currency ?? invoiceData.value?.customer?.currency ?? null)

// Payment can be recorded once the invoice has gone out and money is still owed
const canRecordPayment = computed<boolean>(() => {
  const invoice = invoiceData.value

  return (
    canCreatePayment.value &&
    !!invoice &&
    (invoice.status === 'SENT' || invoice.status === 'VIEWED') &&
    invoice.due_amount > 0
  )
})

const sortOptions = computed(() => [
  { value: 'invoice_date', label: t('reports.invoices.invoice_date') },
  { value: 'due_date', label: t('invoices.due_date') },
  { value: 'invoice_number', label: t('invoices.invoice_number') },
])

function setSortField(field: string): void {
  searchData.orderByField = field
  onSearched()
}

function openPdf(): void {
  pdfPreview.value?.openPdf()
}

// credited_status is only emitted where the creditNotes relation was loaded,
// so fall back to the relation itself rather than hiding the banner outright.
const isCredited = computed<boolean>(() => {
  const status = invoiceData.value?.credited_status

  if (status) {
    return status !== 'NONE'
  }

  return !!invoiceData.value?.credit_notes?.length
})

const isFullyCredited = computed<boolean>(() => {
  return invoiceData.value?.credited_status !== 'PARTIAL'
})

const invoicePaymentAllocations = computed<InvoicePaymentAllocation[]>(() => invoiceData.value?.payment_allocations ?? [])

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

function onMarkAsSent(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('invoices.invoice_mark_as_sent'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      isMarkAsSent.value = false
      await invoiceStore.markAsSent({
        id: invoiceData.value!.id,
        status: 'SENT',
      })
      invoiceData.value!.status = 'SENT' as Invoice['status']
      isMarkAsSent.value = true
      isMarkAsSent.value = false
    }
  })
}

function onSendInvoice(): void {
  modalStore.openModal({
    title: t('invoices.send_invoice'),
    componentName: 'SendInvoiceModal',
    id: invoiceData.value!.id,
    data: invoiceData.value,
    refreshData: () => loadInvoice(),
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
  const list = invoiceListSection.value
  if (el && list) {
    // Scroll the list pane alone; scrollIntoView would also move the page
    list.scrollTo({ top: el.offsetTop - list.offsetTop - 8, behavior: 'smooth' })
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

// Reset-and-refetch the sidebar list from page 1. Used after actions that
// change which invoices exist or their status (e.g. creating a credit
// note), since `loadInvoices()` alone only appends (it's built for
// infinite-scroll pagination) and would duplicate already-loaded rows.
function refreshInvoiceList(): void {
  invoiceList.value = []
  loadInvoices()
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
