<template>
  <BaseDropdown>
    <template #activator>
      <BaseButton v-if="route.name === 'invoices.view'" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-gray-500" />
    </template>

    <!-- Edit Invoice  -->
    <router-link
      v-if="userStore.hasAbilities(abilities.EDIT_INVOICE)"
      :to="`/admin/invoices/${row.id}/edit`"
    >
      <BaseDropdownItem v-show="row.allow_edit">
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Copy PDF url  -->
    <BaseDropdownItem v-if="route.name === 'invoices.view'" @click="copyPdfUrl">
      <BaseIcon
        name="LinkIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('general.copy_pdf_url') }}
    </BaseDropdownItem>

    <!-- View Invoice  -->
    <router-link
      v-if="
        route.name !== 'invoices.view' &&
        userStore.hasAbilities(abilities.VIEW_INVOICE)
      "
      :to="`/admin/invoices/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="EyeIcon"
          class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
        />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Send Invoice Mail  -->
    <BaseDropdownItem v-if="canSendInvoice(row)" @click="sendInvoice(row)">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('invoices.send_invoice') }}
    </BaseDropdownItem>

    <!-- Resend Invoice -->
    <BaseDropdownItem v-if="canReSendInvoice(row)" @click="sendInvoice(row)">
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('invoices.resend_invoice') }}
    </BaseDropdownItem>

    <!-- Record payment  -->
    <router-link :to="`/admin/payments/${row.id}/create`">
      <BaseDropdownItem
        v-if="row.status == 'SENT' && route.name !== 'invoices.view'"
      >
        <BaseIcon
          name="CreditCardIcon"
          class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
        />
        {{ $t('invoices.record_payment') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Mark as sent Invoice -->
    <BaseDropdownItem v-if="canSendInvoice(row)" @click="onMarkAsSent(row.id)">
      <BaseIcon
        name="CheckCircleIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('invoices.mark_as_sent') }}
    </BaseDropdownItem>

    <!-- Mark as paid -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.EDIT_INVOICE)"
      :disabled="isPaid"
      @click="onMarkAsPaid(row)"
    >
      <BaseIcon
        name="CheckCircleIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('invoices.mark_as_paid') }}
    </BaseDropdownItem>

    <!-- Mark as partially paid -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.EDIT_INVOICE)"
      :disabled="isPartialDisabled"
      @click="onMarkAsPartiallyPaid(row)"
    >
      <BaseIcon
        name="MinusCircleIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('invoices.mark_as_partially_paid') }}
    </BaseDropdownItem>

    <!-- Mark as unpaid -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.EDIT_INVOICE)"
      :disabled="isUnpaid"
      @click="onMarkAsUnpaid(row)"
    >
      <BaseIcon
        name="XCircleIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('invoices.mark_as_unpaid') }}
    </BaseDropdownItem>

    <!-- Mark as draft -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.EDIT_INVOICE)"
      :disabled="isDraft"
      @click="onMarkAsStatus(row, 'DRAFT')"
    >
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('invoices.mark_as_draft') }}
    </BaseDropdownItem>

    <!-- Mark as viewed -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.EDIT_INVOICE)"
      :disabled="isViewed"
      @click="onMarkAsStatus(row, 'VIEWED')"
    >
      <BaseIcon
        name="EyeIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('invoices.mark_as_viewed') }}
    </BaseDropdownItem>

    <!-- Mark as completed -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.EDIT_INVOICE)"
      :disabled="isCompleted"
      @click="onMarkAsStatus(row, 'COMPLETED')"
    >
      <BaseIcon
        name="CheckCircleIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('invoices.mark_as_completed') }}
    </BaseDropdownItem>

    <!-- Clone Invoice into new invoice  -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.CREATE_INVOICE)"
      @click="cloneInvoiceData(row)"
    >
      <BaseIcon
        name="DocumentTextIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('invoices.clone_invoice') }}
    </BaseDropdownItem>

    <!--  Delete Invoice  -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.DELETE_INVOICE)"
      @click="removeInvoice(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup>
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useModalStore } from '@/scripts/stores/modal'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/scripts/admin/stores/user'
import { computed, inject } from 'vue'
import abilities from '@/scripts/admin/stub/abilities'

const props = defineProps({
  row: {
    type: Object,
    default: null,
  },
  table: {
    type: Object,
    default: null,
  },
  loadData: {
    type: Function,
    default: () => {},
  },
  refreshInvoice: {
    type: Function,
    default: () => {},
  },
})

const invoiceStore = useInvoiceStore()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const utils = inject('utils')

const isPaid = computed(() => props.row?.paid_status === 'PAID')
const isUnpaid = computed(() => props.row?.paid_status === 'UNPAID')
const isPartiallyPaid = computed(
  () => props.row?.paid_status === 'PARTIALLY_PAID'
)
const isPartialDisabled = computed(() => isPaid.value)
const isDraft = computed(() => props.row?.status === 'DRAFT')
const isViewed = computed(() => props.row?.status === 'VIEWED')
const isCompleted = computed(() => props.row?.status === 'COMPLETED')

