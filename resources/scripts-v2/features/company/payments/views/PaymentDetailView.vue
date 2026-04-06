<template>
  <BasePage class="xl:pl-96 xl:ml-8">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <BaseButton
          v-if="canSend"
          :content-loading="isFetching"
          variant="primary"
          @click="onPaymentSend"
        >
          {{ $t('payments.send_payment_receipt') }}
        </BaseButton>

        <PaymentDropdown
          :content-loading="isFetching"
          class="ml-3"
          :row="paymentData"
          :can-edit="canEdit"
          :can-view="canView"
          :can-delete="canDelete"
          :can-send="canSend"
        />
      </template>
    </BasePageHeader>

    <!-- Sidebar -->
    <div
      class="fixed top-0 left-0 hidden h-full pt-16 pb-[6rem] ml-56 bg-surface xl:ml-64 w-88 xl:block"
    >
      <div
        class="flex items-center justify-between px-4 pt-8 pb-6 border border-line-default border-solid"
      >
        <BaseInput
          v-model="searchData.searchText"
          :placeholder="$t('general.search')"
          type="text"
          @input="onSearch"
        >
          <BaseIcon name="MagnifyingGlassIcon" class="h-5" />
        </BaseInput>

        <div class="flex ml-3" role="group">
          <BaseDropdown
            position="bottom-start"
            width-class="w-50"
            position-class="left-0"
          >
            <template #activator>
              <BaseButton variant="gray">
                <BaseIcon name="FunnelIcon" />
              </BaseButton>
            </template>

            <div
              class="px-4 py-1 pb-2 mb-2 text-sm border-b border-line-default border-solid"
            >
              {{ $t('general.sort_by') }}
            </div>

            <div class="px-2">
              <BaseDropdownItem class="pt-3 rounded-md hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    v-model="searchData.orderByField"
                    :label="$t('payments.date')"
                    size="sm"
                    name="filter"
                    value="payment_date"
                    @update:model-value="onSearch"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>

            <div class="px-2">
              <BaseDropdownItem class="pt-3 rounded-md hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    v-model="searchData.orderByField"
                    :label="$t('payments.payment_number')"
                    size="sm"
                    name="filter"
                    value="payment_number"
                    @update:model-value="onSearch"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>
          </BaseDropdown>

          <BaseButton class="ml-1" size="md" variant="gray" @click="sortData">
            <BaseIcon v-if="getOrderBy" name="BarsArrowUpIcon" />
            <BaseIcon v-else name="BarsArrowDownIcon" />
          </BaseButton>
        </div>
      </div>

      <div
        ref="paymentListSection"
        class="h-full overflow-y-scroll border-l border-line-default border-solid base-scroll"
      >
        <div v-for="(payment, index) in paymentList" :key="index">
          <router-link
            v-if="payment"
            :id="'payment-' + payment.id"
            :to="`/admin/payments/${payment.id}/view`"
            :class="[
              'flex justify-between p-4 items-center cursor-pointer hover:bg-hover-strong border-l-4 border-l-transparent',
              {
                'bg-surface-tertiary border-l-4 border-l-primary-500 border-solid':
                  hasActiveUrl(payment.id),
              },
            ]"
            style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
          >
            <div class="flex-2">
              <BaseText
                :text="payment.customer?.name ?? ''"
                class="pr-2 mb-2 text-sm not-italic font-normal leading-5 text-heading capitalize truncate"
              />
              <div
                class="mb-1 text-xs not-italic font-medium leading-5 text-muted capitalize"
              >
                {{ payment.payment_number }}
              </div>
            </div>

            <div class="flex-1 whitespace-nowrap right">
              <BaseFormatMoney
                class="block mb-2 text-xl not-italic font-semibold leading-8 text-right text-heading"
                :amount="payment.amount"
                :currency="payment.customer?.currency"
              />
              <div class="text-sm text-right text-muted non-italic">
                {{ payment.formatted_payment_date }}
              </div>
            </div>
          </router-link>
        </div>

        <div v-if="isLoading" class="flex justify-center p-4 items-center">
          <LoadingIcon class="h-6 m-1 animate-spin text-primary-400" />
        </div>
        <p
          v-if="!paymentList?.length && !isLoading"
          class="flex justify-center px-4 mt-5 text-sm text-body"
        >
          {{ $t('payments.no_matching_payments') }}
        </p>
      </div>
    </div>

    <!-- PDF Preview -->
    <BasePdfPreview :src="shareableLink" />

    <SendPaymentModal />
  </BasePage>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { usePaymentStore } from '../store'
