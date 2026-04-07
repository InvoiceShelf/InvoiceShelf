<script setup lang="ts">
import { computed, ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useCustomerStore } from '../store'
import { useDebounceFn } from '@vueuse/core'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'

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
  currency: Record<string, unknown> | null
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
const customerListSection = ref<HTMLElement | null>(null)

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

  if (!customerList.value) customerList.value = []
  customerList.value = [...customerList.value, ...response.data]

  currentPageNumber.value = pageNumber ?? 1
  lastPageNumber.value = response.meta.last_page

  const customerFound = customerList.value.find(
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
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
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
  <div
    class="fixed top-0 left-0 hidden h-full pt-16 pb-[6.6rem] ml-56 bg-surface xl:ml-64 w-88 xl:block"
  >
    <div
      class="flex items-center justify-between px-4 pt-8 pb-2 border border-line-default border-solid height-full"
    >
      <BaseInput
        v-model="searchData.searchText"
        :placeholder="$t('general.search')"
        container-class="mb-6"
        type="text"
        variant="gray"
        @input="onSearch()"
      >
        <BaseIcon name="MagnifyingGlassIcon" class="text-muted" />
      </BaseInput>

      <div class="flex mb-6 ml-3" role="group" aria-label="First group">
        <BaseDropdown
          :close-on-select="false"
          position="bottom-start"
          width-class="w-40"
          position-class="left-0"
        >
          <template #activator>
            <BaseButton variant="gray">
              <BaseIcon name="FunnelIcon" />
            </BaseButton>
          </template>

          <div
            class="px-4 py-3 pb-2 mb-2 text-sm border-b border-line-default border-solid"
          >
            {{ $t('general.sort_by') }}
          </div>

          <div class="px-2">
            <BaseDropdownItem
              class="flex px-1 py-2 mt-1 cursor-pointer hover:rounded-md"
            >
              <BaseInputGroup class="pt-2 -mt-4">
                <BaseRadio
                  id="filter_create_date"
                  v-model="searchData.orderByField"
                  :label="$t('customers.create_date')"
                  size="sm"
                  name="filter"
                  value="invoices.created_at"
                  @update:model-value="onSearch"
                />
              </BaseInputGroup>
            </BaseDropdownItem>
          </div>

          <div class="px-2">
            <BaseDropdownItem
              class="flex px-1 cursor-pointer hover:rounded-md"
            >
              <BaseInputGroup class="pt-2 -mt-4">
                <BaseRadio
                  id="filter_display_name"
                  v-model="searchData.orderByField"
                  :label="$t('customers.display_name')"
                  size="sm"
                  name="filter"
                  value="name"
                  @update:model-value="onSearch"
                />
              </BaseInputGroup>
            </BaseDropdownItem>
          </div>
        </BaseDropdown>

        <BaseButton class="ml-1" size="md" variant="gray" @click="sortData">
          <BaseIcon v-if="getOrderBy" name="SortAscendingIcon" />
          <BaseIcon v-else name="SortDescendingIcon" />
        </BaseButton>
      </div>
    </div>

    <div
      ref="customerListSection"
      class="h-full overflow-y-scroll border-l border-line-default border-solid sidebar base-scroll"
    >
      <div v-for="(customer, index) in customerList" :key="index">
        <router-link
          v-if="customer"
          :id="'customer-' + customer.id"
          :to="`/admin/customers/${customer.id}/view`"
          :class="[
            'flex justify-between p-4 items-center cursor-pointer hover:bg-hover-strong border-l-4 border-l-transparent',
            {
              'bg-surface-tertiary border-l-4 border-l-primary-500 border-solid':
                hasActiveUrl(customer.id),
            },
          ]"
          style="border-top: 1px solid rgba(185, 193, 209, 0.41)"
        >
          <div>
            <BaseText
              :text="customer.name"
              class="pr-2 text-sm not-italic font-normal leading-5 text-heading capitalize truncate"
            />

            <BaseText
              v-if="customer.contact_name"
              :text="customer.contact_name"
              class="mt-1 text-xs not-italic font-medium leading-5 text-body"
            />
          </div>
          <div class="flex-1 font-bold text-right whitespace-nowrap">
            <BaseFormatMoney
              :amount="customer.due_amount !== null ? customer.due_amount : 0"
              :currency="customer.currency"
            />
          </div>
        </router-link>
      </div>
      <div v-if="isFetching" class="flex justify-center p-4 items-center">
        <LoadingIcon class="h-6 m-1 animate-spin text-primary-400" />
      </div>
      <p
        v-if="!customerList?.length && !isFetching"
        class="flex justify-center px-4 mt-5 text-sm text-body"
      >
        {{ $t('customers.no_matching_customers') }}
      </p>
    </div>
  </div>
</template>
