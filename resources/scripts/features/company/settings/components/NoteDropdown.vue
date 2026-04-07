<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { noteService } from '@/scripts/api/services/note.service'
import { ABILITIES } from '@/scripts/config/abilities'

interface NoteRow {
  id: number
  name: string
  [key: string]: unknown
}

const props = defineProps<{
  row: NoteRow
  table?: { refresh: () => void } | null
  loadData?: (() => void) | null
}>()

const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()
const userStore = useUserStore()
const modalStore = useModalStore()

function editNote(id: number): void {
  modalStore.openModal({
    title: t('settings.customization.notes.edit_note'),
    componentName: 'NoteModal',
    size: 'md',
    data: id,
    refreshData: props.loadData ?? undefined,
  })
}

function removeNote(id: number): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.customization.notes.note_confirm_delete'),
      yesLabel: t('general.yes'),
      noLabel: t('general.no'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (confirmed: boolean) => {
      if (!confirmed) {
        return
      }

      const response = await noteService.delete(id)
      if (response.success) {
        notificationStore.showNotification({
          type: 'success',
          message: t('settings.customization.notes.deleted_message'),
        })
      } else {
        notificationStore.showNotification({
          type: 'error',
          message: t('settings.customization.notes.already_in_use'),
        })
      }
      props.loadData?.()
    })
}
</script>

<template>
  <BaseDropdown>
    <template #activator>
      <BaseButton v-if="route.name === 'settings.notes'" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.MANAGE_NOTE)"
      @click="editNote(row.id)"
    >
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.MANAGE_NOTE)"
      @click="removeNote(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
