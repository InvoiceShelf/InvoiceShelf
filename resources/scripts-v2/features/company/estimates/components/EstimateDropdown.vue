<template>
  <BaseDropdown>
    <template #activator>
      <BaseButton v-if="isDetailView" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="text-white" />
      </BaseButton>
      <BaseIcon v-else class="text-muted" name="EllipsisHorizontalIcon" />
    </template>

    <!-- Copy PDF url -->
    <BaseDropdownItem v-if="isDetailView" @click="copyPdfUrl">
      <BaseIcon
        name="LinkIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.copy_pdf_url') }}
    </BaseDropdownItem>

    <!-- Edit Estimate -->
    <router-link
      v-if="canEdit"
      :to="`/admin/estimates/${row.id}/edit`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Delete Estimate -->
    <BaseDropdownItem v-if="canDelete" @click="removeEstimate">
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>

    <!-- View Estimate -->
    <router-link
      v-if="!isDetailView && canView"
      :to="`estimates/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="EyeIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Clone Estimate -->
    <BaseDropdownItem v-if="canCreate" @click="cloneEstimateData">
      <BaseIcon
        name="DocumentTextIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('estimates.clone_estimate') }}
    </BaseDropdownItem>

    <!-- Convert into Invoice -->
    <BaseDropdownItem v-if="canCreateInvoice && row.status !== 'REJECTED'" @click="convertToInvoice">
      <BaseIcon
        name="DocumentTextIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('estimates.convert_to_invoice') }}
    </BaseDropdownItem>

    <!-- Mark as Sent -->
    <BaseDropdownItem
      v-if="row.status !== 'SENT' && !isDetailView && canSend"
      @click="onMarkAsSent"
    >
      <BaseIcon
        name="CheckCircleIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('estimates.mark_as_sent') }}
    </BaseDropdownItem>

    <!-- Send Estimate -->
    <BaseDropdownItem
      v-if="row.status !== 'SENT' && !isDetailView && canSend"
      @click="sendEstimate"
    >
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('estimates.send_estimate') }}
    </BaseDropdownItem>

    <!-- Resend Estimate -->
    <BaseDropdownItem v-if="canResendEstimate" @click="sendEstimate">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('estimates.resend_estimate') }}
    </BaseDropdownItem>

    <!-- Mark as Accepted -->
    <BaseDropdownItem
      v-if="row.status !== 'ACCEPTED' && row.status !== 'REJECTED' && canEdit"
      @click="onMarkAsAccepted"
    >
      <BaseIcon
        name="CheckCircleIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('estimates.mark_as_accepted') }}
    </BaseDropdownItem>

    <!-- Mark as Rejected -->
    <BaseDropdownItem
      v-if="row.status !== 'REJECTED' && row.status !== 'ACCEPTED' && canEdit"
      @click="onMarkAsRejected"
    >
      <BaseIcon
        name="XCircleIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('estimates.mark_as_rejected') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useEstimateStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useModalStore } from '../../../../stores/modal.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import type { Estimate } from '../../../../types/domain/estimate'

interface TableRef {
  refresh: () => void
}

interface Props {
  row: Estimate & Record<string, unknown>
  table?: TableRef | null
  canEdit?: boolean
  canView?: boolean
  canCreate?: boolean
  canDelete?: boolean
  canSend?: boolean
  canCreateInvoice?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  table: null,
  canEdit: false,
  canView: false,
  canCreate: false,
  canDelete: false,
  canSend: false,
  canCreateInvoice: false,
})

const estimateStore = useEstimateStore()
const dialogStore = useDialogStore()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const isDetailView = computed<boolean>(() => route.name === 'estimates.view')

const canResendEstimate = computed<boolean>(() => {
  return (
    (props.row.status === 'SENT' || props.row.status === 'VIEWED') &&
    !isDetailView.value &&
    props.canSend
  )
})

function removeEstimate(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('estimates.confirm_delete'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await estimateStore.deleteEstimate({ ids: [props.row.id] })
      if (response.data) {
        props.table?.refresh()
        if (response.data.success) {
          router.push('/admin/estimates')
        }
        estimateStore.$patch((state) => {
          state.selectedEstimates = []
          state.selectAllField = false
        })
      }
    }
  })
}

function convertToInvoice(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('estimates.confirm_conversion'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await estimateStore.convertToInvoice(props.row.id)
      if (response.data) {
        router.push(`/admin/invoices/${response.data.data.id}/edit`)
      }
    }
  })
}

function onMarkAsSent(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('estimates.confirm_mark_as_sent'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      await estimateStore.markAsSent({ id: props.row.id, status: 'SENT' })
      props.table?.refresh()
    }
  })
}

function sendEstimate(): void {
  modalStore.openModal({
    title: t('estimates.send_estimate'),
    componentName: 'SendEstimateModal',
    id: props.row.id,
    data: props.row,
    variant: 'lg',
  })
}

function onMarkAsAccepted(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('estimates.confirm_mark_as_accepted'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      await estimateStore.markAsAccepted({ id: props.row.id, status: 'ACCEPTED' })
      props.table?.refresh()
    }
  })
}

function onMarkAsRejected(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('estimates.confirm_mark_as_rejected'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      await estimateStore.markAsRejected({ id: props.row.id, status: 'REJECTED' })
      props.table?.refresh()
    }
  })
}

function copyPdfUrl(): void {
  const pdfUrl = `${window.location.origin}/estimates/pdf/${props.row.unique_hash}`
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

function cloneEstimateData(): void {
  dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('estimates.confirm_clone'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'primary',
    hideNoButton: false,
    size: 'lg',
  }).then(async (res: boolean) => {
    if (res) {
      const response = await estimateStore.cloneEstimate({ id: props.row.id })
      router.push(`/admin/estimates/${response.data.data.id}/edit`)
    }
  })
}
</script>
