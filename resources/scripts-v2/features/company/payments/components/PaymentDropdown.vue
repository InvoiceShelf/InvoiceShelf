<template>
  <BaseDropdown :content-loading="contentLoading">
    <template #activator>
      <BaseButton v-if="isDetailView" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-muted" />
    </template>

    <!-- Copy PDF url -->
    <BaseDropdownItem
      v-if="isDetailView && canView"
      class="rounded-md"
      @click="copyPdfUrl"
    >
      <BaseIcon
        name="LinkIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.copy_pdf_url') }}
    </BaseDropdownItem>

    <!-- Edit Payment -->
    <router-link
      v-if="canEdit"
      :to="`/admin/payments/${row.id}/edit`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- View Payment -->
    <router-link
      v-if="!isDetailView && canView"
      :to="`/admin/payments/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="EyeIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Send Payment -->
    <BaseDropdownItem
      v-if="!isDetailView && canSend"
      @click="sendPayment"
    >
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
      />
      {{ $t('payments.send_payment') }}
    </BaseDropdownItem>

    <!-- Delete Payment -->
    <BaseDropdownItem v-if="canDelete" @click="removePayment">
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
import { usePaymentStore } from '../store'
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
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const isDetailView = computed<boolean>(() => route.name === 'payments.view')

async function removePayment(): Promise<void> {
  const confirmed = window.confirm(t('payments.confirm_delete'))
  if (!confirmed) return

  const payment = props.row as Payment
  await paymentStore.deletePayment({ ids: [payment.id] })
  router.push('/admin/payments')
  props.table?.refresh()
}

function copyPdfUrl(): void {
  const payment = props.row as Payment
  const pdfUrl = `${window.location.origin}/payments/pdf/${payment.unique_hash}`
  navigator.clipboard.writeText(pdfUrl).catch(() => {
    const textarea = document.createElement('textarea')
    textarea.value = pdfUrl
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    document.body.removeChild(textarea)
  })
}

function sendPayment(): void {
  const payment = props.row as Payment
  const modalStore = (window as Record<string, unknown>).__modalStore as
    | { openModal: (opts: Record<string, unknown>) => void }
    | undefined
  modalStore?.openModal({
    title: t('payments.send_payment'),
    componentName: 'SendPaymentModal',
    id: payment.id,
    data: payment,
    variant: 'lg',
  })
}
</script>
