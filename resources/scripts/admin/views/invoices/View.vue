<script setup>
import { useI18n } from 'vue-i18n'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { debounce } from 'lodash'

import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useModalStore } from '@/scripts/stores/modal'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useDialogStore } from '@/scripts/stores/dialog'

import SendInvoiceModal from '@/scripts/admin/components/modal-components/SendInvoiceModal.vue'
import InvoiceDropdown from '@/scripts/admin/components/dropdowns/InvoiceIndexDropdown.vue'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'

import abilities from '@/scripts/admin/stub/abilities'

const modalStore = useModalStore()
const invoiceStore = useInvoiceStore()
const userStore = useUserStore()
const dialogStore = useDialogStore()

const { t } = useI18n()
const invoiceData = ref(null)
const route = useRoute()

const isMarkAsSent = ref(false)
const isLoading = ref(false)

const invoiceList = ref(null)
const currentPageNumber = ref(1)
const lastPageNumber = ref(1)
const invoiceListSection = ref(null)

const searchData = reactive({
  orderBy: null,
  orderByField: null,
  searchText: null,
})

const pageTitle = computed(() => invoiceData.value.invoice_number)

const getOrderBy = computed(() => {
  if (searchData.orderBy === 'asc' || searchData.orderBy == null) {
    return true
  }
  return false
})

const getOrderName = computed(() => {
  if (getOrderBy.value) {
    return t('general.ascending')
  }
  return t('general.descending')
})

const shareableLink = computed(() => {
  return `/invoices/pdf/${invoiceData.value.unique_hash}`
})

const getCurrentInvoiceId = computed(() => {
  if (invoiceData.value && invoiceData.value.id) {
    return invoice.value.id
  }
  return null
})

watch(route, (to, from) => {
  if (to.name === 'invoices.view') {
    loadInvoice()
  }
})

async function onMarkAsSent() {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('invoices.invoice_mark_as_sent'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (response) => {
      isMarkAsSent.value = false
      if (response) {
        await invoiceStore.markAsSent({
          id: invoiceData.value.id,
          status: 'SENT',
        })
        invoiceData.value.status = 'SENT'
        isMarkAsSent.value = true
      }
      isMarkAsSent.value = false
    })
}

async function onSendInvoice(id) {
  modalStore.openModal({
    title: t('invoices.send_invoice'),
    componentName: 'SendInvoiceModal',
    id: invoiceData.value.id,
    data: invoiceData.value,
  })
}

function hasActiveUrl(id) {
  return route.params.id == id
}

async function loadInvoices(pageNumber, fromScrollListener = false) {
  if (isLoading.value) {
    return
  }

  let params = {}
  if (
    searchData.searchText !== '' &&
    searchData.searchText !== null &&
    searchData.searchText !== undefined
  ) {
    params.search = searchData.searchText
  }

  if (searchData.orderBy !== null && searchData.orderBy !== undefined) {
    params.orderBy = searchData.orderBy
  }

  if (
    searchData.orderByField !== null &&
    searchData.orderByField !== undefined
  ) {
    params.orderByField = searchData.orderByField
  }

  isLoading.value = true
  let response = await invoiceStore.fetchInvoices({
    page: pageNumber,
    ...params,
  })
  isLoading.value = false

  invoiceList.value = invoiceList.value ? invoiceList.value : []
  invoiceList.value = [...invoiceList.value, ...response.data.data]

  currentPageNumber.value = pageNumber ? pageNumber : 1
  lastPageNumber.value = response.data.meta.last_page
  let invoiceFound = invoiceList.value.find((inv) => inv.id == route.params.id)

  if (
    fromScrollListener == false &&
    !invoiceFound &&
    currentPageNumber.value < lastPageNumber.value &&
    Object.keys(params).length === 0
  ) {
    loadInvoices(++currentPageNumber.value)
  }

  if (invoiceFound) {
    setTimeout(() => {
      if (fromScrollListener == false) {
        scrollToInvoice()
      }
    }, 500)
  }
}

