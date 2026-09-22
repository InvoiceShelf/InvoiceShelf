<template>
  <div class="flex min-h-full">
    <!--
      The customer's other estimates (wide screens only). The portal publishes
      no top inset, so the pane is sized below its fixed header here.
    -->
    <RecordListPane
      ref="listPane"
      class="!h-[calc(100dvh-5.5rem)]"
      :search="searchData.estimate_number"
      :sort-options="sortOptions"
      :sort-field="searchData.orderByField"
      :ascending="isAscending"
      :empty="!store.estimates.length"
      :empty-text="$t('estimates.no_matching_estimates')"
      @update:search="onSearchText"
      @update:sort-field="setSortField"
      @toggle-order="sortData"
    >
      <RecordListItem
        v-for="est in store.estimates"
        :id="'estimate-' + est.id"
        :key="est.id"
        :to="`/${store.companySlug}/customer/estimates/${est.id}/view`"
        :active="hasActiveUrl(est.id)"
        :title="est.estimate_number"
        :meta="est.formatted_estimate_date"
      >
        <template #badges>
          <BaseEstimateStatusBadge :status="est.status">
            <BaseEstimateStatusLabel :status="est.status" />
          </BaseEstimateStatusBadge>
        </template>
        <template #amount>
          <BaseFormatMoney :amount="est.total" :currency="est.currency" />
        </template>
      </RecordListItem>
    </RecordListPane>

    <BasePage class="min-w-0">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('estimates.estimate', 2)"
            :to="`/${store.companySlug}/customer/estimates`"
          />
        </BaseBreadcrumb>

        <div v-if="currentEstimate" class="flex flex-wrap items-center gap-1.5 mt-2">
          <BaseEstimateStatusBadge :status="currentEstimate.status">
            <BaseEstimateStatusLabel :status="currentEstimate.status" />
          </BaseEstimateStatusBadge>
        </div>

        <template v-if="currentEstimate?.status === 'DRAFT'" #actions>
          <BaseButton variant="white" @click="rejectEstimate">
            {{ $t('estimates.reject_estimate') }}
          </BaseButton>
          <BaseButton variant="primary" @click="acceptEstimate">
            {{ $t('estimates.accept_estimate') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- What the estimate says, without opening it -->
      <BaseStatStrip v-if="currentEstimate" :columns="3">
        <BaseStat :label="$t('estimates.total')" emphasis>
          <BaseFormatMoney :amount="currentEstimate.total" :currency="currentEstimate.currency" />
        </BaseStat>
        <BaseStat :label="$t('reports.estimates.estimate_date')">
          {{ currentEstimate.formatted_estimate_date }}
        </BaseStat>
        <BaseStat :label="$t('estimates.expiry_date')">
          <span :class="currentEstimate.status === 'EXPIRED' ? 'text-status-red' : ''">
            {{ currentEstimate.formatted_expiry_date || '-' }}
          </span>
        </BaseStat>
      </BaseStatStrip>

      <BasePdfPreview
        :src="shareableLink"
        :title="currentEstimate ? `${currentEstimate.estimate_number}.pdf` : ''"
      />
    </BasePage>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDebounceFn } from '@vueuse/core'
import { useCustomerPortalStore } from '../store'
import RecordListPane from '@/scripts/components/layout/RecordListPane.vue'
import RecordListItem from '@/scripts/components/layout/RecordListItem.vue'
import { useDialogStore } from '../../../stores/dialog.store'
import { EstimateStatus } from '../../../types/domain/estimate'
import type { Estimate } from '../../../types/domain/estimate'

const store = useCustomerPortalStore()
const dialogStore = useDialogStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const estimate = ref<Partial<Estimate>>({})
const listPane = ref<InstanceType<typeof RecordListPane> | null>(null)

const searchData = reactive<{
  orderBy: string
  orderByField: string
  estimate_number: string
}>({
  orderBy: '',
  orderByField: '',
  estimate_number: '',
})

const pageTitle = computed<string>(() => {
  return store.selectedViewEstimate?.estimate_number ?? ''
})

const currentEstimate = computed<Estimate | null>(() => store.selectedViewEstimate)

const sortOptions = computed(() => [
  { value: 'estimate_date', label: t('reports.estimates.estimate_date') },
  { value: 'expiry_date', label: t('estimates.due_date') },
  { value: 'estimate_number', label: t('estimates.estimate_number') },
])

const isAscending = computed<boolean>(() => {
  return searchData.orderBy === 'asc' || !searchData.orderBy
})

const shareableLink = computed<string | false>(() => {
  return estimate.value.unique_hash
    ? `/estimates/pdf/${estimate.value.unique_hash}`
    : false
})

watch(() => route.params.id, () => {
  loadEstimate()
})

onMounted(() => {
  loadEstimates()
  loadEstimate()
})

function hasActiveUrl(id: number): boolean {
  return Number(route.params.id) === id
}

async function loadEstimates(): Promise<void> {
  await store.fetchEstimates({ limit: 'all' })
  setTimeout(() => scrollToEstimate(), 500)
}

async function loadEstimate(): Promise<void> {
  const id = route.params.id
  if (!id) return
  const response = await store.fetchViewEstimate(id as string)
  if (response.data?.data) {
    estimate.value = response.data.data
  }
}

function scrollToEstimate(): void {
  const el = document.getElementById(`estimate-${route.params.id}`)
  const list = listPane.value?.listEl
  if (el && list) {
    // Scroll the list pane alone; scrollIntoView would also move the page
    list.scrollTo({ top: el.offsetTop - list.offsetTop - 8, behavior: 'smooth' })
    el.classList.add('shake')
  }
}

async function onSearch(): Promise<void> {
  const params: Record<string, string> = {}
  if (searchData.estimate_number) params.estimate_number = searchData.estimate_number
  if (searchData.orderBy) params.orderBy = searchData.orderBy
  if (searchData.orderByField) params.orderByField = searchData.orderByField
  await store.searchEstimates(params)
}

const onSearchDebounced = useDebounceFn(onSearch, 500)

function onSearchText(value: string): void {
  searchData.estimate_number = value
  onSearchDebounced()
}

function setSortField(field: string): void {
  searchData.orderByField = field
  onSearchDebounced()
}

function sortData(): void {
  searchData.orderBy = searchData.orderBy === 'asc' ? 'desc' : 'asc'
  onSearch()
}

function acceptEstimate(): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('estimates.confirm_mark_as_accepted', 1),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        await store.updateEstimateStatus(
          route.params.id as string,
          EstimateStatus.ACCEPTED,
        )
        router.push({ name: 'customer-portal.estimates' })
      }
    })
}

function rejectEstimate(): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('estimates.confirm_mark_as_rejected', 1),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        await store.updateEstimateStatus(
          route.params.id as string,
          EstimateStatus.REJECTED,
        )
        router.push({ name: 'customer-portal.estimates' })
      }
    })
}
</script>
