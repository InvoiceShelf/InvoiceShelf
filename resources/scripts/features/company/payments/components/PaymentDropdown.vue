<template>
  <BaseDropdown :content-loading="contentLoading" :label="row.payment_number ? $t('general.actions_for', { name: String(row.payment_number) }) : ''">
    <template #activator>
      <span v-if="isDetailView" data-overflow class="inline-flex items-center justify-center border rounded-lg w-11 h-11 md:w-9 md:h-9 bg-surface border-line-default text-body hover:bg-hover">
        <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
      </span>
      <span v-else class="inline-flex items-center justify-center rounded-lg w-9 h-9 text-muted hover:bg-hover-strong hover:text-heading">
        <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
      </span>
    </template>

    <!-- Copy PDF url -->
    <BaseDropdownItem
      v-if="isDetailView && canView"
      class="rounded-md"
      @click="copyPdfUrl"
    >
      <BaseIcon
        name="LinkIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.copy_pdf_url') }}
    </BaseDropdownItem>

    <!-- Edit Payment -->
    <BaseDropdownItem v-if="canEdit" :to="`/admin/payments/${row.id}/edit`">
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <!-- View Payment -->
    <BaseDropdownItem v-if="!isDetailView && canView" :to="`/admin/payments/${row.id}/view`">
      <BaseIcon
        name="EyeIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.view') }}
    </BaseDropdownItem>

    <!-- Send Payment -->
    <BaseDropdownItem
      v-if="!isDetailView && canSend"
      @click="sendPayment"
    >
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('payments.send_payment') }}
    </BaseDropdownItem>

    <!-- Delete Payment -->
    <BaseDropdownItem v-if="canDelete" @click="removePayment">
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { usePaymentStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useModalStore } from '../../../../stores/modal.store'
import { absoluteDocumentUrl } from '@/scripts/utils/documents'
import type { Payment } from '../../../../types/domain/payment'

interface TableRef {
  refresh: () => void
}

interface Props {
  row: Payment | Record<string, unknown>
  table?: TableRef | null
  contentLoading?: boolean
  canEdit?: boolean
  canView?: boolean
  canDelete?: boolean
  canSend?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  table: null,
  contentLoading: false,
  canEdit: false,
  canView: false,
  canDelete: false,
  canSend: false,
})

const paymentStore = usePaymentStore()
const dialogStore = useDialogStore()
const modalStore = useModalStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const isDetailView = computed<boolean>(() => route.name === 'payments.view')

function removePayment(): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('payments.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res: boolean) => {
      if (res) {
        const payment = props.row as Payment
        await paymentStore.deletePayment({ ids: [payment.id] })
        router.push('/admin/payments')
        props.table?.refresh()
      }
    })
}

function copyPdfUrl(): void {
  const payment = props.row as Payment
  const pdfUrl = absoluteDocumentUrl(`/payments/pdf/${payment.unique_hash}`)

  // navigator.clipboard is [SecureContext]-gated, so on a plain-HTTP origin it is
  // undefined and `.writeText` throws on property access, before any promise
  // exists for .catch() to handle. Test up front, as the invoice and estimate
  // dropdowns already do.
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(pdfUrl)
    return
  }

  const textarea = document.createElement('textarea')
  textarea.value = pdfUrl
  textarea.style.position = 'fixed'
  textarea.style.opacity = '0'
  document.body.appendChild(textarea)
  textarea.focus()
  textarea.select()
  document.execCommand('copy')
  document.body.removeChild(textarea)
}

function sendPayment(): void {
  const payment = props.row as Payment
  modalStore.openModal({
    title: t('payments.send_payment'),
    componentName: 'SendPaymentModal',
    id: payment.id,
    data: payment,
    variant: 'lg',
  })
}
</script>
