<template>
  <BasePage v-if="estimateData" class="xl:pl-96 xl:ml-8">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <div class="mr-3 text-sm">
          <BaseButton
            v-if="estimateData.status === 'DRAFT' && canEdit"
            :disabled="isMarkAsSent"
            :content-loading="isLoadingEstimate"
            variant="primary-outline"
            @click="onMarkAsSent"
          >
            {{ $t('estimates.mark_as_sent') }}
          </BaseButton>
        </div>

        <BaseButton
          v-if="estimateData.status === 'DRAFT' && canSend"
          :content-loading="isLoadingEstimate"
          variant="primary"
          class="text-sm"
          @click="onSendEstimate"
        >
          {{ $t('estimates.send_estimate') }}
        </BaseButton>

        <EstimateDropdown
          class="ml-3"
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

    <!-- Sidebar -->
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
              <BaseIcon name="MagnifyingGlassIcon" class="text-subtle" />
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
              class="px-4 py-1 pb-2 mb-1 mb-2 text-sm border-b border-line-default border-solid"
            >
              {{ $t('general.sort_by') }}
            </div>

            <BaseDropdownItem class="flex px-4 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_estimate_date"
                  v-model="searchData.orderByField"
                  :label="$t('reports.estimates.estimate_date')"
                  size="sm"
                  name="filter"
                  value="estimate_date"
                  @update:model-value="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-4 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_due_date"
                  v-model="searchData.orderByField"
                  :label="$t('estimates.due_date')"
                  value="expiry_date"
                  size="sm"
                  name="filter"
                  @update:model-value="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>

            <BaseDropdownItem class="flex px-4 py-2 cursor-pointer">
              <BaseInputGroup class="-mt-3 font-normal">
                <BaseRadio
                  id="filter_estimate_number"
                  v-model="searchData.orderByField"
                  :label="$t('estimates.estimate_number')"
                  value="estimate_number"
                  size="sm"
                  name="filter"
                  @update:model-value="onSearched"
                />
              </BaseInputGroup>
            </BaseDropdownItem>
          </BaseDropdown>

          <BaseButton class="ml-1" size="md" variant="gray" @click="sortData">
            <BaseIcon v-if="getOrderBy" name="BarsArrowUpIcon" />
            <BaseIcon v-else name="BarsArrowDownIcon" />
          </BaseButton>
        </div>
      </div>

      <div
        ref="estimateListSection"
        class="h-full overflow-y-scroll border-l border-line-default border-solid base-scroll"
      >
        <div v-for="(estimate, index) in estimateList" :key="index">
          <router-link
            v-if="estimate"
            :id="'estimate-' + estimate.id"
            :to="`/admin/estimates/${estimate.id}/view`"
            :class="[
              'flex justify-between side-estimate p-4 cursor-pointer hover:bg-hover-strong items-center border-l-4 border-l-transparent',
              {
                'bg-surface-tertiary border-l-4 border-l-primary-500 border-solid':
                  hasActiveUrl(estimate.id),
              },
            ]"
            style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
          >
            <div class="flex-2">
              <BaseText
                :text="estimate.customer?.name ?? ''"
                class="pr-2 mb-2 text-sm not-italic font-normal leading-5 text-heading capitalize truncate"
              />
              <div
                class="mt-1 mb-2 text-xs not-italic font-medium leading-5 text-body"
              >
                {{ estimate.estimate_number }}
              </div>
              <BaseEstimateStatusBadge
                :status="estimate.status"
                class="px-1 text-xs"
              >
                <BaseEstimateStatusLabel :status="estimate.status" />
              </BaseEstimateStatusBadge>
            </div>

            <div class="flex-1 whitespace-nowrap right">
              <BaseFormatMoney
                :amount="estimate.total"
                :currency="estimate.customer?.currency"
                class="block mb-2 text-xl not-italic font-semibold leading-8 text-right text-heading"
              />
              <div
                class="text-sm not-italic font-normal leading-5 text-right text-body est-date"
              >
                {{ estimate.formatted_estimate_date }}
              </div>
            </div>
          </router-link>
        </div>

        <div v-if="isLoading" class="flex justify-center p-4 items-center">
          <LoadingIcon class="h-6 m-1 animate-spin text-primary-400" />
        </div>
        <p
          v-if="!estimateList?.length && !isLoading"
          class="flex justify-center px-4 mt-5 text-sm text-body"
        >
          {{ $t('estimates.no_matching_estimates') }}
        </p>
      </div>
    </div>

    <!-- PDF Preview -->
    <BasePdfPreview :src="shareableLink" />

    <SendEstimateModal @update="updateSentEstimate" />
  </BasePage>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useEstimateStore } from '../store'
import EstimateDropdown from '../components/EstimateDropdown.vue'
import SendEstimateModal from '../components/SendEstimateModal.vue'
import LoadingIcon from '@v2/components/icons/LoadingIcon.vue'
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
const estimateListSection = ref<HTMLElement | null>(null)

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
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
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
