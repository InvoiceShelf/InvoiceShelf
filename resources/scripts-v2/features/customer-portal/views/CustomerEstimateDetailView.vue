<template>
  <BasePage class="xl:pl-96">
    <BasePageHeader :title="pageTitle">
      <template #actions>
        <div class="mr-3 text-sm">
          <BaseButton
            v-if="store.selectedViewEstimate?.status === 'DRAFT'"
            variant="primary"
            @click="acceptEstimate"
          >
            {{ $t('estimates.accept_estimate') }}
          </BaseButton>
        </div>
        <div class="mr-3 text-sm">
          <BaseButton
            v-if="store.selectedViewEstimate?.status === 'DRAFT'"
            variant="primary-outline"
            @click="rejectEstimate"
          >
            {{ $t('estimates.reject_estimate') }}
          </BaseButton>
        </div>
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
          v-model="searchData.estimate_number"
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
                    id="filter_estimate_date"
                    v-model="searchData.orderByField"
                    :label="$t('reports.estimates.estimate_date')"
                    size="sm"
                    name="filter"
                    value="estimate_date"
                    @update:model-value="onSearchDebounced"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>

            <div class="px-2">
              <BaseDropdownItem class="rounded-md pt-3 hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_due_date"
                    v-model="searchData.orderByField"
                    :label="$t('estimates.due_date')"
                    value="expiry_date"
                    size="sm"
                    name="filter"
                    @update:model-value="onSearchDebounced"
                  />
                </BaseInputGroup>
              </BaseDropdownItem>
            </div>

            <div class="px-2">
              <BaseDropdownItem class="rounded-md pt-3 hover:rounded-md">
                <BaseInputGroup class="-mt-3 font-normal">
                  <BaseRadio
                    id="filter_estimate_number"
                    v-model="searchData.orderByField"
                    :label="$t('estimates.estimate_number')"
                    value="estimate_number"
                    size="sm"
                    name="filter"
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
          v-for="(est, index) in store.estimates"
          :id="'estimate-' + est.id"
          :key="index"
          :to="`/${store.companySlug}/customer/estimates/${est.id}/view`"
          :class="[
            'flex justify-between p-4 items-center cursor-pointer hover:bg-hover-strong border-l-4 border-l-transparent',
            {
              'bg-surface-tertiary border-l-4 border-l-primary-500 border-solid':
                hasActiveUrl(est.id),
            },
          ]"
          style="border-bottom: 1px solid rgba(185, 193, 209, 0.41)"
        >
          <div class="flex-2">
            <div class="mb-1 text-md not-italic font-medium leading-5 text-muted capitalize">
              {{ est.estimate_number }}
            </div>
            <BaseEstimateStatusBadge :status="est.status">
              <BaseEstimateStatusLabel :status="est.status" />
            </BaseEstimateStatusBadge>
          </div>

          <div class="flex-1 whitespace-nowrap right">
            <BaseFormatMoney
              class="mb-2 text-xl not-italic font-semibold leading-8 text-right text-heading block"
              :amount="est.total"
              :currency="est.currency"
            />
            <div class="text-sm text-right text-muted non-italic">
              {{ est.formatted_estimate_date }}
            </div>
          </div>
        </router-link>

        <p
          v-if="!store.estimates.length"
          class="flex justify-center px-4 mt-5 text-sm text-body"
        >
          {{ $t('estimates.no_matching_estimates') }}
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
import { useI18n } from 'vue-i18n'
import { useDebounceFn } from '@vueuse/core'
import { useCustomerPortalStore } from '../store'
import { EstimateStatus } from '../../../types/domain/estimate'
import type { Estimate } from '../../../types/domain/estimate'

const store = useCustomerPortalStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const estimate = ref<Partial<Estimate>>({})

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
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
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

function sortData(): void {
  searchData.orderBy = searchData.orderBy === 'asc' ? 'desc' : 'asc'
  onSearch()
}

async function acceptEstimate(): Promise<void> {
  const confirmed = window.confirm(t('estimates.confirm_mark_as_accepted', 1))
  if (!confirmed) return
  await store.updateEstimateStatus(
    route.params.id as string,
    EstimateStatus.ACCEPTED,
  )
  router.push({ name: 'customer-portal.estimates' })
}

async function rejectEstimate(): Promise<void> {
  const confirmed = window.confirm(t('estimates.confirm_mark_as_rejected', 1))
  if (!confirmed) return
  await store.updateEstimateStatus(
    route.params.id as string,
    EstimateStatus.REJECTED,
  )
  router.push({ name: 'customer-portal.estimates' })
}
</script>
