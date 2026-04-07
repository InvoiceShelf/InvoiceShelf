<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { roleService } from '@/scripts/api/services/role.service'

interface RoleRow {
  id: number
  name: string
  [key: string]: unknown
}

const props = defineProps<{
  row: RoleRow
  table?: { refresh: () => void } | null
  loadData?: (() => void) | null
}>()

const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()
const userStore = useUserStore()
const modalStore = useModalStore()

const PROTECTED_ROLES = ['owner', 'super admin']

async function editRole(id: number): Promise<void> {
  if (PROTECTED_ROLES.includes(props.row.name)) return
  modalStore.openModal({
    title: t('settings.roles.edit_role'),
    componentName: 'RolesModal',
    size: 'lg',
    data: id,
    refreshData: props.loadData ?? undefined,
  })
}

async function removeRole(id: number): Promise<void> {
  if (PROTECTED_ROLES.includes(props.row.name)) return
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.roles.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        await roleService.delete(id)
        notificationStore.showNotification({
          type: 'success',
          message: 'settings.roles.deleted_message',
        })
        props.loadData?.()
      }
    })
}
</script>

<template>
  <BaseDropdown>
    <template #activator>
      <BaseButton v-if="route.name === 'settings.roles'" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <BaseDropdownItem
      v-if="userStore.currentUser?.is_owner"
      @click="editRole(row.id)"
    >
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <BaseDropdownItem
      v-if="userStore.currentUser?.is_owner"
      @click="removeRole(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
