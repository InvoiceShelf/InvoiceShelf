<template>
  <BasePage class="xl:pl-96">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <BaseButton
          :disabled="isSendingEmail"
          variant="primary-outline"
          class="mr-2"
          tag="a"
          :href="downloadLink"
          download
        >
          <template #left="slotProps">
            <BaseIcon name="DownloadIcon" :class="slotProps.class" />
            {{ $t('invoices.download') }}
          </template>
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

    <!-- Sidebar -->
    <div
      class="fixed top-0 left-0 hidden h-full pt-16 pb-4 bg-surface w-88 xl:block"
    >
      <div
        class="flex items-center justify-between px-4 pt-8 pb-6 border border-line-default border-solid"
      >
        <BaseInput
          v-model="searchData.invoice_number"
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
              <BaseDropdownItem class="pt-3 rounded-md hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_invoice_date"
                    v-model="searchData.orderByField"
                    :label="$t('invoices.invoice_date')"
                    name="filter"
                    size="sm"
                    value="invoice_date"
                    @update:model-value="onSearchDebounced"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>

            <div class="px-2">
              <BaseDropdownItem class="pt-3 rounded-md hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_due_date"
                    v-model="searchData.orderByField"
                    :label="$t('invoices.due_date')"
                    name="filter"
                    size="sm"
                    value="due_date"
                    @update:model-value="onSearchDebounced"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>

            <div class="px-2">
              <BaseDropdownItem class="pt-3 rounded-md hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_invoice_number"
                    v-model="searchData.orderByField"
                    :label="$t('invoices.invoice_number')"
                    size="sm"
                    name="filter"
                    value="invoice_number"
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
          v-for="(inv, index) in store.invoices"
          :id="'invoice-' + inv.id"
          :key="index"
          :to="`/${store.companySlug}/customer/invoices/${inv.id}/view`"
          :class="[
            'flex justify-between p-4 items-center cursor-pointer hover:bg-hover-strong border-l-4 border-l-transparent',
            {
              'bg-surface-tertiary border-l-4 border-l-primary-500 border-solid':
                hasActiveUrl(inv.id),
            },
          ]"
          style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
        >
          <div class="flex-2">
            <div class="mb-1 not-italic font-medium leading-5 text-muted capitalize text-md">
              {{ inv.invoice_number }}
            </div>
            <BaseInvoiceStatusBadge :status="inv.status">
              <BaseInvoiceStatusLabel :status="inv.status" />
            </BaseInvoiceStatusBadge>
          </div>

          <div class="flex-1 whitespace-nowrap right">
            <BaseFormatMoney
              class="mb-2 text-xl not-italic font-semibold leading-8 text-right text-heading block"
              :amount="inv.total"
              :currency="inv.currency"
            />
            <div class="text-sm text-right text-muted non-italic">
              {{ inv.formatted_invoice_date }}
            </div>
          </div>
        </router-link>

        <p
          v-if="!store.invoices.length"
          class="flex justify-center px-4 mt-5 text-sm text-body"
        >
          {{ $t('invoices.no_matching_invoices') }}
        </p>
      </div>
    </div>

    <!-- PDF Preview -->
    <div class="flex flex-col min-h-0 mt-8 overflow-hidden" style="height: 75vh">
      <iframe
        v-if="shareableLink"
        :src="shareableLink"
        class="flex-1 border border-gray-400 border-solid rounded-md"
      />
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { useCustomerPortalStore } from '../store'
import type { Invoice } from '../../../types/domain/invoice'

const store = useCustomerPortalStore()
const route = useRoute()
const router = useRouter()

const invoice = ref<Partial<Invoice>>({})
const isSendingEmail = ref<boolean>(false)

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
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
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
