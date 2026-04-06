<template>
  <BaseDropdown>
    <template #activator>
      <BaseButton v-if="isDetailView" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <!-- Edit Invoice -->
    <router-link
      v-if="canEdit"
      :to="`/admin/invoices/${row.id}/edit`"
    >
      <BaseDropdownItem v-show="row.allow_edit">
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Copy PDF url -->
    <BaseDropdownItem v-if="isDetailView" @click="copyPdfUrl">
      <BaseIcon
        name="LinkIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.copy_pdf_url') }}
    </BaseDropdownItem>

    <!-- View Invoice -->
    <router-link
      v-if="!isDetailView && canView"
      :to="`/admin/invoices/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="EyeIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Send Invoice Mail -->
    <BaseDropdownItem v-if="canSendInvoice" @click="sendInvoice">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('invoices.send_invoice') }}
    </BaseDropdownItem>

    <!-- Resend Invoice -->
    <BaseDropdownItem v-if="canReSendInvoice && !isDetailView" @click="sendInvoice">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('invoices.resend_invoice') }}
    </BaseDropdownItem>

    <!-- Record Payment -->
    <router-link :to="`/admin/payments/${row.id}/create`">
      <BaseDropdownItem
        v-if="row.status === 'SENT' && !isDetailView && canCreatePayment"
      >
        <BaseIcon
          name="CreditCardIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('invoices.record_payment') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Mark as Sent -->
    <BaseDropdownItem v-if="row.status === 'DRAFT' && !isDetailView && canSend" @click="onMarkAsSent">
      <BaseIcon
        name="CheckCircleIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('invoices.mark_as_sent') }}
    </BaseDropdownItem>

    <!-- Clone Invoice -->
    <BaseDropdownItem v-if="canCreate" @click="cloneInvoiceData">
      <BaseIcon
        name="DocumentTextIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('invoices.clone_invoice') }}
    </BaseDropdownItem>

    <!-- Convert to Estimate -->
    <BaseDropdownItem v-if="canCreateEstimate" @click="convertToEstimate">
      <BaseIcon
        name="DocumentIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('invoices.convert_to_estimate') }}
    </BaseDropdownItem>

    <!-- Delete Invoice -->
    <BaseDropdownItem v-if="canDelete" @click="removeInvoice">
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useInvoiceStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useModalStore } from '../../../../stores/modal.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import type { Invoice } from '../../../../types/domain/invoice'

interface TableRef {
  refresh: () => void
}

interface Props {
  row: Invoice & Record<string, unknown>
  table?: TableRef | null
  loadData?: () => void
  canEdit?: boolean
  canView?: boolean
  canCreate?: boolean
  canDelete?: boolean
  canSend?: boolean
  canCreatePayment?: boolean
  canCreateEstimate?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  table: null,
  loadData: () => {},
  canEdit: false,
  canView: false,
  canCreate: false,
  canDelete: false,
  canSend: false,
  canCreatePayment: false,
  canCreateEstimate: false,
})

const invoiceStore = useInvoiceStore()
const dialogStore = useDialogStore()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const isDetailView = computed<boolean>(() => route.name === 'invoices.view')

const canReSendInvoice = computed<boolean>(() => {
  return (
    (props.row.status === 'SENT' || props.row.status === 'VIEWED') &&
    props.canSend
  )
})

const canSendInvoice = computed<boolean>(() => {
  return (
    props.row.status === 'DRAFT' &&
    !isDetailView.value &&
    props.canSend
  )
})

function removeInvoice(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('invoices.confirm_delete'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await invoiceStore.deleteInvoice({ ids: [props.row.id] })
      if (response.data.success) {
        router.push('/admin/invoices')
        props.table?.refresh()
        invoiceStore.$patch((state) => {
          state.selectedInvoices = []
          state.selectAllField = false
        })
      }
    }
  })
}

function cloneInvoiceData(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('invoices.confirm_clone'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await invoiceStore.cloneInvoice({ id: props.row.id })
      router.push(`/admin/invoices/${response.data.data.id}/edit`)
    }
  })
}

function convertToEstimate(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('invoices.confirm_convert_to_estimate'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await invoiceStore.convertToEstimate({ id: props.row.id })
      router.push(`/admin/estimates/${response.data.data.id}/edit`)
    }
  })
}

function onMarkAsSent(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('invoices.invoice_mark_as_sent'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      await invoiceStore.markAsSent({ id: props.row.id, status: 'SENT' })
      props.table?.refresh()
    }
  })
}

function sendInvoice(): void {
  modalStore.openModal({
    title: t('invoices.send_invoice'),
    componentName: 'SendInvoiceModal',
    id: props.row.id,
    data: props.row,
    variant: 'sm',
  })
}

function copyPdfUrl(): void {
  const pdfUrl = `${window.location.origin}/invoices/pdf/${props.row.unique_hash}`
  copyToClipboard(pdfUrl)
  notificationStore.showNotification({
    type: 'success',
    message: t('general.copied_pdf_url_clipboard'),
  })
}

function copyToClipboard(text: string): void {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text)
    return
  }
  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.style.position = 'fixed'
  textarea.style.opacity = '0'
  document.body.appendChild(textarea)
  textarea.focus()
  textarea.select()
  document.execCommand('copy')
  document.body.removeChild(textarea)
}
</script>
