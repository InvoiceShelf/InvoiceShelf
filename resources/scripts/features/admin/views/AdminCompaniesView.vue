<template>
  <BasePage>
    <BasePageHeader :title="$t('administration.companies.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('administration.companies.title')"
          to="#"
          active
        />
      </BaseBreadcrumb>

      <template #actions>
        <div class="flex items-center justify-end space-x-5">
          <BaseButton variant="primary-outline" @click="toggleFilter">
            {{ $t('general.filter') }}
            <template #right="slotProps">
              <BaseIcon
                v-if="!showFilters"
                name="FunnelIcon"
                :class="slotProps.class"
              />
              <BaseIcon v-else name="XMarkIcon" :class="slotProps.class" />
            </template>
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper :show="showFilters" class="mt-3" @clear="clearFilter">
      <BaseInputGroup
        :label="$t('administration.companies.company_name')"
        class="flex-1 mt-2"
      >
        <BaseInput
          v-model="filters.search"
          type="text"
          name="search"
          autocomplete="off"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('administration.companies.no_companies')"
      :description="$t('administration.companies.list_description')"
    >
      <BaseIcon name="BuildingOfficeIcon" class="mt-5 mb-4 h-16 w-16 text-subtle" />
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="tableRef"
        :data="fetchData"
        :columns="companyTableColumns"
        class="mt-3"
      >
        <template #cell-name="{ row }">
          <router-link
            :to="{
              name: 'admin.companies.edit',
              params: { id: row.data.id },
            }"
            class="font-medium text-primary-500"
          >
            {{ row.data.name }}
          </router-link>
        </template>

        <template #cell-owner="{ row }">
          <span v-if="row.data.owner">{{ row.data.owner.name }}</span>
          <span v-else class="text-subtle">-</span>
        </template>

        <template #cell-owner_email="{ row }">
          <span v-if="row.data.owner">{{ row.data.owner.email }}</span>
          <span v-else class="text-subtle">-</span>
        </template>

        <template #cell-actions="{ row }">
          <AdminCompanyDropdown
            :row="row.data"
            :table="tableRef"
            :load-data="refreshTable"
          />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, ref, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAdminStore } from '../stores/admin.store'
import AdminCompanyDropdown from '../components/AdminCompanyDropdown.vue'
import type { Company } from '../../../types/domain/company'

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  sortable?: boolean
}

interface FetchParams {
  page: number
  filter: Record<string, unknown>
  sort: { fieldName: string; order: string }
}

interface TableResult {
  data: Company[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

const adminStore = useAdminStore()
const { t } = useI18n()

const showFilters = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(true)
const tableRef = ref<{ refresh: () => void } | null>(null)

const filters = reactive({
  search: '',
})

const companyTableColumns = computed<TableColumn[]>(() => [
  {
    key: 'name',
    label: t('administration.companies.company_name'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'owner',
    label: t('administration.companies.owner'),
    sortable: false,
  },
  {
    key: 'owner_email',
    label: t('general.email'),
    sortable: false,
  },
  {
    key: 'actions',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

const showEmptyScreen = computed<boolean>(() => {
  return !adminStore.totalCompanies && !isFetchingInitialData.value
})

watch(
  filters,
  () => {
    refreshTable()
  },
  { deep: true }
)

function refreshTable(): void {
  tableRef.value?.refresh()
}

async function fetchData({ page, sort }: FetchParams): Promise<TableResult> {
  const params = {
    search: filters.search || '',
    orderByField: sort.fieldName || 'name',
    orderBy: sort.order || 'asc',
    page,
  }

  isFetchingInitialData.value = true

  const response = await adminStore.fetchCompanies(params)

  isFetchingInitialData.value = false

  return {
    data: response.data,
    pagination: {
      totalPages: response.meta.last_page,
      currentPage: page,
      totalCount: response.meta.total,
      limit: 10,
    },
  }
}

function clearFilter(): void {
  filters.search = ''
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}
</script>
