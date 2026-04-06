<template>
  <BasePage>
    <BasePageHeader :title="$t('estimates.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('estimates.estimate', 2)" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-show="estimateStore.totalEstimateCount"
          variant="primary-outline"
          @click="toggleFilter"
        >
          {{ $t('general.filter') }}
          <template #right="slotProps">
            <BaseIcon
              v-if="!showFilters"
              :class="slotProps.class"
              name="FunnelIcon"
            />
            <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
          </template>
        </BaseButton>

        <router-link v-if="canCreate" to="estimates/create">
          <BaseButton variant="primary" class="ml-4">
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('estimates.new_estimate') }}
          </BaseButton>
        </router-link>
      </template>
    </BasePageHeader>

    <!-- Filters -->
    <BaseFilterWrapper
      v-show="showFilters"
      :row-on-xl="true"
      @clear="clearFilter"
    >
      <BaseInputGroup :label="$t('customers.customer', 1)">
        <BaseCustomerSelectInput
          v-model="filters.customer_id"
          :placeholder="$t('customers.type_or_click')"
          value-prop="id"
          label="name"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('estimates.status')">
        <BaseMultiselect
          v-model="filters.status"
          :options="statusOptions"
          searchable
          :placeholder="$t('general.select_a_status')"
          @update:model-value="setActiveTab"
          @remove="clearStatusSearch()"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('general.from')">
        <BaseDatePicker
          v-model="filters.from_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <div
        class="hidden w-8 h-0 mx-4 border border-gray-400 border-solid xl:block"
        style="margin-top: 1.5rem"
      />

      <BaseInputGroup :label="$t('general.to')">
        <BaseDatePicker
          v-model="filters.to_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('estimates.estimate_number')">
        <BaseInput v-model="filters.estimate_number">
          <template #left="slotProps">
            <BaseIcon name="HashtagIcon" :class="slotProps.class" />
          </template>
        </BaseInput>
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Empty State -->
    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('estimates.no_estimates')"
      :description="$t('estimates.list_of_estimates')"
    >
      <template #actions>
        <BaseButton
          v-if="canCreate"
          variant="primary-outline"
          @click="$router.push('/admin/estimates/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('estimates.add_new_estimate') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <!-- Table -->
    <div v-show="!showEmptyScreen" class="relative table-container">
      <div
        class="relative flex items-center justify-between mt-5 list-none"
      >
        <BaseTabGroup @change="setStatusFilter">
          <BaseTab :title="$t('general.all')" filter="" />
          <BaseTab :title="$t('general.draft')" filter="DRAFT" />
          <BaseTab :title="$t('general.sent')" filter="SENT" />
        </BaseTabGroup>

        <BaseDropdown
          v-if="estimateStore.selectedEstimates.length && canDelete"
          class="absolute float-right"
        >
          <template #activator>
            <span
              class="flex text-sm font-medium cursor-pointer select-none text-primary-400"
            >
              {{ $t('general.actions') }}
              <BaseIcon name="ChevronDownIcon" />
            </span>
          </template>

          <BaseDropdownItem @click="removeMultipleEstimates">
            <BaseIcon name="TrashIcon" class="mr-3 text-body" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <BaseTable
        ref="tableRef"
        :data="fetchData"
        :columns="estimateColumns"
        :placeholder-count="estimateStore.totalEstimateCount >= 20 ? 10 : 5"
        :key="tableKey"
        class="mt-4"
      >
        <template #header>
          <div class="absolute items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="estimateStore.selectAllField"
              variant="primary"
              @change="estimateStore.selectAllEstimates"
            />
          </div>
        </template>

        <template #cell-checkbox="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="row.id"
              v-model="selectField"
              :value="row.data.id"
            />
          </div>
        </template>

        <template #cell-estimate_date="{ row }">
          {{ row.data.formatted_estimate_date }}
        </template>

        <template #cell-estimate_number="{ row }">
          <router-link
            :to="{ path: `estimates/${row.data.id}/view` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.estimate_number }}
          </router-link>
        </template>

        <template #cell-name="{ row }">
          <router-link
            v-if="row.data.customer?.id"
            :to="`/admin/customers/${row.data.customer.id}/view`"
            class="font-medium text-primary-500 hover:text-primary-600"
          >
            {{ row.data.customer.name }}
          </router-link>
          <span v-else>{{ row.data.customer?.name ?? '-' }}</span>
        </template>

        <template #cell-status="{ row }">
          <BaseEstimateStatusBadge :status="row.data.status" class="px-3 py-1">
            <BaseEstimateStatusLabel :status="row.data.status" />
          </BaseEstimateStatusBadge>
        </template>

        <template #cell-total="{ row }">
          <BaseFormatMoney
            :amount="row.data.total"
            :currency="row.data.customer.currency"
          />
        </template>

        <template v-if="hasAtLeastOneAbility" #cell-actions="{ row }">
          <EstimateDropdown
            :row="row.data"
            :table="tableRef"
            :can-edit="canEdit"
            :can-view="canView"
            :can-create="canCreate"
            :can-delete="canDelete"
            :can-send="canSend"
          />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, onUnmounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { debouncedWatch } from '@vueuse/core'
import { useEstimateStore } from '../store'
import EstimateDropdown from '../components/EstimateDropdown.vue'
import { useUserStore } from '../../../../stores/user.store'
import { useDialogStore } from '../../../../stores/dialog.store'
import type { Estimate } from '../../../../types/domain/estimate'

interface Props {
  canCreate?: boolean
  canEdit?: boolean
  canView?: boolean
  canDelete?: boolean
  canSend?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canCreate: false,
  canEdit: false,
  canView: false,
  canDelete: false,
  canSend: false,
})

