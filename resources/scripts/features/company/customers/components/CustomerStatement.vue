<template>
  <div class="mt-6 space-y-6">
    <BaseCard>
      <div class="flex flex-wrap items-end gap-4">
        <BaseInputGroup :label="$t('customers.statement_type')" class="min-w-48">
          <BaseMultiselect v-model="filters.type" :options="statementTypes" :allow-empty="false" :can-deselect="false" :show-labels="false" />
        </BaseInputGroup>

        <template v-if="filters.type === 'activity'">
          <BaseInputGroup :label="$t('customers.from_date')" class="min-w-44">
            <BaseDatePicker v-model="filters.from_date" />
          </BaseInputGroup>
          <BaseInputGroup :label="$t('customers.to_date')" class="min-w-44">
            <BaseDatePicker v-model="filters.to_date" />
          </BaseInputGroup>
        </template>
        <BaseInputGroup v-else :label="$t('customers.as_of')" class="min-w-44">
          <BaseDatePicker v-model="filters.as_of" />
        </BaseInputGroup>

        <div class="flex w-full flex-wrap gap-2 sm:ms-auto sm:w-auto">
          <BaseButton variant="primary-outline" :loading="isLoading" @click="refreshStatement">
            <template #left="slotProps"><BaseIcon name="ArrowPathIcon" :class="slotProps.class" /></template>
            {{ $t('general.refresh') }}
          </BaseButton>
          <BaseButton v-if="pdfUrl" variant="primary-outline" :loading="isDownloadingPdf" @click="downloadStatement">
            <template #left="slotProps"><BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" /></template>
            {{ $t('customers.download_statement') }}
          </BaseButton>
          <BaseButton variant="primary" :disabled="!statement" @click="openEmailModal">
            <template #left="slotProps"><BaseIcon name="PaperAirplaneIcon" :class="slotProps.class" /></template>
            {{ $t('customers.send_statement') }}
          </BaseButton>
        </div>
      </div>
    </BaseCard>

    <BaseCard v-if="isLoading" class="py-12 text-center text-muted">
      {{ $t('general.loading') }}
    </BaseCard>

    <template v-else-if="statement">
      <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-2 min-[1400px]:grid-cols-3">
        <BalanceCard :label="$t('customers.invoice_due')" :amount="invoiceDueAmount" :currency="statement.currency ?? customer.currency" />
        <BalanceCard :label="$t('customers.available_credit')" :amount="availableCredit" :currency="statement.currency ?? customer.currency" />
        <BalanceCard class="xl:col-span-2 min-[1400px]:col-span-1" :label="$t('customers.net_account_balance')" :amount="accountBalance" :currency="statement.currency ?? customer.currency" :credit-label="$t('customers.credit')" />
      </div>

      <BaseCard>
        <div class="flex items-center justify-between gap-4 mb-5">
          <div>
            <h2 class="text-lg font-semibold text-heading">{{ filters.type === 'activity' ? $t('customers.account_activity') : $t('customers.outstanding_items') }}</h2>
            <p v-if="filters.type === 'activity'" class="mt-1 text-sm text-muted">
              {{ $t('customers.opening_balance') }}: <BaseFormatMoney :amount="statement.opening_balance ?? 0" :currency="statement.currency ?? customer.currency" />
            </p>
          </div>
          <BaseButton v-if="availableCredit > 0 && canEditPayments" variant="primary-outline" @click="openCreditModal">
            {{ $t('customers.apply_credit') }}
          </BaseButton>
        </div>

        <div v-if="filters.type === 'activity'" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-line-default text-muted">
              <tr>
                <th class="px-3 py-3 text-start font-medium">{{ $t('general.date') }}</th>
                <th class="px-3 py-3 text-start font-medium">{{ $t('customers.activity') }}</th>
                <th class="px-3 py-3 text-end font-medium">{{ $t('customers.debit') }}</th>
                <th class="px-3 py-3 text-end font-medium">{{ $t('customers.credit') }}</th>
                <th class="px-3 py-3 text-end font-medium">{{ $t('customers.balance') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="entry in statement.entries ?? []" :key="`${entry.entry_type}-${entry.id}`" class="border-b border-line-light">
                <td class="px-3 py-3 whitespace-nowrap text-body">{{ entry.date }}</td>
                <td class="px-3 py-3 text-heading"><span class="font-medium">{{ entry.reference }}</span><span v-if="entry.description" class="block text-xs text-muted">{{ entry.description }}</span></td>
                <td class="px-3 py-3 text-end whitespace-nowrap"><BaseFormatMoney :amount="entry.debit_amount" :currency="statement.currency ?? customer.currency" /></td>
                <td class="px-3 py-3 text-end whitespace-nowrap"><BaseFormatMoney :amount="entry.credit_amount" :currency="statement.currency ?? customer.currency" /></td>
                <td class="px-3 py-3 text-end font-medium whitespace-nowrap text-heading"><BaseFormatMoney :amount="entry.balance ?? 0" :currency="statement.currency ?? customer.currency" /></td>
              </tr>
            </tbody>
          </table>
          <p v-if="!(statement.entries?.length)" class="py-8 text-center text-sm text-muted">{{ $t('customers.no_statement_activity') }}</p>
          <BaseTablePagination
            v-if="statement.meta"
            :pagination="{
              currentPage: statement.meta.current_page,
              totalPages: statement.meta.last_page,
              totalCount: statement.meta.total,
              count: statement.entries?.length ?? 0,
              limit: statement.meta.per_page,
            }"
            @page-change="changeActivityPage"
          />
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-line-default text-muted">
              <tr>
                <th class="px-3 py-3 text-start font-medium">{{ $t('payments.invoice') }}</th>
                <th class="px-3 py-3 text-start font-medium">{{ $t('invoices.due_date') }}</th>
                <th class="px-3 py-3 text-end font-medium">{{ $t('customers.original_amount') }}</th>
                <th class="px-3 py-3 text-end font-medium">{{ $t('customers.applied') }}</th>
                <th class="px-3 py-3 text-end font-medium">{{ $t('customers.remaining') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="invoice in statement.invoices ?? []" :key="invoice.id" class="border-b border-line-light">
                <td class="px-3 py-3 whitespace-nowrap"><router-link :to="`/admin/invoices/${invoice.id}/view`" class="font-medium text-primary-500">{{ invoice.invoice_number }}</router-link></td>
                <td class="px-3 py-3 whitespace-nowrap text-body">{{ invoice.due_date }}</td>
                <td class="px-3 py-3 text-end whitespace-nowrap"><BaseFormatMoney :amount="invoice.original_amount" :currency="statement.currency ?? customer.currency" /></td>
                <td class="px-3 py-3 text-end whitespace-nowrap"><BaseFormatMoney :amount="invoice.applied_amount" :currency="statement.currency ?? customer.currency" /></td>
                <td class="px-3 py-3 text-end font-medium whitespace-nowrap text-heading"><BaseFormatMoney :amount="invoice.remaining_amount" :currency="statement.currency ?? customer.currency" /></td>
              </tr>
            </tbody>
          </table>
          <p v-if="!(statement.invoices?.length)" class="py-8 text-center text-sm text-muted">{{ $t('customers.no_outstanding_invoices') }}</p>
        </div>
        <div v-if="filters.type === 'outstanding' && statement.credits?.length" class="pt-5 mt-5 border-t border-line-default">
          <h3 class="mb-3 text-sm font-semibold text-heading">{{ $t('customers.available_credit') }}</h3>
          <div v-for="credit in statement.credits" :key="credit.id" class="flex items-center justify-between py-2 text-sm border-b border-line-light">
            <router-link :to="`/admin/payments/${credit.id}/view`" class="font-medium text-primary-500">{{ credit.payment_number }}</router-link>
            <BaseFormatMoney :amount="credit.available_amount" :currency="statement.currency ?? customer.currency" class="font-medium text-status-green" />
          </div>
        </div>
      </BaseCard>
    </template>

    <BaseModal :show="showEmailModal" closable @close="showEmailModal = false">
      <template #header>{{ $t('customers.send_statement') }}</template>
      <form @submit.prevent="sendStatement">
        <div class="p-6 space-y-4">
          <BaseInputGroup :label="$t('general.to')" required><BaseInput v-model="emailForm.to" type="email" /></BaseInputGroup>
          <BaseInputGroup :label="$t('general.cc')"><BaseInput v-model="emailForm.cc" type="email" /></BaseInputGroup>
          <BaseInputGroup :label="$t('general.bcc')"><BaseInput v-model="emailForm.bcc" type="email" /></BaseInputGroup>
          <BaseInputGroup :label="$t('general.subject')" required><BaseInput v-model="emailForm.subject" /></BaseInputGroup>
          <BaseInputGroup :label="$t('general.body')" required><BaseTextarea v-model="emailForm.body" /></BaseInputGroup>
        </div>
        <div class="flex justify-end gap-3 p-4 border-t border-line-default"><BaseButton variant="primary-outline" type="button" @click="showEmailModal = false">{{ $t('general.cancel') }}</BaseButton><BaseButton variant="primary" :loading="isSending" type="submit">{{ $t('general.send') }}</BaseButton></div>
      </form>
    </BaseModal>

    <BaseModal :show="showCreditModal" closable @close="showCreditModal = false">
      <template #header>{{ $t('customers.apply_credit') }}</template>
      <div class="p-6">
        <p class="mb-4 text-sm text-muted">{{ $t('customers.apply_credit_description') }}</p>
        <div v-if="creditRows.length" class="space-y-3">
          <div v-for="(row, index) in creditRows" :key="index" class="grid items-end gap-3 p-3 rounded-lg bg-surface-secondary md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_9rem_auto]">
            <BaseInputGroup :label="$t('payments.payment')"><BaseMultiselect v-model="row.payment_id" value-prop="id" track-by="payment_number" label="payment_number" :options="creditPayments" /></BaseInputGroup>
            <BaseInputGroup :label="$t('payments.invoice')"><BaseMultiselect v-model="row.invoice_id" value-prop="id" track-by="invoice_number" label="invoice_number" :options="openInvoices" /></BaseInputGroup>
            <BaseInputGroup :label="$t('payments.amount')"><BaseMoney :model-value="row.amount / 100" :currency="statement?.currency ?? customer.currency" @update:model-value="setCreditAmount(index, $event)" /></BaseInputGroup>
            <div class="justify-self-end">
              <BaseButton type="button" size="sm" variant="gray" :aria-label="$t('payments.remove_allocation')" @click="creditRows.splice(index, 1)"><BaseIcon name="TrashIcon" /></BaseButton>
            </div>
          </div>
        </div>
        <div class="flex flex-wrap gap-2 mt-4">
          <BaseButton size="sm" variant="primary-outline" :disabled="!creditPayments.length || !openInvoices.length" @click="allocateCreditOldestFirst">{{ $t('payments.allocate_oldest_first') }}</BaseButton>
          <BaseButton size="sm" variant="primary-outline" @click="addCreditRow"><template #left="slotProps"><BaseIcon name="PlusIcon" :class="slotProps.class" /></template>{{ $t('payments.add_allocation') }}</BaseButton>
        </div>
      </div>
      <template #footer><div class="flex justify-end gap-3 p-4 border-t border-line-default"><BaseButton variant="primary-outline" @click="showCreditModal = false">{{ $t('general.cancel') }}</BaseButton><BaseButton variant="primary" :loading="isApplyingCredit" @click="applyCredit">{{ $t('customers.apply_credit') }}</BaseButton></div></template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { customerService, type CustomerStatement, type CustomerStatementParams } from '@/scripts/api/services/customer.service'
import { invoiceService } from '@/scripts/api/services/invoice.service'
import { paymentService } from '@/scripts/api/services/payment.service'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { downloadDocument } from '@/scripts/utils/documents'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import { formatDate } from '@/scripts/utils/format-date'
import type { Customer } from '@/scripts/types/domain/customer'
import type { Invoice } from '@/scripts/types/domain/invoice'
import type { Payment } from '@/scripts/types/domain/payment'
import BalanceCard from './CustomerBalanceCard.vue'

const props = defineProps<{ customer: Customer }>()
const { t } = useI18n()
const notificationStore = useNotificationStore()
const userStore = useUserStore()
const today = formatDate(new Date())
const monthStart = `${today.slice(0, 8)}01`
const filters = reactive<CustomerStatementParams>({ type: 'activity', from_date: monthStart, to_date: today, as_of: today })
const statement = ref<CustomerStatement | null>(null)
const isLoading = ref(false)
const isDownloadingPdf = ref(false)
const showEmailModal = ref(false)
const isSending = ref(false)
const showCreditModal = ref(false)
const isApplyingCredit = ref(false)
const creditPayments = ref<Payment[]>([])
const openInvoices = ref<Invoice[]>([])
const creditRows = ref<Array<{ payment_id: number; invoice_id: number; amount: number }>>([])
const emailForm = reactive({ to: '', cc: '', bcc: '', subject: '', body: '' })
const statementTypes = computed(() => [
  { label: t('customers.account_activity'), value: 'activity' },
  { label: t('customers.outstanding_items'), value: 'outstanding' },
])
const statementParams = computed<CustomerStatementParams>(() => filters.type === 'activity'
  ? { type: 'activity', from_date: filters.from_date, to_date: filters.to_date, page: filters.page ?? 1 }
  : { type: 'outstanding', as_of: filters.as_of })
const pdfUrl = computed(() => statement.value ? customerService.statementPdfUrl(props.customer.id, statementParams.value) : '')
const invoiceDueAmount = computed(() => statement.value?.invoice_due_amount ?? props.customer.invoice_due_amount ?? props.customer.due_amount ?? 0)
const availableCredit = computed(() => statement.value?.available_credit ?? props.customer.available_credit ?? 0)
const accountBalance = computed(() => statement.value?.account_balance ?? props.customer.account_balance ?? (invoiceDueAmount.value - availableCredit.value))
const canEditPayments = computed(() => userStore.hasAbilities('edit-payment'))

watch(() => props.customer.id, () => void loadStatement(), { immediate: true })
watch(() => filters.type, () => {
  filters.page = 1
  void loadStatement()
})

async function loadStatement(): Promise<void> {
  isLoading.value = true
  try {
    statement.value = (await customerService.getStatement(props.customer.id, statementParams.value)).data
  } catch {
    statement.value = null
  } finally {
    isLoading.value = false
  }
}

function refreshStatement(): void {
  filters.page = 1
  void loadStatement()
}

/**
 * The statement used to be a link opened in a new tab, which only reaches a
 * server the page shares an origin with. It is fetched through the API client
 * and handed over as a file instead, the same thing the tab ended up doing.
 */
async function downloadStatement(): Promise<void> {
  if (!pdfUrl.value) {
    return
  }

  isDownloadingPdf.value = true

  try {
    await downloadDocument(pdfUrl.value, {}, 'statement.pdf')
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: t('pdf.download_failed'),
    })
  } finally {
    isDownloadingPdf.value = false
  }
}

function changeActivityPage(page: number): void {
  filters.page = page
  void loadStatement()
}

async function sendStatement(): Promise<void> {
  if (!emailForm.to || !emailForm.subject || !emailForm.body) return
  isSending.value = true
  try {
    await customerService.sendStatement(props.customer.id, { ...statementParams.value, ...emailForm })
    notificationStore.showNotification({ type: 'success', message: t('customers.statement_sent') })
    showEmailModal.value = false
  } catch (error) {
    showErrorNotification(error)
  } finally {
    isSending.value = false
  }
}

function openEmailModal(): void {
  emailForm.to = props.customer.email ?? ''
  emailForm.subject = t('customers.statement')
  emailForm.body = t('customers.statement_email_body')
  showEmailModal.value = true
}

async function openCreditModal(): Promise<void> {
  showCreditModal.value = true
  const [payments, invoices] = await Promise.all([
    paymentService.list({ customer_id: props.customer.id, limit: 'all' }),
    invoiceService.list({ customer_id: props.customer.id, limit: 'all' } as never),
  ])
  creditPayments.value = payments.data.filter((payment) => (payment.unallocated_amount ?? 0) > 0)
  openInvoices.value = (invoices.data as unknown as Invoice[]).filter(isEligibleInvoice)
  if (!creditRows.value.length) addCreditRow()
}

function addCreditRow(): void {
  const payment = creditPayments.value[0]
  const invoice = openInvoices.value[0]
  if (payment && invoice) creditRows.value.push({ payment_id: payment.id, invoice_id: invoice.id, amount: 0 })
}

function setCreditAmount(index: number, value: number): void {
  if (creditRows.value[index]) creditRows.value[index].amount = Math.round(value * 100)
}

function isEligibleInvoice(invoice: Invoice): boolean {
  return invoice.type === 'INVOICE' && invoice.status !== 'DRAFT' && invoice.due_amount > 0
}

function allocateCreditOldestFirst(): void {
  const remainingInvoices = new Map(
    [...openInvoices.value]
      .sort((a, b) => (a.due_date ?? '9999-12-31').localeCompare(b.due_date ?? '9999-12-31') || a.id - b.id)
      .map((invoice) => [invoice.id, invoice.due_amount]),
  )
  const rows: Array<{ payment_id: number; invoice_id: number; amount: number }> = []

  for (const payment of [...creditPayments.value].sort((a, b) => (a.payment_date ?? '').localeCompare(b.payment_date ?? '') || a.id - b.id)) {
    let remainingCredit = payment.unallocated_amount ?? 0
    for (const [invoiceId, invoiceDue] of remainingInvoices) {
      if (remainingCredit <= 0) break
      if (invoiceDue <= 0) continue

      const amount = Math.min(remainingCredit, invoiceDue)
      rows.push({ payment_id: payment.id, invoice_id: invoiceId, amount })
      remainingCredit -= amount
      remainingInvoices.set(invoiceId, invoiceDue - amount)
    }
  }

  creditRows.value = rows
}

async function applyCredit(): Promise<void> {
  const allocations = creditRows.value.filter((row) => row.payment_id && row.invoice_id && row.amount > 0)
  if (!allocations.length) return
  isApplyingCredit.value = true
  try {
    await customerService.allocateCredit(props.customer.id, allocations)
    notificationStore.showNotification({ type: 'success', message: t('customers.credit_applied') })
    showCreditModal.value = false
    creditRows.value = []
    await loadStatement()
  } catch (error) {
    showErrorNotification(error)
  } finally {
    isApplyingCredit.value = false
  }
}

function showErrorNotification(error: unknown): void {
  const normalized = handleApiError(error)
  const translationKey = getErrorTranslationKey(normalized.message)
  notificationStore.showNotification({ type: 'error', message: translationKey ? t(translationKey) : normalized.message })
}
</script>
