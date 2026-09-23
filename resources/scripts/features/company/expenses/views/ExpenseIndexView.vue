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
          :aria-expanded="showFilters"
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
          class="ms-4"
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
    <BaseFilterWrapper :show="showFilters" @clear="clearFilter">
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
        class="hidden w-4 h-px mb-5 shrink-0 bg-line-strong xl:block"
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
      art="expense"
      :ghost="6"
      :title="$t('expenses.no_expenses')"
      :description="$t('expenses.empty_description')"
    >
      <template v-if="canCreate" #actions>
        <BaseButton
          variant="primary"
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
      <BaseTable
        ref="tableRef"
        :no-results-message="$t('expenses.no_matching_expenses')"
        :data="fetchData"
        :columns="expenseColumns"
        :row-to="expenseLink"
        :selected-count="canDelete ? expenseStore.selectedExpenses.length : 0"
      >
        <template #bulk-actions>
          <BaseButton size="xs" variant="white" @click="removeMultipleExpenses">
            <template #left="slotProps">
              <BaseIcon name="TrashIcon" :class="slotProps.class" />
            </template>
            {{ $t('general.delete') }}
          </BaseButton>
        </template>

        <template #header>
          <div class="absolute items-center start-6 top-3.5 select-none">
            <BaseCheckbox
              v-model="selectAllFieldStatus"
              :aria-label="$t('general.select_all')"
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
              :aria-label="$t('general.select_named', { name: row.data.expense_number || row.data.id })"
              :value="row.data.id"
              variant="primary"
            />
          </div>
        </template>

        <template #cell-name="{ row }">
          <router-link
            :to="{ path: `expenses/${row.data.id}/edit` }"
            class="font-medium text-heading hover:text-primary-600"
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
          <router-link
            v-if="row.data.customer?.id"
            :to="`/admin/customers/${row.data.customer.id}/view`"
            class="font-medium text-heading hover:text-primary-600"
          >
            {{ row.data.customer.name }}
          </router-link>
          <span v-else>-</span>
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
import type { ColumnDef } from '@/scripts/components/table/DataTable.vue'
import { ref, onMounted, computed, reactive, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { debouncedWatch } from '@vueuse/core'
import { useExpenseStore } from '../store'
import ExpenseDropdown from '../components/ExpenseDropdown.vue'
import { useUserStore } from '../../../../stores/user.store'
import { useDialogStore } from '../../../../stores/dialog.store'
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

const ABILITIES = {
  CREATE: 'create-expense',
  EDIT: 'edit-expense',
  DELETE: 'delete-expense',
} as const

const expenseStore = useExpenseStore()
const userStore = useUserStore()
const dialogStore = useDialogStore()
const { t } = useI18n()

const tableRef = ref<{ refresh: () => void } | null>(null)
const showFilters = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(true)

const canCreate = computed<boolean>(() => {
  return props.canCreate || userStore.hasAbilities(ABILITIES.CREATE)
})

const canEdit = computed<boolean>(() => {
  return props.canEdit || userStore.hasAbilities(ABILITIES.EDIT)
})

const canDelete = computed<boolean>(() => {
  return props.canDelete || userStore.hasAbilities(ABILITIES.DELETE)
})

const hasAtLeastOneAbility = computed<boolean>(() => {
  return canDelete.value || canEdit.value
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

type TableColumn = Omit<ColumnDef, 'label'> & { label?: string }

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
    mobile: 'subtitle',
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
    tdClass: 'font-medium text-heading',
    mobile: 'title',
  },
  { key: 'user_name', label: t('expenses.customer') },
  { key: 'notes', label: t('expenses.note') },
  {
    key: 'amount',
    label: t('expenses.amount'),
    align: 'end',
    mobile: 'trailing',
  },
  {
    key: 'actions',
    sortable: false,
    tdClass: 'text-end text-sm font-medium',
    mobile: 'actions',
  },
])

function expenseLink(row: { id?: number | string }): string {
  return `/admin/expenses/${row.id}/edit`
}

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

function removeMultipleExpenses(): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('expenses.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        const response = await expenseStore.deleteMultipleExpenses()
        if (response.data) {
          refreshTable()
        }
      }
    })
}
</script>
