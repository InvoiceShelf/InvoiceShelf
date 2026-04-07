<template>
  <BasePage class="xl:pl-96">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <BaseButton
          :disabled="isSendingEmail"
          variant="primary-outline"
          tag="a"
          download
          :href="downloadLink"
        >
          <template #left="slotProps">
            <BaseIcon name="DownloadIcon" :class="slotProps.class" />
            {{ $t('general.download') }}
          </template>
        </BaseButton>
      </template>
    </BasePageHeader>

    <!-- Sidebar -->
    <div
      class="fixed top-0 left-0 hidden h-full pt-16 pb-4 bg-surface w-88 xl:block"
    >
      <div
        class="flex items-center justify-between px-4 pt-8 pb-6 border border-line-default border-solid"
      >
        <BaseInput
          v-model="searchData.payment_number"
          :placeholder="$t('general.search')"
          type="text"
          variant="gray"
          @input="onSearchDebounced"
        >
          <template #right>
            <BaseIcon name="MagnifyingGlassIcon" class="h-5 text-subtle" />
          </template>
        </BaseInput>

        <div class="flex ml-3" role="group">
          <BaseDropdown
            position="bottom-start"
            width-class="w-50"
            position-class="left-0"
          >
            <template #activator>
              <BaseButton variant="gray">
                <BaseIcon name="FunnelIcon" class="h-5" />
              </BaseButton>
            </template>

            <div class="px-4 py-1 pb-2 mb-2 text-sm border-b border-line-default border-solid">
              {{ $t('general.sort_by') }}
            </div>

            <div class="px-2">
              <BaseDropdownItem class="rounded-md pt-3 hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_invoice_number"
                    v-model="searchData.orderByField"
                    :label="$t('invoices.title')"
                    size="sm"
                    name="filter"
                    value="invoice_number"
                    @update:model-value="onSearchDebounced"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>

            <div class="px-2">
              <BaseDropdownItem class="rounded-md pt-3 hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_payment_date"
                    v-model="searchData.orderByField"
                    :label="$t('payments.date')"
                    size="sm"
                    name="filter"
                    value="payment_date"
                    @update:model-value="onSearchDebounced"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>

            <div class="px-2">
              <BaseDropdownItem class="rounded-md pt-3 hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_payment_number"
                    v-model="searchData.orderByField"
                    :label="$t('payments.payment_number')"
                    size="sm"
                    name="filter"
                    value="payment_number"
                    @update:model-value="onSearchDebounced"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>
          </BaseDropdown>

          <BaseButton class="ml-1" variant="white" @click="sortData">
            <BaseIcon v-if="isAscending" name="SortAscendingIcon" class="h-5" />
            <BaseIcon v-else name="SortDescendingIcon" class="h-5" />
          </BaseButton>
        </div>
      </div>

      <div class="h-full pb-32 overflow-y-scroll border-l border-line-default border-solid sw-scroll">
        <router-link
          v-for="(pmt, index) in store.payments"
          :id="'payment-' + pmt.id"
          :key="index"
          :to="`/${store.companySlug}/customer/payments/${pmt.id}/view`"
          :class="[
            'flex justify-between p-4 items-center cursor-pointer hover:bg-hover-strong border-l-4 border-l-transparent',
            {
              'bg-surface-tertiary border-l-4 border-l-primary-500 border-solid':
                hasActiveUrl(pmt.id),
            },
          ]"
          style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
        >
          <div class="flex-2">
            <div class="mb-1 text-md not-italic font-medium leading-5 text-muted capitalize">
              {{ pmt.payment_number }}
            </div>
          </div>

          <div class="flex-1 whitespace-nowrap right">
            <BaseFormatMoney
              class="mb-2 text-xl not-italic font-semibold leading-8 text-right text-heading block"
              :amount="pmt.amount"
              :currency="pmt.currency"
            />
            <div class="text-sm text-right text-muted non-italic">
              {{ pmt.formatted_payment_date }}
            </div>
          </div>
        </router-link>

        <p
          v-if="!store.payments.length"
          class="flex justify-center px-4 mt-5 text-sm text-body"
        >
          {{ $t('payments.no_matching_payments') }}
        </p>
      </div>
    </div>

    <!-- PDF Preview -->
    <BasePdfPreview :src="shareableLink" />
  </BasePage>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { useCustomerPortalStore } from '../store'
import type { Payment } from '../../../types/domain/payment'

const store = useCustomerPortalStore()
const route = useRoute()

const payment = ref<Partial<Payment>>({})
const isSendingEmail = ref<boolean>(false)

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
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
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

function sortData(): void {
  searchData.orderBy = searchData.orderBy === 'asc' ? 'desc' : 'asc'
  onSearch()
}
</script>
