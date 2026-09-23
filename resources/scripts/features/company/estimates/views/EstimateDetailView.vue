<template>
  <div v-if="estimateData" class="flex min-h-full">
    <!-- The other estimates, beside the one on screen (wide screens only) -->
    <RecordListPane
      ref="listPane"
      :search="searchData.searchText"
      :sort-options="sortOptions"
      :sort-field="searchData.orderByField"
      :ascending="getOrderBy"
      :loading="isLoading"
      :empty="!estimateList?.length"
      :empty-text="$t('estimates.no_matching_estimates')"
      @update:search="onSearchText"
      @update:sort-field="setSortField"
      @toggle-order="sortData"
    >
      <RecordListItem
        v-for="estimate in (estimateList ?? []).filter(Boolean)"
        :id="'estimate-' + estimate.id"
        :key="estimate.id"
        :to="`/admin/estimates/${estimate.id}/view`"
        :active="hasActiveUrl(estimate.id)"
        :title="estimate.customer?.name ?? ''"
        :subtitle="estimate.estimate_number"
        :meta="estimate.formatted_estimate_date"
      >
        <template #badges>
          <BaseEstimateStatusBadge :status="estimate.status">
            <BaseEstimateStatusLabel :status="estimate.status" />
          </BaseEstimateStatusBadge>
        </template>
        <template #amount>
          <BaseFormatMoney :amount="estimate.total" :currency="estimate.customer?.currency" />
        </template>
      </RecordListItem>
    </RecordListPane>

    <BasePage class="min-w-0">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('estimates.estimate', 2)" to="/admin/estimates" />
        </BaseBreadcrumb>

        <div class="flex flex-wrap items-center gap-1.5 mt-2">
          <BaseEstimateStatusBadge :status="estimateData.status">
            <BaseEstimateStatusLabel :status="estimateData.status" />
          </BaseEstimateStatusBadge>
        </div>

        <template v-if="!isPhone" #actions>
          <BaseButton
            v-if="estimateData.status === 'DRAFT' && canEdit"
            :disabled="isMarkAsSent"
            :content-loading="isLoadingEstimate"
            variant="white"
            @click="onMarkAsSent"
          >
            {{ $t('estimates.mark_as_sent') }}
          </BaseButton>

          <BaseButton
            v-if="estimateData.status === 'DRAFT' && canSend"
            :content-loading="isLoadingEstimate"
            variant="primary"
            @click="onSendEstimate"
          >
            <template #left="slotProps">
              <BaseIcon name="PaperAirplaneIcon" :class="slotProps.class" />
            </template>
            {{ $t('estimates.send_estimate') }}
          </BaseButton>

          <BaseButton
            v-else-if="canConvert"
            :content-loading="isLoadingEstimate"
            variant="primary"
            @click="onConvertToInvoice"
          >
            <template #left="slotProps">
              <BaseIcon name="DocumentTextIcon" :class="slotProps.class" />
            </template>
            {{ $t('estimates.convert_to_invoice') }}
          </BaseButton>

          <EstimateDropdown
            :row="estimateData"
            :can-edit="canEdit"
            :can-view="canView"
            :can-create="canCreate"
            :can-delete="canDelete"
            :can-send="canSend"
            :can-create-invoice="canCreateInvoice"
          />
        </template>
      </BasePageHeader>

      <!-- What the document says, without opening it -->
      <BaseStatStrip :columns="4">
        <BaseStat :label="$t('estimates.total')" emphasis>
          <BaseFormatMoney :amount="estimateData.total" :currency="documentCurrency" />
        </BaseStat>
        <BaseStat :label="$t('estimates.customer')" wide>
          <router-link
            v-if="estimateData.customer?.id"
            :to="`/admin/customers/${estimateData.customer.id}/view`"
            class="hover:text-primary-600"
          >
            {{ estimateData.customer.name }}
          </router-link>
        </BaseStat>
        <BaseStat :label="$t('reports.estimates.estimate_date')">
          {{ estimateData.formatted_estimate_date }}
        </BaseStat>
        <BaseStat :label="$t('estimates.expiry_date')">
          <span :class="estimateData.status === 'EXPIRED' ? 'text-status-red' : ''">
            {{ estimateData.formatted_expiry_date || '-' }}
          </span>
        </BaseStat>
      </BaseStatStrip>

      <BasePdfPreview
        ref="pdfPreview"
        :src="shareableLink"
        :title="`${estimateData.estimate_number}.pdf`"
      />

      <!-- Phones: the next step for this estimate, within thumb reach -->
      <BaseActionBar>
        <BaseButton
          v-if="estimateData.status === 'DRAFT' && canSend"
          variant="primary"
          class="flex-1"
          @click="onSendEstimate"
        >
          <template #left="slotProps">
            <BaseIcon name="PaperAirplaneIcon" :class="slotProps.class" />
          </template>
          {{ $t('estimates.send_estimate') }}
        </BaseButton>
        <BaseButton
          v-else-if="canConvert"
          variant="primary"
          class="flex-1"
          @click="onConvertToInvoice"
        >
          <template #left="slotProps">
            <BaseIcon name="DocumentTextIcon" :class="slotProps.class" />
          </template>
          {{ $t('estimates.convert_to_invoice') }}
        </BaseButton>
        <!-- A draft is not out yet; the PDF is also on the document card above -->
        <BaseButton
          v-if="estimateData.status === 'DRAFT' && canEdit"
          :disabled="isMarkAsSent"
          variant="white"
          class="flex-1"
          @click="onMarkAsSent"
        >
          {{ $t('estimates.mark_as_sent') }}
        </BaseButton>
        <BaseButton v-else variant="white" class="flex-1" @click="openPdf">
          <template #left="slotProps">
            <BaseIcon name="ArrowUpOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ $t('pdf.open_pdf') }}
        </BaseButton>
        <EstimateDropdown
          :row="estimateData"
          :can-edit="canEdit"
          :can-view="canView"
          :can-create="canCreate"
          :can-delete="canDelete"
          :can-send="canSend"
          :can-create-invoice="canCreateInvoice"
        />
      </BaseActionBar>
    </BasePage>

    <SendEstimateModal @update="updateSentEstimate" />
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useEstimateStore } from '../store'
import EstimateDropdown from '../components/EstimateDropdown.vue'
import SendEstimateModal from '../components/SendEstimateModal.vue'
import RecordListPane from '@/scripts/components/layout/RecordListPane.vue'
import RecordListItem from '@/scripts/components/layout/RecordListItem.vue'
import BasePdfPreview from '@/scripts/components/base/BasePdfPreview.vue'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useUserStore } from '../../../../stores/user.store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useModalStore } from '../../../../stores/modal.store'
import type { Estimate } from '../../../../types/domain/estimate'

