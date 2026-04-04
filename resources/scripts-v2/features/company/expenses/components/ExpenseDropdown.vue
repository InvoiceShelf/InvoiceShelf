<template>
  <BaseDropdown>
    <template #activator>
      <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <!-- Edit Expense -->
    <router-link v-if="canEdit" :to="`/admin/expenses/${row.id}/edit`">
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Delete Expense -->
    <BaseDropdownItem v-if="canDelete" @click="removeExpense">
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
import { useExpenseStore } from '../store'
import type { Expense } from '../../../../types/domain/expense'

interface TableRef {
  refresh: () => void
}

interface Props {
  row: Expense
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

const expenseStore = useExpenseStore()
const { t } = useI18n()

async function removeExpense(): Promise<void> {
  const confirmed = window.confirm(t('expenses.confirm_delete'))
  if (!confirmed) return

  const res = await expenseStore.deleteExpense({ ids: [props.row.id] })
  if (res) {
    props.loadData?.()
  }
}
</script>
