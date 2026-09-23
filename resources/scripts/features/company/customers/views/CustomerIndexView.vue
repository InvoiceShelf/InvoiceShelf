<script setup lang="ts">
import type { ColumnDef } from '@/scripts/components/table/DataTable.vue'
import { debouncedWatch } from '@vueuse/core'
import { reactive, ref, computed, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCustomerStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useUserStore } from '../../../../stores/user.store'
import CustomerDropdown from '../components/CustomerDropdown.vue'

type TableColumn = Omit<ColumnDef, 'label'> & { label?: string }

interface FetchParams {
  page: number
  filter: Record<string, unknown>
  sort: { fieldName: string; order: string }
}

interface FetchResult {
  data: unknown[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

interface CustomerFilters {
  display_name: string
  contact_name: string
  phone: string
}

const ABILITIES = {
  CREATE_CUSTOMER: 'create-customer',
  DELETE_CUSTOMER: 'delete-customer',
  EDIT_CUSTOMER: 'edit-customer',
  VIEW_CUSTOMER: 'view-customer',
} as const

const companyStore = useCompanyStore()
const dialogStore = useDialogStore()
const customerStore = useCustomerStore()
const userStore = useUserStore()

const tableComponent = ref<{ refresh: () => void } | null>(null)
const showFilters = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(true)
const { t } = useI18n()

const filters = reactive<CustomerFilters>({
  display_name: '',
  contact_name: '',
  phone: '',
})

const showEmptyScreen = computed<boolean>(
  () => !customerStore.totalCustomers && !isFetchingInitialData.value
)

const selectField = computed<number[]>({
  get: () => customerStore.selectedCustomers,
  set: (value: number[]) => {
    customerStore.selectCustomer(value)
  },
})

const selectAllFieldStatus = computed<boolean>({
  get: () => customerStore.selectAllField,
  set: (value: boolean) => {
    customerStore.setSelectAllState(value)
  },
})

const customerColumns = computed<TableColumn[]>(() => [
  {
    key: 'status',
    thClass: 'extra w-10 pr-0',
    sortable: false,
    tdClass: 'font-medium text-heading pr-0',
  },
  {
    key: 'name',
    label: t('customers.name'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
    mobile: 'title',
  },
  { key: 'phone', label: t('customers.phone'), mobile: 'subtitle' },
  {
    key: 'account_balance',
    label: t('customers.net_account_balance'),
    sortable: false,
    align: 'end',
    mobile: 'trailing',
  },
  {
    key: 'created_at',
    label: t('items.added_on'),
  },
  {
    key: 'actions',
    tdClass: 'text-right text-sm font-medium pl-0',
    thClass: 'pl-0',
    sortable: false,
    mobile: 'actions',
  },
])

function customerLink(row: { id?: number | string }): string {
  return `/admin/customers/${row.id}/view`
}

debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 }
)

onUnmounted(() => {
  if (customerStore.selectAllField) {
    customerStore.selectAllCustomers()
  }
})

function refreshTable(): void {
  tableComponent.value?.refresh()
}

function setFilters(): void {
  refreshTable()
}

function hasAtleastOneAbility(): boolean {
  return userStore.hasAbilities([
    ABILITIES.DELETE_CUSTOMER,
    ABILITIES.EDIT_CUSTOMER,
    ABILITIES.VIEW_CUSTOMER,
  ])
}

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const data = {
    display_name: filters.display_name,
    contact_name: filters.contact_name,
    phone: filters.phone,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order === 'asc' ? 'asc' as const : 'desc' as const,
    page,
  }

  isFetchingInitialData.value = true
  const response = await customerStore.fetchCustomers(data)
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
  filters.display_name = ''
  filters.contact_name = ''
  filters.phone = ''
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}

function removeMultipleCustomers(): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('customers.confirm_delete', 2),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res: boolean) => {
      if (res) {
        customerStore.deleteMultipleCustomers().then((response) => {
          if (response) {
            refreshTable()
          }
        })
      }
    })
}
</script>

