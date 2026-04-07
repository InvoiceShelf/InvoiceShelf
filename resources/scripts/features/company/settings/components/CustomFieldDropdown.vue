<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { customFieldService } from '@/scripts/api/services/custom-field.service'

const ABILITIES = {
  EDIT_CUSTOM_FIELDS: 'edit-custom-field',
  DELETE_CUSTOM_FIELDS: 'delete-custom-field',
} as const

interface CustomFieldRow {
  id: number
  name: string
  [key: string]: unknown
}

const props = defineProps<{
  row: CustomFieldRow
  table?: { refresh: () => void } | null
  loadData?: (() => void) | null
}>()

const dialogStore = useDialogStore()
const { t } = useI18n()
const userStore = useUserStore()
const modalStore = useModalStore()

async function editCustomField(id: number): Promise<void> {
  modalStore.openModal({
    title: t('settings.custom_fields.edit_custom_field'),
    componentName: 'CustomFieldModal',
    size: 'sm',
    data: id,
    refreshData: props.loadData ?? undefined,
  })
}

async function removeCustomField(id: number): Promise<void> {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.custom_fields.custom_field_confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        await customFieldService.delete(id)
        props.loadData?.()
      }
    })
}
</script>

<template>
  <BaseDropdown>
    <template #activator>
      <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.EDIT_CUSTOM_FIELDS)"
      @click="editCustomField(row.id)"
    >
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.DELETE_CUSTOM_FIELDS)"
      @click="removeCustomField(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