const ABILITIES = {
  CREATE: 'create-estimate',
  EDIT: 'edit-estimate',
  VIEW: 'view-estimate',
  DELETE: 'delete-estimate',
  SEND: 'send-estimate',
} as const

const estimateStore = useEstimateStore()
const userStore = useUserStore()
const dialogStore = useDialogStore()
const { t } = useI18n()

const tableRef = ref<{ refresh: () => void } | null>(null)
const tableKey = ref<number>(0)
const showFilters = ref<boolean>(false)
const isRequestOngoing = ref<boolean>(true)
const activeTab = ref<string>('general.draft')

interface StatusOption {
  label: string
  value: string
}

const statusOptions = ref<StatusOption[]>([
  { label: t('estimates.draft'), value: 'DRAFT' },
  { label: t('estimates.sent'), value: 'SENT' },
  { label: t('estimates.viewed'), value: 'VIEWED' },
  { label: t('estimates.expired'), value: 'EXPIRED' },
  { label: t('estimates.accepted'), value: 'ACCEPTED' },
  { label: t('estimates.rejected'), value: 'REJECTED' },
])

interface EstimateFilters {
  customer_id: string | number
  status: string
  from_date: string
  to_date: string
  estimate_number: string
}

const filters = reactive<EstimateFilters>({
  customer_id: '',
  status: '',
  from_date: '',
  to_date: '',
  estimate_number: '',
})

const showEmptyScreen = computed<boolean>(
  () => !estimateStore.totalEstimateCount && !isRequestOngoing.value,
)

const selectField = computed<number[]>({
  get: () => estimateStore.selectedEstimates,
  set: (val: number[]) => {
    estimateStore.selectEstimate(val)
  },
})

const canCreate = computed<boolean>(() => {
  return props.canCreate || userStore.hasAbilities(ABILITIES.CREATE)
})

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

const hasAtLeastOneAbility = computed<boolean>(() => {
  return canCreate.value || canEdit.value || canView.value || canSend.value
})

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  sortable?: boolean
}

const estimateColumns = computed<TableColumn[]>(() => [
  {
    key: 'checkbox',
    thClass: 'extra w-10 pr-0',
    sortable: false,
    tdClass: 'font-medium text-heading pr-0',
  },
  {
    key: 'estimate_date',
    label: t('estimates.date'),
    thClass: 'extra',
    tdClass: 'font-medium text-muted',
  },
  { key: 'estimate_number', label: t('estimates.number', 2) },
  { key: 'name', label: t('estimates.customer') },
  { key: 'status', label: t('estimates.status') },
  {
    key: 'total',
    label: t('estimates.total'),
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'actions',
    tdClass: 'text-right text-sm font-medium pl-0',
    thClass: 'text-right pl-0',
    sortable: false,
  },
])

debouncedWatch(filters, () => setFilters(), { debounce: 500 })

onUnmounted(() => {
  if (estimateStore.selectAllField) {
    estimateStore.selectAllEstimates()
  }
})

function clearStatusSearch(): void {
  filters.status = ''
  refreshTable()
}

function refreshTable(): void {
  tableRef.value?.refresh()
}

interface FetchParams {
  page: number
  filter: Record<string, unknown>
  sort: { fieldName?: string; order?: string }
}

interface FetchResult {
  data: Estimate[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const data = {
    customer_id: filters.customer_id ? Number(filters.customer_id) : undefined,
    status: filters.status || undefined,
    from_date: filters.from_date || undefined,
    to_date: filters.to_date || undefined,
    estimate_number: filters.estimate_number || undefined,
    orderByField: sort.fieldName || 'created_at',
    orderBy: (sort.order || 'desc') as 'asc' | 'desc',
    page,
  }

  isRequestOngoing.value = true
  const response = await estimateStore.fetchEstimates(data)
  isRequestOngoing.value = false

  return {
    data: response.data.data,
    pagination: {
      totalPages: response.data.meta.last_page,
      currentPage: page,
      totalCount: response.data.meta.total,
      limit: 10,
    },
  }
}

function setStatusFilter(val: { title: string }): void {
  if (activeTab.value === val.title) return
  activeTab.value = val.title

  switch (val.title) {
    case t('general.draft'):
      filters.status = 'DRAFT'
      break
    case t('general.sent'):
      filters.status = 'SENT'
      break
    default:
      filters.status = ''
      break
  }
}

function setFilters(): void {
  estimateStore.$patch((state) => {
    state.selectedEstimates = []
    state.selectAllField = false
  })
  tableKey.value += 1
  refreshTable()
}

function clearFilter(): void {
  filters.customer_id = ''
  filters.status = ''
  filters.from_date = ''
  filters.to_date = ''
  filters.estimate_number = ''
  activeTab.value = t('general.all')
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}

function removeMultipleEstimates(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('estimates.confirm_delete'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await estimateStore.deleteMultipleEstimates()
      if (response.data) {
        refreshTable()
        estimateStore.$patch((state) => {
          state.selectedEstimates = []
          state.selectAllField = false
        })
      }
    }
  })
}

function setActiveTab(val: string): void {
  const tabMap: Record<string, string> = {
    DRAFT: t('general.draft'),
    SENT: t('general.sent'),
    VIEWED: t('estimates.viewed'),
    EXPIRED: t('estimates.expired'),
    ACCEPTED: t('estimates.accepted'),
    REJECTED: t('estimates.rejected'),
  }
  activeTab.value = tabMap[val] ?? t('general.all')
}
</script>
