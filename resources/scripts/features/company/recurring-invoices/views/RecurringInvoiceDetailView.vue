<template>
  <div class="flex min-h-full">

    <BasePage class="min-w-0">
      <!-- The only action is the menu, which reads better on the title row than alone in a bar -->
      <BasePageHeader :help="$t('page_help.recurring_invoices')" :title="pageTitle" phone-actions="inline">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('recurring_invoices.title')" to="/admin/recurring-invoices" />
        </BaseBreadcrumb>

        <div v-if="!isFetching && recurringInvoice.status" class="flex flex-wrap items-center gap-1.5 mt-2">
          <BaseRecurringInvoiceStatusBadge :status="recurringInvoice.status">
            <BaseRecurringInvoiceStatusLabel :status="recurringInvoice.status" />
          </BaseRecurringInvoiceStatusBadge>
        </div>

        <template #actions>
          <BaseButton
            v-if="canEdit && !isFetching && canToggle"
            variant="primary-outline"
            :loading="isActing"
            :disabled="isActing"
            @click="toggleStatus"
          >
            {{
              $t(
                recurringInvoice.status === 'ACTIVE'
                  ? 'recurring_invoices.pause'
                  : 'recurring_invoices.resume',
              )
            }}
          </BaseButton>
          <RecurringInvoiceDropdown
            v-if="hasAtLeastOneAbility"
            :row="recurringInvoiceStore.newRecurringInvoice"
            :can-edit="canEdit"
            :can-view="canView"
            :can-delete="canDelete"
          />
        </template>
      </BasePageHeader>

      <div v-if="isFetching" class="flex justify-center p-12">
        <BaseSpinner class="w-7 h-7 text-subtle" />
      </div>

      <template v-else>
        <div
          v-if="recurringInvoice.last_error"
          role="alert"
          class="flex gap-3 rounded-lg bg-alert-error-bg p-4 text-sm text-alert-error-text"
        >
          <BaseIcon
            name="ExclamationTriangleIcon"
            class="h-5 w-5 shrink-0"
            aria-hidden="true"
          />
          <div>
            <p class="font-medium">{{ $t('recurring_invoices.last_run_failed') }}</p>
            <p class="mt-1">{{ lastError }}</p>
          </div>
        </div>

        <!-- What each invoice will be, and when the next one goes out -->
        <BaseStatStrip :columns="4">
          <BaseStat :label="$t('recurring_invoices.amount')" emphasis>
            <BaseFormatMoney :amount="recurringInvoice.total" :currency="documentCurrency" />
          </BaseStat>
          <BaseStat :label="$t('recurring_invoices.frequency.label')" wide>
            {{ frequencyText || '-' }}
          </BaseStat>
          <BaseStat :label="$t('recurring_invoices.next_invoice_date')">
            {{
              recurringInvoice.status === 'COMPLETED'
                ? '-'
                : recurringInvoice.formatted_next_invoice_at || '-'
            }}
          </BaseStat>
          <BaseStat :label="$t('recurring_invoices.starts_at')">
            {{ recurringInvoice.formatted_starts_at || '-' }}
          </BaseStat>
        </BaseStatStrip>

        <BaseCard>
          <BaseHeading>
            {{ $t('customers.basic_info') }}
          </BaseHeading>

          <BaseDescriptionList>
            <BaseDescriptionListItem
              v-if="recurringInvoice.customer?.name"
              :label="$t('invoices.customer')"
            >
              <router-link
                :to="`/admin/customers/${recurringInvoice.customer.id}/view`"
                class="hover:text-primary-600"
              >
                {{ recurringInvoice.customer.name }}
              </router-link>
            </BaseDescriptionListItem>

            <BaseDescriptionListItem
              v-if="recurringInvoice.limit_by !== 'NONE'"
              :label="$t('recurring_invoices.limit_by')"
              :value="limitByLabel"
            />

            <BaseDescriptionListItem
              v-if="recurringInvoice.limit_date && recurringInvoice.limit_by !== 'NONE'"
              :label="$t('recurring_invoices.limit_date')"
              :value="recurringInvoice.formatted_limit_date || recurringInvoice.limit_date || ''"
            />

            <BaseDescriptionListItem
              v-if="recurringInvoice.limit_by === 'COUNT'"
              :label="$t('recurring_invoices.limit_count')"
              :value="recurringInvoice.limit_count ?? ''"
            />

            <BaseDescriptionListItem
              :label="$t('recurring_invoices.send_automatically')"
              :value="recurringInvoice.send_automatically ? $t('general.yes') : $t('general.no')"
            />

            <BaseDescriptionListItem
              :label="$t('recurring_invoices.notify_creator')"
              :value="recurringInvoice.notify_creator ? $t('general.yes') : $t('general.no')"
            />
          </BaseDescriptionList>
        </BaseCard>

        <section class="flex flex-col gap-3">
          <BaseHeading>
            {{ $t('invoices.title', 2) }}
          </BaseHeading>

          <BaseTable
            :data="recurringInvoice.invoices ?? []"
            :columns="invoiceColumns"
            :row-to="invoiceRoute"
            :placeholder-count="5"
          >
            <!-- Invoice date -->
            <template #cell-invoice_date="{ row }">
              {{ row.data.formatted_invoice_date }}
            </template>

            <!-- Invoice Number -->
            <template #cell-invoice_number="{ row }">
              <router-link
                :to="{ path: `/admin/invoices/${row.data.id}/view` }"
                class="font-medium text-primary-600 hover:text-primary-700"
              >
                {{ row.data.invoice_number }}
              </router-link>
            </template>

            <!-- Invoice total -->
            <template #cell-total="{ row }">
              <BaseFormatMoney
                :amount="row.data.due_amount"
                :currency="row.data.currency"
              />
            </template>

            <!-- Invoice status -->
            <template #cell-status="{ row }">
              <BaseInvoiceStatusBadge :status="row.data.status">
                <BaseInvoiceStatusLabel :status="row.data.status" />
              </BaseInvoiceStatusBadge>
            </template>
            <!-- The schedule has not run yet -->
            <template #empty>
              <BaseEmptyPlaceholder
                compact
                art="invoice"
                :title="$t('recurring_invoices.no_generated_invoices')"
                :description="$t('recurring_invoices.no_generated_invoices_description')"
              />
            </template>
          </BaseTable>
        </section>
      </template>
    </BasePage>

    <!-- The other recurring invoices, beside this one (wide screens only) -->
    <RecordListPane
      ref="listPane"
      :search="searchData.searchText"
      :sort-options="sortOptions"
      :sort-field="searchData.orderByField"
      :ascending="getOrderBy"
      :loading="isSidebarLoading"
      :empty="!invoiceList?.length"
      :empty-text="$t('recurring_invoices.no_matching_invoices')"
      @update:search="onSearchText"
      @update:sort-field="setSortField"
      @toggle-order="sortData"
    >
      <RecordListItem
        v-for="invoice in (invoiceList ?? []).filter(Boolean)"
        :id="'recurring-invoice-' + invoice.id"
        :key="invoice.id"
        :to="`/admin/recurring-invoices/${invoice.id}/view`"
        :active="hasActiveUrl(invoice.id)"
        :title="invoice.customer?.name ?? ''"
        :subtitle="getFrequencyLabel(invoice.frequency)"
        :meta="invoice.formatted_starts_at"
      >
        <template #badges>
          <BaseRecurringInvoiceStatusBadge :status="invoice.status">
            <BaseRecurringInvoiceStatusLabel :status="invoice.status" />
          </BaseRecurringInvoiceStatusBadge>
        </template>
        <template #amount>
          <BaseFormatMoney :amount="invoice.total" :currency="invoice.customer?.currency" />
        </template>
      </RecordListItem>
    </RecordListPane>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useRecurringInvoiceStore } from '../store'
