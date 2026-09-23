<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useItemStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useUserStore } from '../../../../stores/user.store'

interface RowData {
  id: number
  [key: string]: unknown
}

interface Props {
  row: RowData | null
  table?: { refresh: () => void } | null
  loadData?: (() => void) | null
}

const ABILITIES = {
  EDIT_ITEM: 'edit-item',
  DELETE_ITEM: 'delete-item',
} as const

const props = withDefaults(defineProps<Props>(), {
  row: null,
  table: null,
  loadData: null,
})

const dialogStore = useDialogStore()
const { t } = useI18n()
const itemStore = useItemStore()
const route = useRoute()
const userStore = useUserStore()

function removeItem(id: number): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('items.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res: boolean) => {
      if (res) {
        itemStore.deleteItem({ ids: [id] }).then((response) => {
          if (response.success) {
            props.loadData?.()
          }
        })
      }
    })
}
</script>

<template>
  <BaseDropdown :label="row?.name ? $t('general.actions_for', { name: String(row?.name) }) : ''">
    <template #activator>
      <span class="inline-flex items-center justify-center rounded-lg w-9 h-9 text-muted hover:bg-hover-strong hover:text-heading">
        <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
      </span>
    </template>

    <!-- Edit Item -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.EDIT_ITEM) && row"
      :to="`/admin/items/${row.id}/edit`"
    >
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <!-- Delete Item -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.DELETE_ITEM) && row"
      @click="removeItem(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
