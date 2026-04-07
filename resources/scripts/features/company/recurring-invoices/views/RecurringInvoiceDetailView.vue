<template>
  <BasePage class="xl:pl-96">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <RecurringInvoiceDropdown
          v-if="hasAtLeastOneAbility"
          :row="recurringInvoiceStore.newRecurringInvoice"
          :can-edit="canEdit"
          :can-view="canView"
          :can-delete="canDelete"
        />
      </template>
    </BasePageHeader>

    <!-- LEFT SIDEBAR (fixed) -->
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
                <BaseIcon name="FunnelIcon" class="h-5" />
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
                  id="filter_next_invoice_date"
                  v-model="searchData.orderByField"
                  :label="$t('recurring_invoices.next_invoice_date')"
                  size="sm"
                  name="filter"
                  value="next_invoice_at"
                  @update:model-value="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-1 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_start_date"
                  v-model="searchData.orderByField"
                  :label="$t('recurring_invoices.starts_at')"
                  value="starts_at"
                  size="sm"
                  name="filter"
                  @update:model-value="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>
          </BaseDropdown>

          <BaseButton class="ml-1" size="md" variant="gray" @click="sortData">
            <BaseIcon v-if="getOrderBy" name="BarsArrowUpIcon" class="h-5" />
            <BaseIcon v-else name="BarsArrowDownIcon" class="h-5" />
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
            :id="'recurring-invoice-' + invoice.id"
            :to="`/admin/recurring-invoices/${invoice.id}/view`"
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
              <BaseRecurringInvoiceStatusBadge
                :status="invoice.status"
                class="px-1 text-xs"
              >
                <BaseRecurringInvoiceStatusLabel :status="invoice.status" />
              </BaseRecurringInvoiceStatusBadge>
            </div>

            <div class="flex-1 whitespace-nowrap right">
              <BaseFormatMoney
                class="block mb-2 text-xl not-italic font-semibold leading-8 text-right text-heading"
                :amount="invoice.total"
                :currency="invoice.customer?.currency"
              />

              <div
                class="text-sm not-italic font-normal leading-5 text-right text-body est-date"
              >
                {{ invoice.formatted_starts_at }}
              </div>
            </div>
          </router-link>
        </div>
        <div v-if="isSidebarLoading" class="flex justify-center p-4 items-center">
          <LoadingIcon class="h-6 m-1 animate-spin text-primary-400" />
        </div>
        <p
          v-if="!invoiceList?.length && !isSidebarLoading"
          class="flex justify-center px-4 mt-5 text-sm text-body"
        >
          {{ $t('invoices.no_matching_invoices') }}
        </p>
      </div>
    </div>

    <!-- MAIN CONTENT -->
    <div
      v-if="recurringInvoiceStore.isFetchingViewData"
      class="flex justify-center p-12"
    >
      <LoadingIcon class="h-8 animate-spin text-primary-400" />
    </div>

    <BaseCard v-else class="mt-10">
      <BaseHeading>
        {{ $t('customers.basic_info') }}
      </BaseHeading>

      <BaseDescriptionList class="mt-5">
        <BaseDescriptionListItem
          :label="$t('recurring_invoices.starts_at')"
          :content-loading="recurringInvoiceStore.isFetchingViewData"
          :value="recurringInvoiceStore.newRecurringInvoice?.formatted_starts_at"
        />

        <BaseDescriptionListItem
          :label="$t('recurring_invoices.next_invoice_date')"
          :content-loading="recurringInvoiceStore.isFetchingViewData"
          :value="recurringInvoiceStore.newRecurringInvoice?.formatted_next_invoice_at"
        />

        <BaseDescriptionListItem
          v-if="selectedFrequencyLabel"
          :label="$t('recurring_invoices.frequency.title')"
          :value="selectedFrequencyLabel"
          :content-loading="recurringInvoiceStore.isFetchingViewData"
        />

        <BaseDescriptionListItem
          v-if="
            recurringInvoiceStore.newRecurringInvoice?.limit_by !== 'NONE'
          "
          :label="$t('recurring_invoices.limit_by')"
          :content-loading="recurringInvoiceStore.isFetchingViewData"
          :value="recurringInvoiceStore.newRecurringInvoice?.limit_by"
        />

        <BaseDescriptionListItem
          v-if="
            recurringInvoiceStore.newRecurringInvoice?.limit_date &&
            recurringInvoiceStore.newRecurringInvoice?.limit_by !== 'NONE'
          "
          :label="$t('recurring_invoices.limit_date')"
          :content-loading="recurringInvoiceStore.isFetchingViewData"
          :value="recurringInvoiceStore.newRecurringInvoice?.limit_date"
        />

        <BaseDescriptionListItem
          v-if="recurringInvoiceStore.newRecurringInvoice?.limit_by === 'COUNT'"
          :label="$t('recurring_invoices.limit_count')"
          :value="recurringInvoiceStore.newRecurringInvoice?.limit_count"
          :content-loading="recurringInvoiceStore.isFetchingViewData"
        />

        <BaseDescriptionListItem
          :label="$t('recurring_invoices.send_automatically')"
          :content-loading="recurringInvoiceStore.isFetchingViewData"
          :value="
            recurringInvoiceStore.newRecurringInvoice?.send_automatically
              ? $t('general.yes')
              : $t('general.no')
          "
        />
      </BaseDescriptionList>

      <BaseHeading class="mt-8">
        {{ $t('invoices.title', 2) }}
      </BaseHeading>

      <div class="relative table-container">
        <BaseTable
          :data="recurringInvoiceStore.newRecurringInvoice.invoices"
          :columns="invoiceColumns"
          :loading="recurringInvoiceStore.isFetchingViewData"
          :placeholder-count="5"
          class="mt-5"
        >
          <!-- Invoice date -->
          <template #cell-invoice_date="{ row }">
            {{ row.data.formatted_invoice_date }}
          </template>

          <!-- Invoice Number -->
          <template #cell-invoice_number="{ row }">
            <router-link
              :to="{ path: `/admin/invoices/${row.data.id}/view` }"
              class="font-medium text-primary-500"
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
            <BaseInvoiceStatusBadge :status="row.data.status" class="px-3 py-1">
              <BaseInvoiceStatusLabel :status="row.data.status" />
            </BaseInvoiceStatusBadge>
          </template>
        </BaseTable>
      </div>
    </BaseCard>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useRecurringInvoiceStore } from '../store'
import RecurringInvoiceDropdown from '../components/RecurringInvoiceDropdown.vue'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'
import { useUserStore } from '../../../../stores/user.store'
import type { RecurringInvoice } from '../../../../types/domain/recurring-invoice'

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
    },
    { key: 'invoice_number', label: t('invoices.invoice') },
    { key: 'customer.name', label: t('invoices.customer') },
    { key: 'status', label: t('invoices.status') },
    { key: 'total', label: t('invoices.total') },
  ]
})

// ---------------------------------------------------------------------------
// Sidebar state
// ---------------------------------------------------------------------------

const isSidebarLoading = ref<boolean>(false)
const invoiceList = ref<RecurringInvoice[] | null>(null)
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

const getOrderBy = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || searchData.orderBy === null
})

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
