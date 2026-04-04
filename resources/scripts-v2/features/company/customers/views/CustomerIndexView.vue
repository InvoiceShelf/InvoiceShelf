<script setup lang="ts">
import { debouncedWatch } from '@vueuse/core'
import { reactive, ref, computed, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCustomerStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useUserStore } from '../../../../stores/user.store'
import CustomerDropdown from '../components/CustomerDropdown.vue'
import AstronautIcon from '@/scripts/components/icons/empty/AstronautIcon.vue'

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
  },
  { key: 'phone', label: t('customers.phone') },
  { key: 'due_amount', label: t('customers.amount_due') },
  {
    key: 'created_at',
    label: t('items.added_on'),
  },
  {
    key: 'actions',
    tdClass: 'text-right text-sm font-medium pl-0',
    thClass: 'pl-0',
    sortable: false,
  },
])

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
    orderBy: sort.order || 'desc',
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

    <BaseFilterWrapper :show="showFilters" class="mt-5" @clear="clearFilter">
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
      :title="$t('customers.no_customers')"
      :description="$t('customers.list_of_customers')"
    >
      <AstronautIcon class="mt-5 mb-4" />

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
      <div class="relative flex items-center justify-end h-5">
        <BaseDropdown v-if="customerStore.selectedCustomers.length">
          <template #activator>
            <span
              class="
                flex
                text-sm
                font-medium
                cursor-pointer
                select-none
                text-primary-400
              "
            >
              {{ $t('general.actions') }}
              <BaseIcon name="ChevronDownIcon" />
            </span>
          </template>
          <BaseDropdownItem @click="removeMultipleCustomers">
            <BaseIcon name="TrashIcon" class="mr-3 text-body" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <!-- Table Section -->
      <BaseTable
        ref="tableComponent"
        class="mt-3"
        :data="fetchData"
        :columns="customerColumns"
      >
        <!-- Select All Checkbox -->
        <template #header>
          <div class="absolute z-10 items-center left-6 top-2.5 select-none">
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
              class="font-medium text-primary-500 flex flex-col"
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

        <template #cell-due_amount="{ row }">
          <BaseFormatMoney
            :amount="row.data.due_amount || 0"
            :currency="row.data.currency"
          />
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
