<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDialogStore } from '@v2/stores/dialog.store'
import { useUserStore } from '@v2/stores/user.store'
import { useModalStore } from '@v2/stores/modal.store'
import { useNotificationStore } from '@v2/stores/notification.store'
import { expenseService } from '@v2/api/services/expense.service'

const ABILITIES = {
  EDIT_EXPENSE: 'edit-expense',
  DELETE_EXPENSE: 'delete-expense',
} as const

interface ExpenseCategoryRow {
  id: number
  name: string
  [key: string]: unknown
}

const props = defineProps<{
  row: ExpenseCategoryRow
  table?: { refresh: () => void } | null
  loadData?: (() => void) | null
}>()

const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()
const userStore = useUserStore()
const modalStore = useModalStore()

function editExpenseCategory(id: number): void {
  modalStore.openModal({
    title: t('settings.expense_category.edit_category'),
    componentName: 'CategoryModal',
    data: id,
    refreshData: props.loadData ?? undefined,
    size: 'sm',
  })
}

function removeExpenseCategory(id: number): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.expense_category.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        const response = await expenseService.deleteCategory(id)
        if (response.success) {
          notificationStore.showNotification({
            type: 'success',
            message: 'settings.expense_category.deleted_message',
          })
          props.loadData?.()
        }
      }
    })
}
</script>

<template>
  <BaseDropdown>
    <template #activator>
      <BaseButton
        v-if="route.name === 'settings.expense-categories'"
        variant="primary"
      >
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.EDIT_EXPENSE)"
      @click="editExpenseCategory(row.id)"
    >
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.DELETE_EXPENSE)"
      @click="removeExpenseCategory(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
