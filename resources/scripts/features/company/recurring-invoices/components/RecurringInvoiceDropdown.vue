<template>
  <BaseDropdown :content-loading="recurringInvoiceStore.isFetchingViewData">
    <template #activator>
      <span v-if="isDetailView" data-overflow class="inline-flex items-center justify-center border rounded-lg w-11 h-11 md:w-9 md:h-9 bg-surface border-line-default text-body hover:bg-hover">
        <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
      </span>
      <span v-else class="inline-flex items-center justify-center rounded-lg w-9 h-9 text-muted hover:bg-hover-strong hover:text-heading">
        <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
      </span>
    </template>

    <!-- Edit Recurring Invoice -->
    <BaseDropdownItem v-if="canEdit" :to="`/admin/recurring-invoices/${row.id}/edit`">
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <!-- View Recurring Invoice -->
    <BaseDropdownItem v-if="!isDetailView && canView" :to="`recurring-invoices/${row.id}/view`">
      <BaseIcon
        name="EyeIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.view') }}
    </BaseDropdownItem>

    <!-- Pause or resume (the detail page has its own button) -->
    <BaseDropdownItem
      v-if="canEdit && !isDetailView && ['ACTIVE', 'ON_HOLD'].includes(String(row.status))"
      @click="toggleStatus"
    >
      <BaseIcon
        :name="row.status === 'ACTIVE' ? 'PauseIcon' : 'PlayIcon'"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t(row.status === 'ACTIVE' ? 'recurring_invoices.pause' : 'recurring_invoices.resume') }}
    </BaseDropdownItem>

    <!-- Delete Recurring Invoice -->
    <BaseDropdownItem v-if="canDelete" @click="removeRecurringInvoice">
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
import { useRecurringInvoiceStore } from '../store'
import { useDialogStore } from '../../../../stores/dialog.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { recurringInvoiceService } from '@/scripts/api/services/recurring-invoice.service'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import type { RecurringInvoice } from '../../../../types/domain/recurring-invoice'

interface TableRef {
  refresh: () => void
}

interface Props {
  row: RecurringInvoice | Record<string, unknown>
  table?: TableRef | null
  loadData?: (() => void) | null
  canEdit?: boolean
  canView?: boolean
  canDelete?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  table: null,
  loadData: null,
  canEdit: false,
  canView: false,
  canDelete: false,
})

const recurringInvoiceStore = useRecurringInvoiceStore()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const isDetailView = computed<boolean>(
  () => route.name === 'recurring-invoices.view',
)

async function toggleStatus(): Promise<void> {
  const action = props.row.status === 'ACTIVE' ? 'pause' : 'resume'
  try {
    await recurringInvoiceService.act(Number(props.row.id), action)
    props.table?.refresh()
    props.loadData?.()
    notificationStore.showNotification({
      type: 'success',
      message: t(action === 'pause' ? 'recurring_invoices.paused_message' : 'recurring_invoices.resumed_message'),
    })
  } catch (err) {
    const { message, validationErrors } = handleApiError(err)
    const code = Object.values(validationErrors ?? {}).flat()[0] ?? message
    const key = getErrorTranslationKey(code)
    notificationStore.showNotification({ type: 'error', message: key ? t(key) : code })
  }
}

function removeRecurringInvoice(): void {
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
      const invoiceRow = props.row as RecurringInvoice
      const response = await recurringInvoiceStore.deleteMultipleRecurringInvoices(
        invoiceRow.id,
      )

      if (response.data.success) {
        props.table?.refresh()
        recurringInvoiceStore.$patch((state) => {
          state.selectedRecurringInvoices = []
          state.selectAllField = false
        })

        if (isDetailView.value) {
          router.push('/admin/recurring-invoices')
        }
      }
    }
  })
}
</script>
