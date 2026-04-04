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
    <BaseDropdownItem v-if="canCreateInvoice" @click="convertToInvoice">
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
      v-if="row.status !== 'ACCEPTED' && canEdit"
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
      v-if="row.status !== 'REJECTED' && canEdit"
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

async function removeEstimate(): Promise<void> {
  const confirmed = window.confirm(t('estimates.confirm_delete'))
  if (!confirmed) return

  const res = await estimateStore.deleteEstimate({ ids: [props.row.id] })
  if (res.data) {
    props.table?.refresh()
    if (res.data.success) {
      router.push('/admin/estimates')
    }
    estimateStore.$patch((state) => {
      state.selectedEstimates = []
      state.selectAllField = false
    })
  }
}

async function convertToInvoice(): Promise<void> {
  const confirmed = window.confirm(t('estimates.confirm_conversion'))
  if (!confirmed) return

  const res = await estimateStore.convertToInvoice(props.row.id)
  if (res.data) {
    router.push(`/admin/invoices/${res.data.data.id}/edit`)
  }
}

async function onMarkAsSent(): Promise<void> {
  const confirmed = window.confirm(t('estimates.confirm_mark_as_sent'))
  if (!confirmed) return

  await estimateStore.markAsSent({ id: props.row.id, status: 'SENT' })
  props.table?.refresh()
}

function sendEstimate(): void {
  const modalStore = (window as Record<string, unknown>).__modalStore as
    | { openModal: (opts: Record<string, unknown>) => void }
    | undefined
  modalStore?.openModal({
    title: t('estimates.send_estimate'),
    componentName: 'SendEstimateModal',
    id: props.row.id,
    data: props.row,
    variant: 'lg',
  })
}

async function onMarkAsAccepted(): Promise<void> {
  const confirmed = window.confirm(t('estimates.confirm_mark_as_accepted'))
  if (!confirmed) return

  await estimateStore.markAsAccepted({ id: props.row.id, status: 'ACCEPTED' })
  props.table?.refresh()
}

async function onMarkAsRejected(): Promise<void> {
  const confirmed = window.confirm(t('estimates.confirm_mark_as_rejected'))
  if (!confirmed) return

  await estimateStore.markAsRejected({ id: props.row.id, status: 'REJECTED' })
  props.table?.refresh()
}

function copyPdfUrl(): void {
  const pdfUrl = `${window.location.origin}/estimates/pdf/${props.row.unique_hash}`
  navigator.clipboard.writeText(pdfUrl).catch(() => {
    const textarea = document.createElement('textarea')
    textarea.value = pdfUrl
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    document.body.removeChild(textarea)
  })
}

async function cloneEstimateData(): Promise<void> {
  const confirmed = window.confirm(t('estimates.confirm_clone'))
  if (!confirmed) return

  const res = await estimateStore.cloneEstimate({ id: props.row.id })
  router.push(`/admin/estimates/${res.data.data.id}/edit`)
}
</script>