function scrollToInvoice() {
  const el = document.getElementById(`invoice-${route.params.id}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
    el.classList.add('shake')
    addScrollListener()
  }
}

function addScrollListener() {
  invoiceListSection.value.addEventListener('scroll', (ev) => {
    if (
      ev.target.scrollTop > 0 &&
      ev.target.scrollTop + ev.target.clientHeight >
        ev.target.scrollHeight - 200
    ) {
      if (currentPageNumber.value < lastPageNumber.value) {
        loadInvoices(++currentPageNumber.value, true)
      }
    }
  })
}

async function loadInvoice() {
  let response = await invoiceStore.fetchInvoice(route.params.id)
  if (response.data) {
    invoiceData.value = { ...response.data.data }
  }
}

async function onSearched() {
  invoiceList.value = []
  loadInvoices()
}

function sortData() {
  if (searchData.orderBy === 'asc') {
    searchData.orderBy = 'desc'
    onSearched()
    return true
  }
  searchData.orderBy = 'asc'
  onSearched()
  return true
}

function updateSentInvoice() {
  let pos = invoiceList.value.findIndex(
    (invoice) => invoice.id === invoiceData.value.id
  )

  if (invoiceList.value[pos]) {
    invoiceList.value[pos].status = 'SENT'
    invoiceData.value.status = 'SENT'
  }
}

loadInvoices()
loadInvoice()
onSearched = debounce(onSearched, 500)
</script>

<template>
  <SendInvoiceModal @update="updateSentInvoice" />

  <BasePage v-if="invoiceData" class="xl:pl-96 xl:ml-8">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <div class="text-sm mr-3">
          <BaseButton
            v-if="
              invoiceData.status === 'DRAFT' &&
              userStore.hasAbilities(abilities.EDIT_INVOICE)
            "
            :disabled="isMarkAsSent"
            variant="primary-outline"
            @click="onMarkAsSent"
          >
            {{ $t('invoices.mark_as_sent') }}
          </BaseButton>
        </div>

        <BaseButton
          v-if="
            invoiceData.status === 'DRAFT' &&
            userStore.hasAbilities(abilities.SEND_INVOICE)
          "
          variant="primary"
          class="text-sm"
          @click="onSendInvoice"
        >
          {{ $t('invoices.send_invoice') }}
        </BaseButton>

        <!-- Record Payment  -->
        <router-link
          v-if="userStore.hasAbilities(abilities.CREATE_PAYMENT)"
          :to="`/admin/payments/${$route.params.id}/create`"
        >
          <BaseButton
            v-if="
              invoiceData.status === 'SENT' || invoiceData.status === 'VIEWED'
            "
            variant="primary"
          >
            {{ $t('invoices.record_payment') }}
          </BaseButton>
        </router-link>

        <!-- Invoice Dropdown  -->
        <InvoiceDropdown
          class="ml-3"
          :row="invoiceData"
          :load-data="loadInvoices"
        />
      </template>
    </BasePageHeader>

    <!-- Sidebar -->
    <div
      class="fixed top-0 left-0 hidden h-full pt-16 pb-[6.4rem] ml-56 bg-white dark:bg-gray-800 xl:ml-64 w-88 xl:block"
    >
      <div
        class="flex items-center justify-between px-4 pt-8 pb-2 border-t border-r border-gray-200 border-solid dark:border-gray-700 height-full"
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
              <BaseIcon
                name="MagnifyingGlassIcon"
                class="text-gray-400 dark:text-gray-300"
              />
            </template>
          </BaseInput>
        </div>

        <div class="flex mb-6 ml-3" role="group" aria-label="First group">
          <BaseDropdown
            class="ml-3"
            position="bottom-start"
            width-class="w-45"
            position-class="left-0"
          >
            <template #activator>
              <BaseButton size="md" variant="gray">
                <BaseIcon name="FunnelIcon" />
              </BaseButton>
            </template>

            <div
              class="px-4 py-1 pb-2 mb-2 text-sm border-b border-gray-200 border-solid dark:border-gray-700 dark:text-gray-300"
            >
              {{ $t('general.sort_by') }}
            </div>

            <BaseDropdownItem class="flex px-4 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_invoice_date"
                  v-model="searchData.orderByField"
                  :label="$t('invoices.invoice_date')"
                  size="sm"
                  name="filter"
                  value="invoice_date"
                  @update:modelValue="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-4 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_due_date"
                  v-model="searchData.orderByField"
                  :label="$t('invoices.due_date')"
                  value="due_date"
                  size="sm"
                  name="filter"
                  @update:modelValue="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-4 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_invoice_number"
                  v-model="searchData.orderByField"
                  :label="$t('invoices.invoice_number')"
                  value="invoice_number"
                  size="sm"
                  name="filter"
                  @update:modelValue="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>
          </BaseDropdown>

          <BaseButton class="ml-1" size="md" variant="gray" @click="sortData()">
            <BaseIcon v-if="getOrderBy" name="SortAscendingIcon" />
            <BaseIcon v-else name="SortDescendingIcon" />
          </BaseButton>
        </div>
      </div>

      <div
        ref="invoiceListSection"
        class="h-full overflow-y-scroll border-l border-r border-gray-200 border-solid dark:border-gray-700 base-scroll"
      >
        <div v-for="(invoice, index) in invoiceList" :key="index">
          <router-link
            v-if="invoice"
            :id="'invoice-' + invoice.id"
            :to="`/admin/invoices/${invoice.id}/view`"
            :class="[
              'flex justify-between side-invoice p-4 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 items-center border-l-4 border-transparent border-b border-gray-200/40 dark:border-gray-700/40',
              {
                'bg-gray-100 dark:bg-gray-700 border-l-4 border-primary-500 border-solid':
                  hasActiveUrl(invoice.id),
              },
            ]"
          >
            <div class="flex-2">
              <BaseText
                :text="invoice.customer.name"
                class="pr-2 mb-2 text-sm not-italic font-normal leading-5 text-black truncate capitalize dark:text-gray-200"
              />

              <div
                class="mt-1 mb-2 text-xs not-italic font-medium leading-5 text-gray-600 dark:text-gray-400"
              >
                {{ invoice.invoice_number }}
              </div>

              <BaseInvoiceStatusBadge
                :status="invoice.status"
                class="px-1 text-xs"
              >
                <BaseInvoiceStatusLabel :status="invoice.status" />
              </BaseInvoiceStatusBadge>
            </div>
            <div class="flex-1 whitespace-nowrap right">
              <BaseFormatMoney
                :amount="invoice.total"
                :currency="invoice.customer.currency"
                class="block mb-2 text-xl not-italic font-semibold leading-8 text-right text-gray-900 dark:text-gray-200"
              />

              <div
                class="text-sm not-italic font-normal leading-5 text-right text-gray-600 dark:text-gray-400 inv-date"
              >
                {{ invoice.formatted_invoice_date }}
              </div>
            </div>
          </router-link>
        </div>
        <div v-if="isLoading" class="flex items-center justify-center p-4">
          <LoadingIcon class="w-6 h-6 m-1 animate-spin text-primary-400" />
        </div>
        <p
          v-if="!invoiceList?.length && !isLoading"
          class="flex justify-center px-4 mt-5 text-sm text-gray-600 dark:text-gray-400"
        >
          {{ $t('invoices.no_matching_invoices') }}
        </p>
      </div>
    </div>

    <!-- main content -->
    <div class="relative">
      <div v-if="invoiceData.customer" class="p-12 bg-white dark:bg-gray-800">
        <div class="flex">
          <div class="flex-1">
            <h4 class="text-lg font-bold text-gray-700 dark:text-gray-200">
              {{ $t('invoices.invoice_from') }}
            </h4>
            <p
              v-if="invoiceStore.company"
              class="text-sm text-gray-600 dark:text-gray-400"
            >
              {{ invoiceStore.company.name }} <br />
              {{ invoiceStore.company.billing.address_street_1 }}
              <br v-if="invoiceStore.company.billing.address_street_2" />
              {{ invoiceStore.company.billing.address_street_2 }}
              <br v-if="
                  invoiceStore.company.billing.city ||
                  invoiceStore.company.billing.state
                "
              />
              {{ invoiceStore.company.billing.city }}
              {{ invoiceStore.company.billing.state }}
              <br v-if="invoiceStore.company.billing.country" />
              {{ invoiceStore.company.billing.country.name }}
              <br v-if="invoiceStore.company.billing.zip" />
              {{ invoiceStore.company.billing.zip }}
            </p>
          </div>
          <div class="flex-1 text-right">
            <h4 class="text-lg font-bold text-gray-700 dark:text-gray-200">
              {{ $t('invoices.invoice_to') }}
            </h4>

            <p class="text-sm text-gray-600 dark:text-gray-400">
              {{ invoiceData.customer.name }} <br />
              {{ invoiceData.billing.address_street_1 }} <br />
              {{ invoiceData.billing.address_street_2 }} <br />
              {{ invoiceData.billing.city }} {{ invoiceData.billing.state }}
              <br />
              {{ invoiceData.billing.country.name }} <br />
              {{ invoiceData.billing.zip }}
            </p>
          </div>
        </div>

        <div class="flex justify-between mt-10">
          <div class="flex-1 text-left">
            <h4 class="text-lg font-bold text-gray-700 dark:text-gray-200">
              {{ $t('invoices.invoice_no') }}
            </h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              {{ invoiceData.invoice_number }}
            </p>
          </div>

          <div class="flex-1 text-right">
            <h4 class="text-lg font-bold text-gray-700 dark:text-gray-200">
              {{ $t('invoices.invoice_date') }}
            </h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              {{ invoiceData.formatted_invoice_date }}
            </p>
          </div>
        </div>

        <div class="flex justify-between mt-4">
          <div class="flex-1 text-left">
            <h4
              v-if="invoiceData.reference_number"
              class="text-lg font-bold text-gray-700 dark:text-gray-200"
            >
              {{ $t('invoices.reference_no') }}
            </h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              {{ invoiceData.reference_number }}
            </p>
          </div>
          <div class="flex-1 text-right">
            <h4 class="text-lg font-bold text-gray-700 dark:text-gray-200">
              {{ $t('invoices.due_date') }}
            </h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              {{ invoiceData.formatted_due_date }}
            </p>
          </div>
        </div>

        <div class="w-full mt-10">
          <table
            class="w-full text-sm text-left text-gray-500 dark:text-gray-400"
          >
            <thead
              class="text-xs text-gray-700 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-700"
            >
              <tr>
                <th scope="col" class="py-3 pl-10 pr-3">
                  {{ $t('invoices.item') }}
                </th>
                <th scope="col" class="px-3 py-3">
                  {{ $t('invoices.quantity') }}
                </th>
                <th scope="col" class="px-3 py-3">
                  {{ $t('invoices.price') }}
                </th>
                <th
                  v-if="invoiceData.discount_per_item === 'YES'"
                  scope="col"
                  class="px-3 py-3"
                >
                  {{ $t('invoices.discount') }}
                </th>
                <th
                  v-for="tax in invoiceData.taxes"
                  v-if="invoiceData.tax_per_item === 'YES'"
                  :key="tax.id"
                  scope="col"
                  class="px-3 py-3"
                >
                  {{ tax.name }}
                </th>
                <th scope="col" class="py-3 pl-3 pr-10 text-right">
                  {{ $t('invoices.total') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in invoiceData.items"
                :key="item.name"
                class="bg-white dark:bg-gray-800 border-b dark:border-gray-700"
              >
                <td class="py-4 pl-10 pr-3 text-base text-gray-900 dark:text-white">
                  {{ item.name }}
                </td>
                <td class="px-3 py-4 text-base">
                  {{ item.quantity }}
                </td>
                <td class="px-3 py-4 text-base">
                  <BaseFormatMoney
                    :amount="item.price"
                    :currency="invoiceData.customer.currency"
                  />
                </td>
                <td
                  v-if="invoiceData.discount_per_item === 'YES'"
                  class="px-3 py-4 text-base"
                >
                  {{ item.discount_val }}
                  <span v-if="item.discount_type == 'fixed'">
                    ({{ $t('invoices.fixed') }})
                  </span>
                  <span v-else>(%)</span>
                </td>
                <td
                  v-for="tax in item.taxes"
                  v-if="invoiceData.tax_per_item === 'YES'"
                  :key="tax.id"
                  class="px-3 py-4 text-base"
                >
                  {{ tax.tax_type.percent }}%
                </td>
                <td class="py-4 pl-3 pr-10 text-base text-right">
                  <BaseFormatMoney
                    :amount="item.total"
                    :currency="invoiceData.customer.currency"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-end mt-10">
          <div class="w-1/2">
            <div class="flex justify-between">
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-400"
                >{{ $t('invoices.sub_total') }}:
              </span>
              <span class="text-sm">
                <BaseFormatMoney
                  :amount="invoiceData.sub_total"
                  :currency="invoiceData.customer.currency"
                />
              </span>
            </div>
            <div
              v-if="invoiceData.tax_per_item === 'NO'"
              class="flex justify-between mt-4"
            >
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-400"
                >{{ $t('invoices.tax') }}:
              </span>
              <span class="text-sm">
                <div
                  v-for="tax in invoiceData.taxes"
                  :key="tax.id"
                  class="text-sm"
                >
                  <span v-if="tax.tax_type.type === 'percentage'">
                    {{ tax.tax_type.percent }}%
                  </span>
                  <span v-else>
                    <BaseFormatMoney
                      :amount="tax.tax_type.amount"
                      :currency="invoiceData.customer.currency"
                    />
                  </span>
                  on
                  <BaseFormatMoney
                    :amount="tax.amount"
                    :currency="invoiceData.customer.currency"
                  />
                </div>
              </span>
            </div>
            <div
              v-if="invoiceData.discount_per_item == 'NO'"
              class="flex justify-between mt-4"
            >
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-400"
                >{{ $t('invoices.discount') }}:</span
              >
              <span class="text-sm">
                {{ invoiceData.discount_val }}
                <span v-if="invoiceData.discount_type == 'fixed'">
                  ({{ $t('invoices.fixed') }})
                </span>
                <span v-else>(%)</span>
              </span>
            </div>

            <div class="h-px my-4 bg-gray-200 dark:bg-gray-700"></div>

            <div class="flex justify-between text-lg">
              <span class="font-bold text-gray-900 dark:text-white"
                >{{ $t('invoices.total') }}:</span
              >
              <span class="font-bold">
                <BaseFormatMoney
                  :amount="invoiceData.total"
                  :currency="invoiceData.customer.currency"
                />
              </span>
            </div>

            <div class="flex justify-between mt-2 text-md">
              <span class="font-bold text-gray-900 dark:text-white"
                >{{ $t('invoices.amount_paid') }}:</span
              >
              <span class="font-bold">
                <BaseFormatMoney
                  :amount="invoiceData.paid_amount"
                  :currency="invoiceData.customer.currency"
                />
              </span>
            </div>

            <div
              class="flex justify-between mt-2 text-xl"
              :class="[
                invoiceData.due_amount > 0
                  ? 'text-red-500'
                  : 'text-green-500',
              ]"
            >
              <span class="font-bold">{{ $t('invoices.amount_due') }}:</span>
              <span class="font-bold">
                <BaseFormatMoney
                  :amount="invoiceData.due_amount"
                  :currency="invoiceData.customer.currency"
                />
              </span>
            </div>
          </div>
        </div>

        <div v-if="invoiceData.notes" class="mt-10">
          <h4 class="text-lg font-bold text-gray-900 dark:text-white">
            {{ $t('invoices.notes') }}
          </h4>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ invoiceData.notes }}
          </p>
        </div>
      </div>
    </div>

    <!-- pdf section -->
    <div
      class="flex flex-col min-h-0 mt-8 overflow-hidden"
      style="height: 75vh"
    >
      <iframe
        :src="`${shareableLink}`"
        class="flex-1 border border-gray-400 border-solid rounded-md bg-white dark:bg-gray-700 dark:border-gray-600 frame-style"
      />
    </div>
  </BasePage>
</template>
