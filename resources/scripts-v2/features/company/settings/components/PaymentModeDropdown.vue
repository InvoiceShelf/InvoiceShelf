<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDialogStore } from '@v2/stores/dialog.store'
import { useModalStore } from '@v2/stores/modal.store'
import { paymentService } from '@v2/api/services/payment.service'

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
  <BaseDropdown>
    <template #activator>
      <BaseButton v-if="route.name === 'paymentModes.view'" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <BaseDropdownItem @click="editPaymentMode(row.id)">
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <BaseDropdownItem @click="removePaymentMode(row.id)">
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>