import RecurringInvoiceDropdown from '../components/RecurringInvoiceDropdown.vue'
import RecordListPane from '@/scripts/components/layout/RecordListPane.vue'
import RecordListItem from '@/scripts/components/layout/RecordListItem.vue'
import { useUserStore } from '../../../../stores/user.store'
import type { RecurringInvoice } from '../../../../types/domain/recurring-invoice'
import type { CurrencyConfig } from '@/scripts/utils/format-money'
import { scrollBehavior } from '@/scripts/utils/motion'
import { recurringInvoiceService } from '@/scripts/api/services/recurring-invoice.service'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import { useNotificationStore } from '@/scripts/stores/notification.store'

interface Props {
  canEdit?: boolean
  canView?: boolean
  canDelete?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canEdit: false,
  canView: false,
  canDelete: false,
})

const ABILITIES = {
  EDIT: 'edit-recurring-invoice',
  VIEW: 'view-recurring-invoice',
  DELETE: 'delete-recurring-invoice',
} as const

const recurringInvoiceStore = useRecurringInvoiceStore()
const userStore = useUserStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()

// ---------------------------------------------------------------------------
// Ability checks
// ---------------------------------------------------------------------------

const canEdit = computed<boolean>(() => {
  return props.canEdit || userStore.hasAbilities(ABILITIES.EDIT)
})

