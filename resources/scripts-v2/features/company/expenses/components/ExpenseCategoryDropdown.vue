<template>
  <BaseDropdown>
    <template #activator>
      <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <!-- Edit Category -->
    <BaseDropdownItem v-if="canEdit" @click="editExpenseCategory">
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <!-- Delete Category -->
    <BaseDropdownItem v-if="canDelete" @click="removeExpenseCategory">
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ExpenseCategory } from '../../../../types/domain/expense'

interface TableRef {
  refresh: () => void
}

interface Props {
  row: ExpenseCategory
  table?: TableRef | null
  loadData?: (() => void) | null
  canEdit?: boolean
  canDelete?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  table: null,
  loadData: null,
  canEdit: false,
  canDelete: false,
})

const { t } = useI18n()

function editExpenseCategory(): void {
  const modalStore = (window as Record<string, unknown>).__modalStore as
    | { openModal: (opts: Record<string, unknown>) => void }
    | undefined
  modalStore?.openModal({
    title: t('settings.expense_category.edit_category'),
    componentName: 'CategoryModal',
    refreshData: props.loadData,
    size: 'sm',
  })
}

async function removeExpenseCategory(): Promise<void> {
  const confirmed = window.confirm(
    t('settings.expense_category.confirm_delete'),
  )
  if (!confirmed) return

  try {
    const { expenseService } = await import(
      '../../../../api/services/expense.service'
    )
    const response = await expenseService.deleteCategory(props.row.id)
    if (response.success) {
      props.loadData?.()
    }
  } catch {
    props.loadData?.()
  }
}
</script>