import PaymentDropdown from '../components/PaymentDropdown.vue'
import SendPaymentModal from '../components/SendPaymentModal.vue'
import LoadingIcon from '@v2/components/icons/LoadingIcon.vue'
import { useUserStore } from '../../../../stores/user.store'
import { useModalStore } from '../../../../stores/modal.store'
import type { Payment } from '../../../../types/domain/payment'

interface Props {
  canEdit?: boolean
  canView?: boolean
  canDelete?: boolean
  canSend?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canEdit: false,
  canView: false,
  canDelete: false,
  canSend: false,
})

const ABILITIES = {
  EDIT: 'edit-payment',
  VIEW: 'view-payment',
  DELETE: 'delete-payment',
  SEND: 'send-payment',
} as const

const paymentStore = usePaymentStore()
const userStore = useUserStore()
const modalStore = useModalStore()
const { t } = useI18n()
const route = useRoute()

const canEdit = computed<boolean>(() => {
  return props.canEdit || userStore.hasAbilities(ABILITIES.EDIT)
})

const canView = computed<boolean>(() => {
  return props.canView || userStore.hasAbilities(ABILITIES.VIEW)
})

const canDelete = computed<boolean>(() => {
  return props.canDelete || userStore.hasAbilities(ABILITIES.DELETE)
})

const canSend = computed<boolean>(() => {
  return props.canSend || userStore.hasAbilities(ABILITIES.SEND)
})

const paymentData = ref<Payment | Record<string, unknown>>({})
const isFetching = ref<boolean>(false)
const isLoading = ref<boolean>(false)

const paymentList = ref<Payment[] | null>(null)
const currentPageNumber = ref<number>(1)
const lastPageNumber = ref<number>(1)
const paymentListSection = ref<HTMLElement | null>(null)

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

const pageTitle = computed<string>(() => {
  return (paymentData.value as Payment).payment_number ?? ''
})

const getOrderBy = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || searchData.orderBy === null
})

const shareableLink = computed<string | false>(() => {
  const hash = (paymentData.value as Payment).unique_hash
  return hash ? `/payments/pdf/${hash}` : false
})

watch(route, () => {
  loadPayment()
})

loadPayments()
loadPayment()

let searchTimeout: ReturnType<typeof setTimeout> | null = null

function onSearch(): void {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    paymentList.value = []
    loadPayments()
  }, 500)
}

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

async function loadPayments(
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
  const response = await paymentStore.fetchPayments({
    page: pageNumber,
    ...params,
  } as never)
  isLoading.value = false

  paymentList.value = paymentList.value ?? []
  paymentList.value = [...paymentList.value, ...response.data.data]

  currentPageNumber.value = pageNumber ?? 1
  lastPageNumber.value = response.data.meta.last_page

  const paymentFound = paymentList.value.find(
    (p) => p.id === Number(route.params.id),
  )

  if (
    !fromScrollListener &&
    !paymentFound &&
    currentPageNumber.value < lastPageNumber.value &&
    Object.keys(params).length === 0
  ) {
    loadPayments(++currentPageNumber.value)
  }

  if (paymentFound && !fromScrollListener) {
    setTimeout(() => scrollToPayment(), 500)
  }
}

async function loadPayment(): Promise<void> {
  if (!route.params.id) return

  isFetching.value = true
  const response = await paymentStore.fetchPayment(Number(route.params.id))

  if (response.data) {
    isFetching.value = false
    paymentData.value = { ...response.data.data } as Payment
  }
}

function scrollToPayment(): void {
  const el = document.getElementById(`payment-${route.params.id}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
    el.classList.add('shake')
    addScrollListener()
  }
}

function addScrollListener(): void {
  paymentListSection.value?.addEventListener('scroll', (ev) => {
    const target = ev.target as HTMLElement
    if (
      target.scrollTop > 0 &&
      target.scrollTop + target.clientHeight > target.scrollHeight - 200
    ) {
      if (currentPageNumber.value < lastPageNumber.value) {
        loadPayments(++currentPageNumber.value, true)
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

function onPaymentSend(): void {
  modalStore.openModal({
    title: t('payments.send_payment'),
    componentName: 'SendPaymentModal',
    id: (paymentData.value as Payment).id,
    data: paymentData.value,
    variant: 'lg',
    refreshData: () => loadPayment(),
  })
}
</script>
