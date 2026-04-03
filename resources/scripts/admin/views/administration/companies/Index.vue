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
      <BaseIcon name="BuildingOfficeIcon" class="mt-5 mb-4 h-16 w-16 text-gray-300" />
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="table"
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
          <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell-owner_email="{ row }">
          <span v-if="row.data.owner">{{ row.data.owner.email }}</span>
          <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell-actions="{ row }">
          <AdminCompanyDropdown
            :row="row.data"
            :table="table"
            :load-data="refreshTable"
          />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
import { computed, ref, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAdministrationStore } from '@/scripts/admin/stores/administration'
import AdminCompanyDropdown from '@/scripts/admin/components/dropdowns/AdminCompanyIndexDropdown.vue'

const administrationStore = useAdministrationStore()
const { t } = useI18n()

let showFilters = ref(false)
let isFetchingInitialData = ref(true)
let table = ref(null)

let filters = reactive({
  search: '',
})

const companyTableColumns = computed(() => {
  return [
    {
      key: 'name',
      label: t('administration.companies.company_name'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
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
  ]
})

const showEmptyScreen = computed(() => {
  return !administrationStore.totalCompanies && !isFetchingInitialData.value
})

watch(
  filters,
  () => {
    refreshTable()
  },
  { deep: true }
)

function refreshTable() {
  table.value && table.value.refresh()
}

async function fetchData({ page, filter, sort }) {
  let data = {
    search: filters.search || '',
    orderByField: sort.fieldName || 'name',
    orderBy: sort.order || 'asc',
    page,
  }

  isFetchingInitialData.value = true

  let response = await administrationStore.fetchCompanies(data)

  isFetchingInitialData.value = false

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

function clearFilter() {
  filters.search = ''
}

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }

  showFilters.value = !showFilters.value
}
</script>
