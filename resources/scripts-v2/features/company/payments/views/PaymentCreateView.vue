<template>
  <BasePage class="relative payment-create">
    <form action="" @submit.prevent="submitPaymentData">
      <BasePageHeader :title="pageTitle" class="mb-5">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('general.home')"
            to="/admin/dashboard"
          />
          <BaseBreadcrumbItem
            :title="$t('payments.payment', 2)"
            to="/admin/payments"
          />
          <BaseBreadcrumbItem :title="pageTitle" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <BaseButton
            :loading="isSaving"
            :disabled="isSaving"
            variant="primary"
            type="submit"
            class="hidden sm:flex"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                name="ArrowDownOnSquareIcon"
                :class="slotProps.class"
              />
            </template>
            {{
              isEdit
                ? $t('payments.update_payment')
                : $t('payments.save_payment')
            }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <BaseCard>
        <BaseInputGrid>
          <!-- Payment Date -->
          <BaseInputGroup
            :label="$t('payments.date')"
            :content-loading="isLoadingContent"
            required
          >
            <BaseDatePicker
              v-model="paymentStore.currentPayment.payment_date"
              :content-loading="isLoadingContent"
              :calendar-button="true"
              calendar-button-icon="calendar"
            />
          </BaseInputGroup>

          <!-- Payment Number -->
          <BaseInputGroup
            :label="$t('payments.payment_number')"
            :content-loading="isLoadingContent"
            required
          >
            <BaseInput
              v-model="paymentStore.currentPayment.payment_number"
              :content-loading="isLoadingContent"
            />
          </BaseInputGroup>

          <!-- Customer -->
          <BaseInputGroup
            :label="$t('payments.customer')"
            :content-loading="isLoadingContent"
            required
          >
            <BaseCustomerSelectInput
              v-if="!isLoadingContent"
              v-model="paymentStore.currentPayment.customer_id"
              :content-loading="isLoadingContent"
              :placeholder="$t('customers.select_a_customer')"
              show-action
              @update:model-value="onManualCustomerSelect"
            />
          </BaseInputGroup>

          <!-- Invoice -->
          <BaseInputGroup
            :content-loading="isLoadingContent"
            :label="$t('payments.invoice')"
          >
            <BaseMultiselect
              v-model="paymentStore.currentPayment.invoice_id"
              :content-loading="isLoadingContent"
              value-prop="id"
              track-by="invoice_number"
              label="invoice_number"
              :options="invoiceList"
              :loading="isLoadingInvoices"
              :placeholder="$t('invoices.select_invoice')"
              @select="onSelectInvoice"
            />
          </BaseInputGroup>

          <!-- Amount -->
          <BaseInputGroup
            :label="$t('payments.amount')"
            :content-loading="isLoadingContent"
            required
          >
            <div class="relative w-full">
              <BaseMoney
                :key="String(paymentStore.currentPayment.currency)"
                v-model="amount"
                :currency="paymentStore.currentPayment.currency"
                :content-loading="isLoadingContent"
              />
            </div>
          </BaseInputGroup>

          <!-- Payment Mode -->
          <BaseInputGroup
            :content-loading="isLoadingContent"
            :label="$t('payments.payment_mode')"
          >
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
        </BaseInputGrid>

        <!-- Notes -->
        <div class="relative mt-6">
          <label class="mb-4 text-sm font-medium text-heading">
            {{ $t('estimates.notes') }}
          </label>

          <BaseCustomInput
            v-model="paymentStore.currentPayment.notes"
            :content-loading="isLoadingContent"
            :fields="paymentFields"
            class="mt-1"
          />
        </div>

        <!-- Mobile Save Button -->
        <BaseButton
          :loading="isSaving"
          :content-loading="isLoadingContent"
          variant="primary"
          type="submit"
          class="flex justify-center w-full mt-4 sm:hidden md:hidden"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              name="ArrowDownOnSquareIcon"
              :class="slotProps.class"
            />
          </template>
          {{
            isEdit
              ? $t('payments.update_payment')
              : $t('payments.save_payment')
          }}
        </BaseButton>
      </BaseCard>
    </form>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, computed, watchEffect, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import cloneDeep from 'lodash/cloneDeep'
import { usePaymentStore } from '../store'
import { invoiceService } from '../../../../api/services/invoice.service'
import type { Invoice } from '../../../../types/domain/invoice'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const paymentStore = usePaymentStore()

const isSaving = ref<boolean>(false)
const isLoadingInvoices = ref<boolean>(false)
const invoiceList = ref<Invoice[]>([])
const selectedInvoice = ref<Invoice | null>(null)

const paymentFields = ref<string[]>([
  'customer',
  'company',
  'customerCustom',
  'payment',
  'paymentCustom',
])

const amount = computed<number>({
  get: () => paymentStore.currentPayment.amount / 100,
  set: (value: number) => {
    paymentStore.currentPayment.amount = Math.round(value * 100)
  },
})

const isLoadingContent = computed<boolean>(
  () => paymentStore.isFetchingInitialData,
)

const isEdit = computed<boolean>(() => route.name === 'payments.edit')

const pageTitle = computed<string>(() => {
  return isEdit.value ? t('payments.edit_payment') : t('payments.new_payment')
})

// Reset state on create
paymentStore.resetCurrentPayment()

if (route.query.customer) {
  paymentStore.currentPayment.customer_id = Number(route.query.customer)
}

paymentStore.fetchPaymentInitialData(isEdit.value, {
  id: isEdit.value ? (route.params.id as string) : undefined,
})

// Create-from-invoice: pre-select the invoice and its customer
if (route.params.id && !isEdit.value) {
  setInvoiceFromUrl()
}

async function setInvoiceFromUrl(): Promise<void> {
  try {
    const response = await invoiceService.get(Number(route.params.id))
    const invoice = response.data
    paymentStore.currentPayment.customer_id = invoice.customer_id ?? (invoice.customer as Record<string, unknown>)?.id as number
    paymentStore.currentPayment.invoice_id = invoice.id
  } catch {
    // Invoice not found
  }
}

// Reactively fetch invoices whenever customer_id changes
// Handles: edit data load, manual selection, create-from-invoice
watchEffect(() => {
  if (paymentStore.currentPayment.customer_id) {
    onCustomerChange(paymentStore.currentPayment.customer_id)
  }
})

async function onCustomerChange(customerId: number): Promise<void> {
  const params: Record<string, unknown> = {
    customer_id: customerId,
    status: isEdit.value ? '' : 'DUE',
    limit: 'all',
  }

  isLoadingInvoices.value = true
  try {
    const response = await invoiceService.list(params as never)
    invoiceList.value = [...(response.data as unknown as Invoice[])]

    if (paymentStore.currentPayment.invoice_id) {
      selectedInvoice.value =
        invoiceList.value.find(
          (inv) => inv.id === paymentStore.currentPayment.invoice_id,
        ) ?? null

      if (selectedInvoice.value) {
        paymentStore.currentPayment.maxPayableAmount =
          selectedInvoice.value.due_amount +
          paymentStore.currentPayment.amount

        if (amount.value === 0) {
          amount.value = selectedInvoice.value.due_amount / 100
        }
      }
    }

    if (isEdit.value) {
      invoiceList.value = invoiceList.value.filter(
        (v) =>
          v.due_amount > 0 ||
          v.id === paymentStore.currentPayment.invoice_id,
      )
    }
  } catch {
    invoiceList.value = []
  } finally {
    isLoadingInvoices.value = false
  }
}

function onManualCustomerSelect(): void {
  const params: Record<string, unknown> = {
    userId: paymentStore.currentPayment.customer_id,
  }
  if (route.params.id) {
    params.model_id = route.params.id
  }

  paymentStore.currentPayment.invoice_id = null
  selectedInvoice.value = null
  paymentStore.currentPayment.amount = 0
  paymentStore.getNextNumber(params, true)
}

function onSelectInvoice(id: number): void {
  if (id) {
    selectedInvoice.value =
      invoiceList.value.find((inv) => inv.id === id) ?? null
    if (selectedInvoice.value) {
      amount.value = selectedInvoice.value.due_amount / 100
      paymentStore.currentPayment.maxPayableAmount =
        selectedInvoice.value.due_amount
    }
  }
}

async function submitPaymentData(): Promise<void> {
  isSaving.value = true

  const data = {
    ...cloneDeep(paymentStore.currentPayment),
  }

  try {
    const action = isEdit.value
      ? paymentStore.updatePayment
      : paymentStore.addPayment

    const response = await action(data)
    router.push(`/admin/payments/${response.data.data.id}/view`)
  } catch {
    isSaving.value = false
  }
}

onBeforeUnmount(() => {
  paymentStore.resetCurrentPayment()
  invoiceList.value = []
})
</script>
