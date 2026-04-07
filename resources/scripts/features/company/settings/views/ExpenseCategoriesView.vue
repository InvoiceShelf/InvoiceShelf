<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../../stores/modal.store'
import { expenseService } from '../../../../api/services/expense.service'
import ExpenseCategoryDropdown from '@/scripts/features/company/settings/components/ExpenseCategoryDropdown.vue'
import CategoryModal from '@/scripts/features/company/settings/components/CategoryModal.vue'

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

const modalStore = useModalStore()
const { t } = useI18n()

const table = ref<{ refresh: () => void } | null>(null)

const expenseCategoryColumns = computed<TableColumn[]>(() => [
  {
    key: 'name',
    label: t('settings.expense_category.category_name'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'description',
    label: t('settings.expense_category.category_description'),
    thClass: 'extra',
    tdClass: 'font-medium text-heading',
  },
  {
    key: 'actions',
    label: '',
    tdClass: 'text-right text-sm font-medium',
    sortable: false,
  },
])

async function fetchData({ page, sort }: FetchParams): Promise<FetchResult> {
  const data = {
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  const response = await expenseService.listCategories(data)

  return {
    data: (response as Record<string, unknown>).data as unknown[],
    pagination: {
      totalPages: ((response as Record<string, unknown>).meta as Record<string, number>).last_page,
      currentPage: page,
      totalCount: ((response as Record<string, unknown>).meta as Record<string, number>).total,
      limit: 5,
    },
  }
}

function openCategoryModal(): void {
  modalStore.openModal({
    title: t('settings.expense_category.add_category'),
    componentName: 'CategoryModal',
    size: 'sm',
    refreshData: table.value?.refresh,
  })
}

function refreshTable(): void {
  table.value?.refresh()
}
</script>

<template>
  <CategoryModal />

  <BaseSettingCard
    :title="$t('settings.expense_category.title')"
    :description="$t('settings.expense_category.description')"
  >
    <template #action>
      <BaseButton
        variant="primary-outline"
        type="button"
        @click="openCategoryModal"
      >
        <template #left="slotProps">
          <BaseIcon :class="slotProps.class" name="PlusIcon" />
        </template>
        {{ $t('settings.expense_category.add_new_category') }}
      </BaseButton>
    </template>

    <BaseTable
      ref="table"
      :data="fetchData"
      :columns="expenseCategoryColumns"
      class="mt-16"
    >
      <template #cell-description="{ row }">
        <div class="w-64">
          <p class="truncate">{{ row.data.description }}</p>
        </div>
      </template>

      <template #cell-actions="{ row }">
        <ExpenseCategoryDropdown
          :row="row.data"
          :table="table"
          :load-data="refreshTable"
        />
      </template>
    </BaseTable>
  </BaseSettingCard>
</template>
