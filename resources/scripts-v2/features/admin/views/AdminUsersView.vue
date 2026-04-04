<template>
  <BasePage>
    <BasePageHeader :title="$t('administration.users.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('administration.users.title')"
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
        :label="$t('users.name')"
        class="flex-1 mt-2 mr-4"
      >
        <BaseInput
          v-model="filters.display_name"
          type="text"
          name="name"
          autocomplete="off"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('general.email')" class="flex-1 mt-2 mr-4">
        <BaseInput
          v-model="filters.email"
          type="text"
          name="email"
          autocomplete="off"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('users.phone')" class="flex-1 mt-2">
        <BaseInput
          v-model="filters.phone"
          type="text"
          name="phone"
          autocomplete="off"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('administration.users.no_users')"
      :description="$t('administration.users.list_description')"
    >
      <BaseIcon name="UsersIcon" class="mt-5 mb-4 h-16 w-16 text-subtle" />
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="tableRef"
        :data="fetchData"
        :columns="userTableColumns"
        class="mt-3"
      >
        <template #cell-name="{ row }">
          <router-link
            :to="{
              name: 'admin.users.edit',
              params: { id: row.data.id },
            }"
            class="font-medium text-primary-500"
          >
            {{ row.data.name }}
          </router-link>
        </template>

        <template #cell-email="{ row }">
          <span>{{ row.data.email }}</span>
        </template>

        <template #cell-role="{ row }">
          <span
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
            :class="getRoleBadgeClass(row.data.role)"
          >
            {{ row.data.role }}
          </span>
        </template>

        <template #cell-companies="{ row }">
          <div v-if="row.data.companies && row.data.companies.length" class="flex flex-wrap gap-1">
            <span
              v-for="company in row.data.companies.slice(0, 3)"
              :key="company.id"
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-tertiary text-body"
            >
              {{ company.name }}
            </span>
            <span
              v-if="row.data.companies.length > 3"
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-tertiary text-muted"
            >
              +{{ row.data.companies.length - 3 }}
            </span>
          </div>
          <span v-else class="text-subtle">-</span>
        </template>

        <template #cell-actions="{ row }">
          <AdminUserDropdown
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
import AdminUserDropdown from '../components/AdminUserDropdown.vue'
import type { User } from '../../../types/domain/user'

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
  data: User[]
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
  display_name: '',
  email: '',
  phone: '',
})

const userTableColumns = computed<TableColumn[]>(() => [
  {
    key: 'name',
    label: t('users.name'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'email',
    label: t('general.email'),
  },
  {
    key: 'role',
    label: t('administration.users.role'),
    sortable: false,
  },
  {
    key: 'companies',
    label: t('navigation.companies'),
    sortable: false,
  },
  {
    key: 'actions',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

const showEmptyScreen = computed<boolean>(() => {
  return !adminStore.totalUsers && !isFetchingInitialData.value
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
    display_name: filters.display_name || '',
    email: filters.email || '',
    phone: filters.phone || '',
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true

  const response = await adminStore.fetchUsers(params)

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

function getRoleBadgeClass(role: string | null): string {
  switch (role) {
    case 'super admin':
      return 'bg-purple-100 text-purple-800'
    case 'admin':
      return 'bg-blue-100 text-blue-800'
    default:
      return 'bg-surface-tertiary text-heading'
  }
}

function clearFilter(): void {
  filters.display_name = ''
  filters.email = ''
  filters.phone = ''
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}
</script>