interface Props {
  canEdit?: boolean
  canView?: boolean
  canCreate?: boolean
  canDelete?: boolean
  canSend?: boolean
  canCreateInvoice?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canEdit: false,
  canView: false,
  canCreate: false,
  canDelete: false,
  canSend: false,
  canCreateInvoice: false,
})

const ABILITIES = {
  EDIT: 'edit-estimate',
  VIEW: 'view-estimate',
  CREATE: 'create-estimate',
  DELETE: 'delete-estimate',
  SEND: 'send-estimate',
  CREATE_INVOICE: 'create-invoice',
} as const

const estimateStore = useEstimateStore()
const userStore = useUserStore()
const dialogStore = useDialogStore()
const modalStore = useModalStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

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

const canCreateInvoice = computed<boolean>(() => {
  return (
    props.canCreateInvoice || userStore.hasAbilities(ABILITIES.CREATE_INVOICE)
  )
})

const estimateData = ref<Estimate | null>(null)
const isMarkAsSent = ref<boolean>(false)
const isLoading = ref<boolean>(false)
const isLoadingEstimate = ref<boolean>(false)

const estimateList = ref<Estimate[] | null>(null)
const currentPageNumber = ref<number>(1)
const lastPageNumber = ref<number>(1)
const listPane = ref<InstanceType<typeof RecordListPane> | null>(null)
const estimateListSection = computed<HTMLElement | null>(() => listPane.value?.listEl ?? null)

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

const pageTitle = computed<string>(() => estimateData.value?.estimate_number ?? '')

const { isPhone } = useBreakpoints()
const pdfPreview = ref<InstanceType<typeof BasePdfPreview> | null>(null)

const documentCurrency = computed(() => estimateData.value?.currency ?? estimateData.value?.customer?.currency ?? null)

