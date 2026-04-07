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
  <BaseDropdown>
    <template #activator>
      <BaseButton v-if="route.name === 'items.view'" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <!-- Edit Item -->
    <router-link
      v-if="userStore.hasAbilities(ABILITIES.EDIT_ITEM) && row"
      :to="`/admin/items/${row.id}/edit`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Delete Item -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.DELETE_ITEM) && row"
      @click="removeItem(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
