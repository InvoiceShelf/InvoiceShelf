<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { taxTypeService } from '@/scripts/api/services/tax-type.service'

const ABILITIES = {
  EDIT_TAX_TYPE: 'edit-tax-type',
  DELETE_TAX_TYPE: 'delete-tax-type',
} as const

interface TaxTypeRow {
  id: number
  name: string
  [key: string]: unknown
}

const props = defineProps<{
  row: TaxTypeRow
  table?: { refresh: () => void } | null
  loadData?: (() => void) | null
}>()

const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()
const userStore = useUserStore()
const modalStore = useModalStore()

async function editTaxType(id: number): Promise<void> {
  modalStore.openModal({
    title: t('settings.tax_types.edit_tax'),
    componentName: 'TaxTypeModal',
    size: 'sm',
    data: id,
    refreshData: props.loadData ?? undefined,
  })
}

function removeTaxType(id: number): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.tax_types.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        const response = await taxTypeService.delete(id)
        if (response.success) {
          notificationStore.showNotification({
            type: 'success',
            message: 'settings.tax_types.deleted_message',
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
      <BaseButton v-if="route.name === 'settings.tax-types'" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.EDIT_TAX_TYPE)"
      @click="editTaxType(row.id)"
    >
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <BaseDropdownItem
      v-if="userStore.hasAbilities(ABILITIES.DELETE_TAX_TYPE)"
      @click="removeTaxType(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
