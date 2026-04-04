<template>
  <BasePage>
    <BasePageHeader :title="$t('expenses.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('expenses.expense', 2)"
          to="#"
          active
        />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton
          v-show="expenseStore.totalExpenses"
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
          v-if="canCreate"
          class="ml-4"
          variant="primary"
          @click="$router.push('expenses/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('expenses.add_expense') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <!-- Filters -->
    <BaseFilterWrapper :show="showFilters" class="mt-5" @clear="clearFilter">
      <BaseInputGroup :label="$t('expenses.customer')">
        <BaseCustomerSelectInput
          v-model="filters.customer_id"
          :placeholder="$t('customers.type_or_click')"
          value-prop="id"
          label="name"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('expenses.category')">
        <BaseMultiselect
          v-model="filters.expense_category_id"
          value-prop="id"
          label="name"
          track-by="name"
          :filter-results="false"
          resolve-on-load
          :delay="500"
          :options="searchCategory"
          searchable
          :placeholder="$t('expenses.categories.select_a_category')"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('expenses.from_date')">
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

      <BaseInputGroup :label="$t('expenses.to_date')">
        <BaseDatePicker
          v-model="filters.to_date"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <!-- Empty State -->
    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('expenses.no_expenses')"
      :description="$t('expenses.list_of_expenses')"
    >
      <template v-if="canCreate" #actions>
        <BaseButton
          variant="primary-outline"
          @click="$router.push('/admin/expenses/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('expenses.add_new_expense') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <!-- Table -->
    <div v-show="!showEmptyScreen" class="relative table-container">
      <div class="relative flex items-center justify-end h-5">
        <BaseDropdown
          v-if="expenseStore.selectedExpenses.length && canDelete"
        >
          <template #activator>
            <span
              class="flex text-sm font-medium cursor-pointer select-none text-primary-400"
            >
              {{ $t('general.actions') }}
              <BaseIcon name="ChevronDownIcon" />
            </span>
          </template>

          <BaseDropdownItem @click="removeMultipleExpenses">
            <BaseIcon name="TrashIcon" class="h-5 mr-3 text-body" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <BaseTable
        ref="tableRef"
        :data="fetchData"
        :columns="expenseColumns"
        class="mt-3"
      >
        <template #header>
          <div class="absolute items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="selectAllFieldStatus"
              variant="primary"
              @change="expenseStore.selectAllExpenses"
            />
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="row.id"
              v-model="selectField"
              :value="row.data.id"
              variant="primary"
            />
          </div>
        </template>

        <template #cell-name="{ row }">
          <router-link
            :to="{ path: `expenses/${row.data.id}/edit` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.expense_category?.name ?? '-' }}
          </router-link>
        </template>

        <template #cell-amount="{ row }">
          <BaseFormatMoney
            :amount="row.data.amount"
            :currency="row.data.currency"
          />
        </template>

        <template #cell-expense_date="{ row }">
          {{ row.data.formatted_expense_date }}
        </template>

        <template #cell-expense_number="{ row }">
          {{ row.data.expense_number || '-' }}
        </template>

        <template #cell-user_name="{ row }">
          <BaseText
            :text="row.data.customer ? row.data.customer.name : '-'"
          />
        </template>

        <template #cell-notes="{ row }">
          <div class="notes">
            <div class="truncate note w-60">
              {{ row.data.notes ? row.data.notes : '-' }}
            </div>
          </div>
        </template>

        <template v-if="hasAtLeastOneAbility" #cell-actions="{ row }">
          <ExpenseDropdown
            :row="row.data"
            :table="tableRef"
            :load-data="refreshTable"
            :can-edit="canEdit"
            :can-delete="canDelete"
          />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, reactive, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { debouncedWatch } from '@vueuse/core'
import { useExpenseStore } from '../store'
import ExpenseDropdown from '../components/ExpenseDropdown.vue'
import type { Expense, ExpenseCategory } from '../../../../types/domain/expense'

