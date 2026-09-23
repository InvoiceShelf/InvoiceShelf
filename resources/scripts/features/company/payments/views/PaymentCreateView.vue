<template>
  <BasePage class="relative payment-create">
    <form class="flex flex-col gap-4 md:gap-5" @submit.prevent="submitPaymentData">
      <!-- On phones Save moves to the bottom bar, still submitting this form -->
      <BasePageHeader :title="pageTitle" phone-actions="bar">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
          <BaseBreadcrumbItem :title="$t('payments.payment', 2)" to="/admin/payments" />
          <BaseBreadcrumbItem :title="pageTitle" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <BaseButton :loading="isSaving" :disabled="isSaving" :content-loading="isLoadingContent" variant="primary" type="submit">
            <template #left="slotProps">
              <BaseIcon v-if="!isSaving" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
            </template>
            {{ isEdit ? $t('payments.update_payment') : $t('payments.save_payment') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <BaseCard container-class="p-4 md:p-5">
        <BaseInputGrid>
          <BaseInputGroup :label="$t('payments.date')" :content-loading="isLoadingContent" required>
            <BaseDatePicker v-model="paymentStore.currentPayment.payment_date" :content-loading="isLoadingContent" :calendar-button="true" calendar-button-icon="calendar" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('payments.payment_number')" :content-loading="isLoadingContent" required>
            <BaseInput v-model="paymentStore.currentPayment.payment_number" :content-loading="isLoadingContent" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('payments.customer')" :content-loading="isLoadingContent" required>
            <BaseCustomerSelectInput
              v-if="!isLoadingContent"
              v-model="paymentStore.currentPayment.customer_id"
              :content-loading="isLoadingContent"
              :placeholder="$t('customers.select_a_customer')"
              show-action
              @update:model-value="onManualCustomerSelect"
            />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('payments.amount')" :content-loading="isLoadingContent" required>
            <BaseMoney
              :key="String(paymentStore.currentPayment.currency)"
              v-model="amount"
              :currency="paymentStore.currentPayment.currency"
              :content-loading="isLoadingContent"
            />
          </BaseInputGroup>

          <ExchangeRateConverter
            store-prop="currentPayment"
            :v="{ exchange_rate: { $error: false, $errors: [], $touch: () => {} } }"
            :is-loading="isLoadingContent"
            :is-edit="isEdit"
            :customer-currency="paymentStore.currentPayment.currency_id"
          />

          <BaseInputGroup :content-loading="isLoadingContent" :label="$t('payments.payment_mode')">
            <BaseMultiselect
              v-model="paymentStore.currentPayment.payment_method_id"
              :content-loading="isLoadingContent"
              label="name"
              value-prop="id"
              track-by="name"
              :options="paymentStore.paymentModes"
              :placeholder="$t('payments.select_payment_mode')"
              searchable
            />
          </BaseInputGroup>

          <!-- Custom fields join the form's own grid rather than forming a
               band of their own; they are attributes like the rest. -->
          <CustomFieldInput
            v-for="field in customFields"
            :key="field.id"
            :custom-field-scope="customFieldValidationScope"
            :field="field"
          />
        </BaseInputGrid>

        <section class="pt-5 mt-5 border-t border-line-light">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
              <h2 class="font-semibold text-section text-heading">{{ $t('payments.allocations') }}</h2>
              <p class="mt-1 text-sm text-muted">{{ $t('payments.allocations_description') }}</p>
            </div>
            <div class="flex gap-2">
              <BaseButton type="button" size="sm" variant="primary-outline" :disabled="!invoiceList.length || !amount" @click="allocateOldestFirst">
                {{ $t('payments.allocate_oldest_first') }}
              </BaseButton>
              <BaseButton type="button" size="sm" variant="primary-outline" :disabled="!paymentStore.currentPayment.customer_id" @click="addAllocation">
                <template #left="slotProps"><BaseIcon name="PlusIcon" :class="slotProps.class" /></template>
                {{ $t('payments.add_allocation') }}
              </BaseButton>
            </div>
          </div>

          <div v-if="!paymentStore.currentPayment.customer_id" class="p-4 text-sm rounded-lg bg-surface-secondary text-muted">
            {{ $t('payments.select_customer_to_allocate') }}
          </div>

          <div v-else-if="!paymentStore.currentPayment.allocations.length" class="p-4 text-sm rounded-lg bg-surface-secondary text-muted">
            {{ $t('payments.no_allocations') }}
          </div>

          <div v-else class="space-y-3">
            <div v-for="(allocation, index) in paymentStore.currentPayment.allocations" :key="`${allocation.invoice_id}-${index}`" class="grid items-end gap-3 p-3 rounded-lg bg-surface-secondary md:grid-cols-[minmax(0,1fr)_12rem_auto]">
              <BaseInputGroup :label="$t('payments.invoice')">
                <BaseMultiselect
                  v-model="allocation.invoice_id"
                  value-prop="id"
                  track-by="invoice_number"
                  label="invoice_number"
                  :options="availableInvoices(allocation.invoice_id)"
                  :loading="isLoadingInvoices"
                  :placeholder="$t('invoices.select_invoice')"
                />
              </BaseInputGroup>
              <BaseInputGroup :label="$t('payments.amount')">
                <BaseMoney :model-value="allocation.amount / 100" :currency="paymentStore.currentPayment.currency" @update:model-value="setAllocationAmount(index, $event)" />
              </BaseInputGroup>
              <div class="justify-self-end">
                <BaseButton type="button" size="sm" variant="gray" :aria-label="$t('payments.remove_allocation')" @click="removeAllocation(index)">
                  <BaseIcon name="TrashIcon" />
                </BaseButton>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap justify-end gap-x-8 gap-y-2 pt-4 mt-4 text-sm border-t border-line-light">
            <span class="text-muted">{{ $t('payments.allocated') }}: <BaseFormatMoney :amount="allocatedAmount" :currency="paymentStore.currentPayment.currency" /></span>
            <span :class="unallocatedAmount < 0 ? 'text-status-red' : 'text-heading'">{{ $t('payments.unapplied_credit') }}: <BaseFormatMoney :amount="Math.max(unallocatedAmount, 0)" :currency="paymentStore.currentPayment.currency" /></span>
          </div>
        </section>

        <BaseInputGroup :label="$t('estimates.notes')" class="mt-5">
          <BaseCustomInput v-model="paymentStore.currentPayment.notes" :content-loading="isLoadingContent" :fields="paymentFields" />
        </BaseInputGroup>
      </BaseCard>
    </form>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { usePaymentStore } from '../store'
import CustomFieldInput from '@/scripts/features/shared/custom-fields/CustomFieldInput.vue'
import { useCustomFields } from '@/scripts/features/shared/custom-fields/use-custom-fields'
import { useCompanyStore } from '../../../../stores/company.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import { handleApiError, getErrorTranslationKey } from '../../../../utils/error-handling'
import { invoiceService } from '../../../../api/services/invoice.service'
import { customerService } from '../../../../api/services/customer.service'
import { ExchangeRateConverter } from '../../../shared/document-form'
import type { Invoice } from '../../../../types/domain/invoice'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const customFieldValidationScope = 'customFields'

const paymentStore = usePaymentStore()
const exchangeRateStore = paymentStore as unknown as Record<string, unknown> & { showExchangeRate: boolean }
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()

const isSaving = ref(false)
const isLoadingInvoices = ref(false)
const invoiceList = ref<Invoice[]>([])
const paymentFields = ref(['customer', 'company', 'customerCustom', 'payment', 'paymentCustom'])

const amount = computed<number>({
  get: () => paymentStore.currentPayment.amount / 100,
  set: (value) => { paymentStore.currentPayment.amount = Math.round(value * 100) },
})

const allocatedAmount = computed(() => paymentStore.currentPayment.allocations.reduce((total, allocation) => total + allocation.amount, 0))
const unallocatedAmount = computed(() => paymentStore.currentPayment.amount - allocatedAmount.value)
const isLoadingContent = computed(() => paymentStore.isFetchingInitialData)
const isEdit = computed(() => route.name === 'payments.edit')

const customFields = useCustomFields({
  store: paymentStore,
  storeProp: 'currentPayment',
  type: 'Payment',
  isEdit: () => isEdit.value,
})
const pageTitle = computed(() => isEdit.value ? t('payments.edit_payment') : t('payments.new_payment'))

paymentStore.resetCurrentPayment()

if (route.query.customer) paymentStore.currentPayment.customer_id = Number(route.query.customer)

paymentStore.fetchPaymentInitialData(
  isEdit.value,
  { id: isEdit.value ? String(route.params.id) : undefined },
  companyStore.selectedCompanyCurrency ?? undefined,
)

if (route.params.id && !isEdit.value) void setInvoiceFromUrl()

watch(
  () => paymentStore.currentPayment.customer_id,
  (customerId, previousCustomerId) => {
    if (!customerId) return
    if (previousCustomerId && customerId !== previousCustomerId) paymentStore.currentPayment.allocations = []
    void onCustomerChange(customerId)
  },
  { immediate: true },
)

async function setInvoiceFromUrl(): Promise<void> {
  try {
    const invoice = (await invoiceService.get(Number(route.params.id))).data
    paymentStore.currentPayment.customer_id = invoice.customer_id
    paymentStore.currentPayment.allocations = [{ invoice_id: invoice.id, amount: invoice.due_amount }]
    if (paymentStore.currentPayment.amount === 0) paymentStore.currentPayment.amount = invoice.due_amount
  } catch {
    // The normal form remains usable if the originating invoice no longer exists.
  }
}

async function onCustomerChange(customerId: number): Promise<void> {
  isLoadingInvoices.value = true
  try {
    const [invoiceResponse, customerResponse] = await Promise.all([
      invoiceService.list({ customer_id: customerId, limit: 'all' } as never),
      customerService.get(customerId),
    ])
    invoiceList.value = [...(invoiceResponse.data as unknown as Invoice[])]
    const customer = customerResponse.data
    paymentStore.currentPayment.customer = customer
    paymentStore.currentPayment.selectedCustomer = customer
    if (customer.currency) {
      paymentStore.currentPayment.currency = customer.currency
      paymentStore.currentPayment.currency_id = customer.currency.id
    }
  } catch {
    invoiceList.value = []
  } finally {
    isLoadingInvoices.value = false
  }
}

function availableInvoices(selectedInvoiceId: number): Invoice[] {
  const chosenInvoiceIds = paymentStore.currentPayment.allocations
    .map(({ invoice_id }) => invoice_id)
    .filter((id) => id !== selectedInvoiceId)
  return invoiceList.value
    .filter((invoice) => isEligibleInvoice(invoice) || invoice.id === selectedInvoiceId)
    .filter((invoice) => !chosenInvoiceIds.includes(invoice.id))
}

function isEligibleInvoice(invoice: Invoice): boolean {
  return invoice.type === 'INVOICE' && invoice.status !== 'DRAFT' && invoice.due_amount > 0
}

function onManualCustomerSelect(): void {
  paymentStore.currentPayment.allocations = []
  paymentStore.currentPayment.amount = 0
  const params: Record<string, unknown> = { userId: paymentStore.currentPayment.customer_id }
  if (route.params.id) params.model_id = route.params.id
  void paymentStore.getNextNumber(params, true)
}

function addAllocation(): void {
  const firstInvoice = availableInvoices(0)[0]
  if (firstInvoice) paymentStore.currentPayment.allocations.push({ invoice_id: firstInvoice.id, amount: 0 })
}

function removeAllocation(index: number): void {
  paymentStore.currentPayment.allocations.splice(index, 1)
}

function setAllocationAmount(index: number, value: number): void {
  const allocation = paymentStore.currentPayment.allocations[index]
  if (allocation) allocation.amount = Math.round(value * 100)
}

function allocateOldestFirst(): void {
  let remaining = paymentStore.currentPayment.amount
  const allocations: Array<{ invoice_id: number; amount: number }> = []
  for (const invoice of [...invoiceList.value]
    .filter(isEligibleInvoice)
    .sort((a, b) => (a.due_date ?? '9999-12-31').localeCompare(b.due_date ?? '9999-12-31') || a.id - b.id)) {
    if (remaining <= 0) break
    const allocated = Math.min(remaining, invoice.due_amount)
    allocations.push({ invoice_id: invoice.id, amount: allocated })
    remaining -= allocated
  }
  paymentStore.currentPayment.allocations = allocations
}

async function submitPaymentData(): Promise<void> {
  if (unallocatedAmount.value < 0) {
    notificationStore.showNotification({ type: 'error', message: t('payments.allocation_exceeds_amount') })
    return
  }

  isSaving.value = true
  const current = paymentStore.currentPayment
  const data = {
    ...(isEdit.value ? { id: current.id } : {}),
    payment_date: current.payment_date,
    payment_number: current.payment_number,
    customer_id: current.customer_id,
    amount: current.amount,
    payment_method_id: current.payment_method_id,
    notes: current.notes,
    currency_id: current.currency_id,
    exchange_rate: current.exchange_rate,
    customFields: current.customFields,
    fields: current.fields,
    allocations: current.allocations.filter(({ invoice_id, amount }) => invoice_id && amount > 0),
  }

  try {
    const response = await (isEdit.value ? paymentStore.updatePayment(data) : paymentStore.addPayment(data))
    await router.push(`/admin/payments/${response.data.data.id}/view`)
  } catch (error) {
    const normalized = handleApiError(error)
    const translationKey = getErrorTranslationKey(normalized.message)
    notificationStore.showNotification({ type: 'error', message: translationKey ? t(translationKey) : normalized.message })
  } finally {
    isSaving.value = false
  }
}

onBeforeUnmount(() => {
  paymentStore.resetCurrentPayment()
  invoiceList.value = []
})
</script>