function canReSendInvoice(row) {
  return (
    (row.status == 'SENT' || row.status == 'VIEWED') &&
    userStore.hasAbilities(abilities.SEND_INVOICE)
  )
}

function canSendInvoice(row) {
  return (
    row.status == 'DRAFT' &&
    route.name !== 'invoices.view' &&
    userStore.hasAbilities(abilities.SEND_INVOICE)
  )
}

async function removeInvoice(id) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('invoices.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res) => {
      id = id
      if (res) {
        invoiceStore.deleteInvoice({ ids: [id] }).then((res) => {
          if (res.data.success) {
            router.push('/admin/invoices')
            props.table && props.table.refresh()

            invoiceStore.$patch((state) => {
              state.selectedInvoices = []
              state.selectAllField = false
            })
          }
        })
      }
    })
}

async function cloneInvoiceData(data) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('invoices.confirm_clone'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res) => {
      if (res) {
        invoiceStore.cloneInvoice(data).then((res) => {
          router.push(`/admin/invoices/${res.data.data.id}/edit`)
        })
      }
    })
}

async function onMarkAsSent(id) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('invoices.invoice_mark_as_sent'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then((response) => {
      const data = {
        id: id,
        status: 'SENT',
      }
      if (response) {
        invoiceStore.markAsSent(data).then((response) => {
          props.table && props.table.refresh()
          props.refreshInvoice && props.refreshInvoice()
          if (props.row) {
            props.row.status = 'SENT'
            props.row.sent = true
            props.row.viewed = false
          }
        })
      }
    })
}

function onMarkAsPaid(row) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('invoices.confirm_mark_as_paid'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then((response) => {
      if (!response) {
        return
      }

      invoiceStore
        .updateInvoiceStatus(
          {
            id: row.id,
            paid_status: 'PAID',
            reset_payments: true,
          },
          {
            message: t('invoices.mark_as_paid_successfully'),
          }
        )
        .then(() => {
          props.table && props.table.refresh()
          props.refreshInvoice && props.refreshInvoice()
          row.status = 'COMPLETED'
          row.paid_status = 'PAID'
          row.due_amount = 0
        })
    })
}

function onMarkAsUnpaid(row) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('invoices.confirm_mark_as_unpaid'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then((response) => {
      if (!response) {
        return
      }

      invoiceStore
        .updateInvoiceStatus(
          {
            id: row.id,
            paid_status: 'UNPAID',
            reset_payments: true,
          },
          {
            message: t('invoices.mark_as_unpaid_successfully'),
          }
        )
        .then(() => {
          props.table && props.table.refresh()
          props.refreshInvoice && props.refreshInvoice()
          row.paid_status = 'UNPAID'
          row.due_amount = row.total
          row.status = row.viewed ? 'VIEWED' : row.sent ? 'SENT' : 'DRAFT'
        })
    })
}

function onMarkAsPartiallyPaid(row) {
  modalStore.openModal({
    title: t('invoices.mark_as_partially_paid'),
    componentName: 'InvoicePartialPaidModal',
    data: {
      invoice: row,
    },
    refreshData: () => {
      props.table && props.table.refresh()
      props.refreshInvoice && props.refreshInvoice()
    },
    size: 'sm',
  })
}

function onMarkAsStatus(row, status) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t(`invoices.confirm_mark_as_${status.toLowerCase()}`),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'primary',
      hideNoButton: false,
      size: 'lg',
    })
    .then((response) => {
      if (!response) {
        return
      }

      invoiceStore
        .updateInvoiceStatus(
          {
            id: row.id,
            status,
          },
          {
            message: t(`invoices.mark_as_${status.toLowerCase()}_successfully`),
          }
        )
        .then(() => {
          props.table && props.table.refresh()
          props.refreshInvoice && props.refreshInvoice()
          row.status = status
          if (status === 'DRAFT') {
            row.sent = false
            row.viewed = false
          }
          if (status === 'SENT') {
            row.sent = true
            row.viewed = false
          }
          if (status === 'VIEWED') {
            row.sent = true
            row.viewed = true
          }
          if (status === 'COMPLETED') {
            row.paid_status = 'PAID'
            row.due_amount = 0
          }
        })
    })
}

async function sendInvoice(invoice) {
  modalStore.openModal({
    title: t('invoices.send_invoice'),
    componentName: 'SendInvoiceModal',
    id: invoice.id,
    data: invoice,
    variant: 'sm',
  })
}

function copyPdfUrl() {
  let pdfUrl = `${window.location.origin}/invoices/pdf/${props.row.unique_hash}`

  utils.copyTextToClipboard(pdfUrl)

  notificationStore.showNotification({
    type: 'success',
    message: t('general.copied_pdf_url_clipboard'),
  })
}
</script>
