<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { paymentService } from '@/scripts/api/services/payment.service'

interface PaymentModeRow {
  id: number
  name: string
  [key: string]: unknown
}

const props = defineProps<{
  row: PaymentModeRow
  table?: { refresh: () => void } | null
  loadData?: (() => void) | null
}>()

const dialogStore = useDialogStore()
const { t } = useI18n()
const route = useRoute()
const modalStore = useModalStore()

function editPaymentMode(id: number): void {
  modalStore.openModal({
    title: t('settings.payment_modes.edit_payment_mode'),
    componentName: 'PaymentModeModal',
    data: id,
    refreshData: props.loadData ?? undefined,
    size: 'sm',
  })
}

function removePaymentMode(id: number): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.payment_modes.payment_mode_confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        await paymentService.deleteMethod(id)
        props.loadData?.()
      }
    })
}
</script>

<template>
  <BaseDropdown :label="$t('general.actions_for', { name: row.name })">
    <template #activator>
      <span class="inline-flex items-center justify-center rounded-lg w-9 h-9 text-muted hover:bg-hover-strong hover:text-heading">
        <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
      </span>
    </template>

    <BaseDropdownItem @click="editPaymentMode(row.id)">
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <BaseDropdownItem @click="removePaymentMode(row.id)">
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