const canView = computed<boolean>(() => {
  return props.canView || userStore.hasAbilities(ABILITIES.VIEW)
})

const canDelete = computed<boolean>(() => {
  return props.canDelete || userStore.hasAbilities(ABILITIES.DELETE)
})

const hasAtLeastOneAbility = computed<boolean>(() => {
  return canDelete.value || canEdit.value
})

// ---------------------------------------------------------------------------
// Page title
// ---------------------------------------------------------------------------

const pageTitle = computed<string>(() => {
  return recurringInvoiceStore.newRecurringInvoice?.customer?.name ?? ''
})

const isFetching = computed<boolean>(() => recurringInvoiceStore.isFetchingViewData)

// The form's state holds the fetched record, response fields included
const recurringInvoice = computed(() => {
  return recurringInvoiceStore.newRecurringInvoice as typeof recurringInvoiceStore.newRecurringInvoice &
    Partial<Pick<RecurringInvoice, 'formatted_starts_at' | 'formatted_next_invoice_at' | 'formatted_limit_date'>>
})

// ---------------------------------------------------------------------------
// Pause and resume, and why the last run failed
// ---------------------------------------------------------------------------

const isActing = ref(false)

const canToggle = computed<boolean>(() =>
  ['ACTIVE', 'ON_HOLD'].includes(recurringInvoice.value.status),
)

async function toggleStatus(): Promise<void> {
  if (isActing.value || !recurringInvoice.value.id) return
  const action = recurringInvoice.value.status === 'ACTIVE' ? 'pause' : 'resume'
  isActing.value = true
  try {
    await recurringInvoiceService.act(Number(recurringInvoice.value.id), action)
    await recurringInvoiceStore.fetchRecurringInvoice(Number(recurringInvoice.value.id))
    notificationStore.showNotification({
      type: 'success',
      message: t(action === 'pause' ? 'recurring_invoices.paused_message' : 'recurring_invoices.resumed_message'),
    })
  } catch (err) {
    const { message, validationErrors } = handleApiError(err)
    const code = Object.values(validationErrors ?? {}).flat()[0] ?? message
    const key = getErrorTranslationKey(code)
    notificationStore.showNotification({ type: 'error', message: key ? t(key) : code })
  } finally {
    isActing.value = false
  }
}

const lastError = computed<string>(() => {
  const key = getErrorTranslationKey(recurringInvoice.value.last_error || '')
  return key ? t(key) : t('errors.recurring_invoice_failed')
})

const limitByLabel = computed<string>(() => {
  const labels: Record<string, string> = {
    DATE: t('recurring_invoices.limit.date'),
    COUNT: t('recurring_invoices.limit.count'),
  }
  return labels[recurringInvoice.value.limit_by] ?? String(recurringInvoice.value.limit_by)
})

const documentCurrency = computed<CurrencyConfig | null>(() => {
  const currency = recurringInvoice.value.currency ?? recurringInvoice.value.customer?.currency

  return currency && typeof currency === 'object' ? currency as CurrencyConfig : null
})

// ---------------------------------------------------------------------------
// Frequency label
// ---------------------------------------------------------------------------

const selectedFrequencyLabel = computed<string>(() => {
  const inv = recurringInvoiceStore.newRecurringInvoice
  if (inv?.selectedFrequency?.label) {
    return inv.selectedFrequency.label
  }
  return inv?.frequency ?? ''
})