<template>
  <BasePage>
    <!-- Page Header Section -->
    <BasePageHeader :title="$t('customers.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('customers.customer', 2)"
          to="#"
          active
        />
      </BaseBreadcrumb>

      <template #actions>
        <div class="flex items-center justify-end space-x-5">
          <BaseButton
            v-show="customerStore.totalCustomers"
            variant="primary-outline"
            @click="toggleFilter"
          >
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

          <BaseButton
            v-if="userStore.hasAbilities(ABILITIES.CREATE_CUSTOMER)"
            @click="$router.push('customers/create')"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('customers.new_customer') }}
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper :show="showFilters" @clear="clearFilter">
      <BaseInputGroup :label="$t('customers.display_name')" class="text-left">
        <BaseInput
          v-model="filters.display_name"
          type="text"
          name="name"
          autocomplete="off"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('customers.contact_name')" class="text-left">
        <BaseInput
          v-model="filters.contact_name"
          type="text"
          name="address_name"
          autocomplete="off"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('customers.phone')" class="text-left">
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
      icon="UsersIcon"
      :title="$t('customers.no_customers')"
      :description="$t('customers.list_of_customers')"
    >
      <template #actions>
        <BaseButton
          v-if="userStore.hasAbilities(ABILITIES.CREATE_CUSTOMER)"
          variant="primary-outline"
          @click="$router.push('/admin/customers/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('customers.add_new_customer') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <!-- Total no of Customers in Table -->
    <div v-show="!showEmptyScreen" class="relative table-container">
      <!-- Table Section -->
      <BaseTable
        ref="tableComponent"
        :data="fetchData"
        :columns="customerColumns"
        :row-to="customerLink"
        :selected-count="
          userStore.hasAbilities(ABILITIES.DELETE_CUSTOMER)
            ? customerStore.selectedCustomers.length
            : 0
        "
      >
        <template #bulk-actions>
          <BaseButton size="xs" variant="white" @click="removeMultipleCustomers">
            <template #left="slotProps">
              <BaseIcon name="TrashIcon" :class="slotProps.class" />
            </template>
            {{ $t('general.delete') }}
          </BaseButton>
        </template>

        <!-- Select All Checkbox -->
        <template #header>
          <div class="absolute z-10 items-center left-6 top-3.5 select-none">
            <BaseCheckbox
              v-model="selectAllFieldStatus"
              variant="primary"
              @change="customerStore.selectAllCustomers"
            />
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="row.data.id"
              v-model="selectField"
              :value="row.data.id"
              variant="primary"
            />
          </div>
        </template>

        <template #cell-name="{ row }">
          <router-link :to="{ path: `customers/${row.data.id}/view` }">
            <BaseText
              :text="row.data.name"
              tag="span"
              class="font-medium text-heading hover:text-primary-600 flex flex-col"
            />
            <BaseText
              :text="row.data.contact_name ? row.data.contact_name : ''"
              tag="span"
              class="text-xs text-subtle"
            />
          </router-link>
        </template>

        <template #cell-phone="{ row }">
          <span>
            {{ row.data.phone ? row.data.phone : '-' }}
          </span>
        </template>

        <template #cell-account_balance="{ row }">
          <div>
            <BaseFormatMoney :amount="Math.abs(row.data.account_balance ?? row.data.due_amount ?? 0)" :currency="row.data.currency" />
            <span v-if="(row.data.account_balance ?? row.data.due_amount ?? 0) < 0" class="block mt-1 text-xs font-medium text-status-green">{{ $t('customers.credit') }}</span>
          </div>
        </template>

        <template #cell-created_at="{ row }">
          <span>{{ row.data.formatted_created_at }}</span>
        </template>

        <template v-if="hasAtleastOneAbility()" #cell-actions="{ row }">
          <CustomerDropdown
            :row="row.data"
            :table="tableComponent"
            :load-data="refreshTable"
          />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>
