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
      <BaseIcon
        name="UsersIcon"
        class="mt-5 mb-4 h-16 w-16 text-gray-300"
      />
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <BaseTable
        ref="table"
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
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700"
            >
              {{ company.name }}
            </span>
            <span
              v-if="row.data.companies.length > 3"
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500"
            >
              +{{ row.data.companies.length - 3 }}
            </span>
          </div>
          <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell-actions="{ row }">
          <AdminUserDropdown
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
import AdminUserDropdown from '@/scripts/admin/components/dropdowns/AdminUserIndexDropdown.vue'

const administrationStore = useAdministrationStore()
const { t } = useI18n()

let showFilters = ref(false)
let isFetchingInitialData = ref(true)
let table = ref(null)

let filters = reactive({
  display_name: '',
  email: '',
  phone: '',
})

const userTableColumns = computed(() => {
  return [
    {
      key: 'name',
      label: t('users.name'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
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
  ]
})

const showEmptyScreen = computed(() => {
  return !administrationStore.totalUsers && !isFetchingInitialData.value
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
    display_name: filters.display_name || '',
    email: filters.email || '',
    phone: filters.phone || '',
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true

  let response = await administrationStore.fetchUsers(data)

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

function getRoleBadgeClass(role) {
  switch (role) {
    case 'super admin':
      return 'bg-purple-100 text-purple-800'
    case 'admin':
      return 'bg-blue-100 text-blue-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

function clearFilter() {
  filters.display_name = ''
  filters.email = ''
  filters.phone = ''
}

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }

  showFilters.value = !showFilters.value
}
</script>