interface Props {
  canCreate?: boolean
  canEdit?: boolean
  canDelete?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  canCreate: false,
  canEdit: false,
  canDelete: false,
})

const expenseStore = useExpenseStore()
const { t } = useI18n()

const tableRef = ref<{ refresh: () => void } | null>(null)
const showFilters = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(true)

const hasAtLeastOneAbility = computed<boolean>(() => {
  return props.canDelete || props.canEdit
})

interface ExpenseFilters {
  expense_category_id: string | number
  from_date: string
  to_date: string
  customer_id: string | number
}

const filters = reactive<ExpenseFilters>({
  expense_category_id: '',
  from_date: '',
  to_date: '',
  customer_id: '',
})

const showEmptyScreen = computed<boolean>(
  () => !expenseStore.totalExpenses && !isFetchingInitialData.value,
)

const selectField = computed<number[]>({
  get: () => expenseStore.selectedExpenses,
  set: (value: number[]) => {
    expenseStore.selectExpense(value)
  },
})

const selectAllFieldStatus = computed<boolean>({
  get: () => expenseStore.selectAllField,
  set: (value: boolean) => {
    expenseStore.setSelectAllState(value)
  },
})

interface TableColumn {
  key: string
  label?: string
  thClass?: string
  tdClass?: string
  placeholderClass?: string
  sortable?: boolean
}

const expenseColumns = computed<TableColumn[]>(() => [
  {
    key: 'status',
    thClass: 'extra w-10',
    tdClass: 'font-medium text-heading',
    placeholderClass: 'w-10',
    sortable: false,
  },
  {
    key: 'expense_date',
    label: t('expenses.date'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'expense_number',
    label: t('expenses.expense_number'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'name',
    label: t('expenses.category'),
    thClass: 'extra',
    tdClass: 'cursor-pointer font-medium text-primary-500',
  },
  { key: 'user_name', label: t('expenses.customer') },
  { key: 'notes', label: t('expenses.note') },
  { key: 'amount', label: t('expenses.amount') },
  {
    key: 'actions',
    sortable: false,
    tdClass: 'text-right text-sm font-medium',
  },
])

debouncedWatch(filters, () => setFilters(), { debounce: 500 })

onUnmounted(() => {
  if (expenseStore.selectAllField) {
    expenseStore.selectAllExpenses()
  }
})

async function searchCategory(search: string): Promise<ExpenseCategory[]> {
  const response = await expenseService_listCategories({ search })
  return response
}

/** Thin wrapper to fetch categories via expense service */
async function expenseService_listCategories(
  params: Record<string, unknown>,
): Promise<ExpenseCategory[]> {
  const { expenseService } = await import(
    '../../../../api/services/expense.service'
  )
  const response = await expenseService.listCategories(params as never)
  return response.data
}

interface FetchParams {
  page: number
  filter: Record<string, unknown>
  sort: { fieldName?: string; order?: string }
}

interface FetchResult {
  data: Expense[]
  pagination: {
    totalPages: number
    currentPage: number
    totalCount: number
    limit: number
  }
}

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const data = {
    ...filters,
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true
  const response = await expenseStore.fetchExpenses(data as never)
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

function refreshTable(): void {
  tableRef.value?.refresh()
}

function setFilters(): void {
  refreshTable()
}

function clearFilter(): void {
  filters.expense_category_id = ''
  filters.from_date = ''
  filters.to_date = ''
  filters.customer_id = ''
}

function toggleFilter(): void {
  if (showFilters.value) {
    clearFilter()
  }
  showFilters.value = !showFilters.value
}

async function removeMultipleExpenses(): Promise<void> {
  const confirmed = window.confirm(t('expenses.confirm_delete'))
  if (!confirmed) return

  const res = await expenseStore.deleteMultipleExpenses()
  if (res.data) {
    refreshTable()
  }
}
</script>