// A custom schedule reads better as its cron expression than as "Custom"
const frequencyText = computed<string>(() => {
  const inv = recurringInvoiceStore.newRecurringInvoice

  if (inv?.selectedFrequency?.value === 'CUSTOM' && inv.frequency) {
    return inv.frequency
  }

  return selectedFrequencyLabel.value
})

function getFrequencyLabel(frequencyFormat: string): string {
  const frequencyObj = recurringInvoiceStore.frequencies.find(
    (f) => f.value === frequencyFormat,
  )
  return frequencyObj ? frequencyObj.label : frequencyFormat
}

// ---------------------------------------------------------------------------
// Invoices table columns
// ---------------------------------------------------------------------------

const invoiceColumns = computed(() => {
  return [
    {
      key: 'invoice_date',
      label: t('invoices.date'),
      thClass: 'extra',
      tdClass: 'font-medium text-heading',
      mobile: 'subtitle' as const,
    },
    { key: 'invoice_number', label: t('invoices.invoice'), mobile: 'title' as const },
    { key: 'customer.name', label: t('invoices.customer') },
    { key: 'status', label: t('invoices.status'), mobile: 'badge' as const },
    { key: 'total', label: t('invoices.total'), align: 'end' as const, mobile: 'trailing' as const },
  ]
})

function invoiceRoute(row: { id?: number | string }): string | null {
  return row.id ? `/admin/invoices/${row.id}/view` : null
}

// ---------------------------------------------------------------------------
// Sidebar state
// ---------------------------------------------------------------------------

const isSidebarLoading = ref<boolean>(false)
const invoiceList = ref<RecurringInvoice[] | null>(null)
const currentPageNumber = ref<number>(1)
const lastPageNumber = ref<number>(1)
const listPane = ref<InstanceType<typeof RecordListPane> | null>(null)
const invoiceListSection = computed<HTMLElement | null>(() => listPane.value?.listEl ?? null)

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

const getOrderBy = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || searchData.orderBy === null
})

const sortOptions = computed(() => [
  { value: 'next_invoice_at', label: t('recurring_invoices.next_invoice_date') },
  { value: 'starts_at', label: t('recurring_invoices.starts_at') },
])

function setSortField(field: string): void {
  searchData.orderByField = field
  onSearched()
}

function onSearchText(value: string): void {
  searchData.searchText = value
  onSearched()
}

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

// ---------------------------------------------------------------------------
// Sidebar data loading
// ---------------------------------------------------------------------------

async function loadRecurringInvoices(
  pageNumber?: number,
  fromScrollListener = false,
): Promise<void> {
  if (isSidebarLoading.value) return

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

  isSidebarLoading.value = true
  const response = await recurringInvoiceStore.fetchRecurringInvoices({
    page: pageNumber,
    ...params,
  } as never)
  isSidebarLoading.value = false

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
    loadRecurringInvoices(++currentPageNumber.value)
  }

  if (invoiceFound && !fromScrollListener) {
    setTimeout(() => scrollToRecurringInvoice(), 500)
  }
}

function scrollToRecurringInvoice(): void {
  const el = document.getElementById(`recurring-invoice-${route.params.id}`)
  const list = invoiceListSection.value
  if (el && list) {
    // Scroll the list pane alone; scrollIntoView would also move the page
    list.scrollTo({ top: el.offsetTop - list.offsetTop - 8, behavior: scrollBehavior() })
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
        loadRecurringInvoices(++currentPageNumber.value, true)
      }
    }
  })
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null

function onSearched(): void {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    invoiceList.value = []
    loadRecurringInvoices()
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

// ---------------------------------------------------------------------------
// Main content loading
// ---------------------------------------------------------------------------

async function loadRecurringInvoice(): Promise<void> {
  if (route.params.id) {
    await recurringInvoiceStore.fetchRecurringInvoice(Number(route.params.id))
  }
}

// ---------------------------------------------------------------------------
// Watch route changes to reload data
// ---------------------------------------------------------------------------

watch(
  () => route.params.id,
  (newId) => {
    if (newId && route.name === 'recurring-invoices.view') {
      loadRecurringInvoice()
    }
  },
)

// ---------------------------------------------------------------------------
// Initialize
// ---------------------------------------------------------------------------

recurringInvoiceStore.initFrequencies(t)
loadRecurringInvoices()
loadRecurringInvoice()
</script>