// Once it has gone out, turning it into an invoice is the next step
const canConvert = computed<boolean>(() => {
  const status = estimateData.value?.status

  return canCreateInvoice.value && !!status && status !== 'DRAFT' && status !== 'REJECTED'
})

const sortOptions = computed(() => [
  { value: 'estimate_date', label: t('reports.estimates.estimate_date') },
  { value: 'expiry_date', label: t('estimates.due_date') },
  { value: 'estimate_number', label: t('estimates.estimate_number') },
])

function setSortField(field: string): void {
  searchData.orderByField = field
  onSearched()
}

function openPdf(): void {
  pdfPreview.value?.openPdf()
}

const getOrderBy = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || searchData.orderBy === null
})

const shareableLink = computed<string>(() => {
  return `/estimates/pdf/${estimateData.value?.unique_hash ?? ''}`
})

watch(route, (to) => {
  if (to.name === 'estimates.view') {
    loadEstimate()
  }
})

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

async function loadEstimates(
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
  const response = await estimateStore.fetchEstimates({
    page: pageNumber,
    ...params,
  } as never)
  isLoading.value = false

  estimateList.value = estimateList.value ?? []
  estimateList.value = [...estimateList.value, ...response.data.data]

  currentPageNumber.value = pageNumber ?? 1
  lastPageNumber.value = response.data.meta.last_page

  const estimateFound = estimateList.value.find(
    (est) => est.id === Number(route.params.id),
  )

  if (
    !fromScrollListener &&
    !estimateFound &&
    currentPageNumber.value < lastPageNumber.value &&
    Object.keys(params).length === 0
  ) {
    loadEstimates(++currentPageNumber.value)
  }

  if (estimateFound && !fromScrollListener) {
    setTimeout(() => scrollToEstimate(), 500)
  }
}

function scrollToEstimate(): void {
  const el = document.getElementById(`estimate-${route.params.id}`)
  const list = estimateListSection.value
  if (el && list) {
    // Scroll the list pane alone; scrollIntoView would also move the page
    list.scrollTo({ top: el.offsetTop - list.offsetTop - 8, behavior: 'smooth' })
    el.classList.add('shake')
    addScrollListener()
  }
}

function addScrollListener(): void {
  estimateListSection.value?.addEventListener('scroll', (ev) => {
    const target = ev.target as HTMLElement
    if (
      target.scrollTop > 0 &&
      target.scrollTop + target.clientHeight > target.scrollHeight - 200
    ) {
      if (currentPageNumber.value < lastPageNumber.value) {
        loadEstimates(++currentPageNumber.value, true)
      }
    }
  })
}

async function loadEstimate(): Promise<void> {
  isLoadingEstimate.value = true
  const response = await estimateStore.fetchEstimate(Number(route.params.id))
  if (response.data) {
    isLoadingEstimate.value = false
    estimateData.value = { ...response.data.data } as Estimate
  }
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null

function onSearchText(value: string): void {
  searchData.searchText = value
  onSearched()
}

function onSearched(): void {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    estimateList.value = []
    loadEstimates()
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

function onMarkAsSent(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('estimates.confirm_mark_as_sent'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      isMarkAsSent.value = false
      await estimateStore.markAsSent({
        id: estimateData.value!.id,
        status: 'SENT',
      })
      estimateData.value!.status = 'SENT' as Estimate['status']
      isMarkAsSent.value = true
      isMarkAsSent.value = false
    }
  })
}

function onSendEstimate(): void {
  modalStore.openModal({
    title: t('estimates.send_estimate'),
    componentName: 'SendEstimateModal',
    id: estimateData.value!.id,
    data: estimateData.value,
    refreshData: () => loadEstimate(),
  })
}

function onConvertToInvoice(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('estimates.confirm_conversion'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await estimateStore.convertToInvoice(estimateData.value!.id)
      if (response.data) {
        router.push(`/admin/invoices/${response.data.data.id}/edit`)
      }
    }
  })
}

function updateSentEstimate(): void {
  if (!estimateList.value) return
  const pos = estimateList.value.findIndex(
    (est) => est.id === estimateData.value?.id,
  )
  if (pos !== -1 && estimateList.value[pos]) {
    estimateList.value[pos].status = 'SENT' as Estimate['status']
    estimateData.value!.status = 'SENT' as Estimate['status']
  }
}

// Initialize
loadEstimates()
loadEstimate()
</script>
