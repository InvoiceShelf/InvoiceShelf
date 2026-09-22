<script setup lang="ts">
import { computed, ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useCustomerStore } from '../store'
import { useDebounceFn } from '@vueuse/core'
import RecordListPane from '@/scripts/components/layout/RecordListPane.vue'
import RecordListItem from '@/scripts/components/layout/RecordListItem.vue'
import type { Currency } from '@/scripts/types/domain/currency'

interface SearchData {
  orderBy: string | null
  orderByField: string | null
  searchText: string | null
}

interface CustomerListItem {
  id: number
  name: string
  contact_name: string | null
  due_amount: number | null
  account_balance?: number | null
  currency: Currency | null
}

const customerStore = useCustomerStore()
const route = useRoute()
const { t } = useI18n()

const isFetching = ref<boolean>(false)

const searchData = reactive<SearchData>({
  orderBy: null,
  orderByField: null,
  searchText: null,
})

const customerList = ref<CustomerListItem[] | null>(null)
const currentPageNumber = ref<number>(1)
const lastPageNumber = ref<number>(1)
const listPane = ref<InstanceType<typeof RecordListPane> | null>(null)
const customerListSection = computed<HTMLElement | null>(() => listPane.value?.listEl ?? null)

const sortOptions = computed(() => [
  { value: 'invoices.created_at', label: t('customers.create_date') },
  { value: 'name', label: t('customers.display_name') },
])

function onSearchText(value: string): void {
  searchData.searchText = value
  onSearch()
}

function setSortField(field: string): void {
  searchData.orderByField = field
  onSearch()
}

function balanceOf(customer: CustomerListItem): number {
  return customer.account_balance ?? customer.due_amount ?? 0
}

const onSearch = useDebounceFn(async () => {
  customerList.value = []
  loadCustomers()
}, 500)

const getOrderBy = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || searchData.orderBy === null
})

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

async function loadCustomers(
  pageNumber?: number,
  fromScrollListener = false
): Promise<void> {
  if (isFetching.value) return

  const params: Record<string, unknown> = {}

  if (searchData.searchText) {
    params.display_name = searchData.searchText
  }
  if (searchData.orderBy) {
    params.orderBy = searchData.orderBy
  }
  if (searchData.orderByField) {
    params.orderByField = searchData.orderByField
  }

  isFetching.value = true
  const response = await customerStore.fetchCustomers({
    page: pageNumber ?? 1,
    ...params,
    limit: 15,
  })
  isFetching.value = false

  const nextCustomers: CustomerListItem[] = [
    ...(customerList.value ?? []),
    ...response.data.map((customer) => ({
      id: customer.id,
      name: customer.name,
      contact_name: customer.contact_name,
      due_amount: customer.due_amount,
      account_balance: customer.account_balance,
      currency: customer.currency ?? null,
    })),
  ]
  customerList.value = nextCustomers

  currentPageNumber.value = pageNumber ?? 1
  lastPageNumber.value = response.meta.last_page

  const customerFound = nextCustomers.find(
    (cust) => cust.id === Number(route.params.id)
  )

  if (
    !fromScrollListener &&
    !customerFound &&
    currentPageNumber.value < lastPageNumber.value &&
    Object.keys(params).length === 0
  ) {
    loadCustomers(++currentPageNumber.value)
  }

  if (customerFound) {
    setTimeout(() => {
      if (!fromScrollListener) {
        scrollToCustomer()
      }
    }, 500)
  }
}

function scrollToCustomer(): void {
  const el = document.getElementById(`customer-${route.params.id}`)
  const list = customerListSection.value
  if (el && list) {
    // Scroll the list pane alone; scrollIntoView would also move the page
    list.scrollTo({ top: el.offsetTop - list.offsetTop - 8, behavior: 'smooth' })
    el.classList.add('shake')
    addScrollListener()
  }
}

function addScrollListener(): void {
  customerListSection.value?.addEventListener('scroll', (ev: Event) => {
    const target = ev.target as HTMLElement
    if (
      target.scrollTop > 0 &&
      target.scrollTop + target.clientHeight > target.scrollHeight - 200
    ) {
      if (currentPageNumber.value < lastPageNumber.value) {
        loadCustomers(++currentPageNumber.value, true)
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

loadCustomers()
</script>

<template>
  <RecordListPane
    ref="listPane"
    :search="searchData.searchText"
    :sort-options="sortOptions"
    :sort-field="searchData.orderByField"
    :ascending="getOrderBy"
    :loading="isFetching"
    :empty="!customerList?.length"
    :empty-text="$t('customers.no_matching_customers')"
    @update:search="onSearchText"
    @update:sort-field="setSortField"
    @toggle-order="sortData"
  >
    <RecordListItem
      v-for="customer in (customerList ?? []).filter(Boolean)"
      :id="'customer-' + customer.id"
      :key="customer.id"
      :to="`/admin/customers/${customer.id}/view`"
      :active="hasActiveUrl(customer.id)"
      :title="customer.name"
      :subtitle="customer.contact_name ?? ''"
      :meta="balanceOf(customer) < 0 ? $t('customers.credit') : ''"
    >
      <template #amount>
        <BaseFormatMoney :amount="Math.abs(balanceOf(customer))" :currency="customer.currency" />
      </template>
    </RecordListItem>
  </RecordListPane>
</template>
