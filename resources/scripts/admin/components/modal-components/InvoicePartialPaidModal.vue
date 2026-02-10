<template>
  <BaseModal :show="modalActive" @close="onCancel" @open="onOpen">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-gray-500 cursor-pointer"
          @click="onCancel"
        />
      </div>
    </template>

    <form @submit.prevent="submitForm">
      <div class="p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('invoices.partial_paid_amount')"
            :error="v$.paidAmount.$error && v$.paidAmount.$errors[0].$message"
            required
          >
            <BaseMoney
              v-model="paidAmount"
              :currency="invoiceCurrency"
              :invalid="v$.paidAmount.$error"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <div class="z-0 flex justify-end p-4 border-t border-gray-200 border-solid">
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="onCancel"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              name="ArrowDownOnSquareIcon"
              :class="slotProps.class"
            />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@/scripts/stores/modal'
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useVuelidate } from '@vuelidate/core'
import { required, helpers, between } from '@vuelidate/validators'

const { t } = useI18n()
const modalStore = useModalStore()
const invoiceStore = useInvoiceStore()

const isSaving = ref(false)
const paidAmount = ref(0)

const modalActive = computed(() => {
  return (
    modalStore.active &&
    modalStore.componentName === 'InvoicePartialPaidModal'
  )
})

const invoice = computed(() => {
  return modalStore.data?.invoice || null
})

const invoiceCurrency = computed(() => {
  return invoice.value?.currency || null
})

const maxPaidAmount = computed(() => {
  return invoice.value ? invoice.value.total / 100 : 0
})

const rules = computed(() => {
  return {
    paidAmount: {
      required: helpers.withMessage(t('validation.required'), required),
      between: helpers.withMessage(
        t('validation.payment_greater_than_due_amount'),
        between(0, maxPaidAmount.value)
      ),
    },
  }
})

const v$ = useVuelidate(rules, { paidAmount })

function onOpen() {
  const total = invoice.value ? invoice.value.total / 100 : 0
  const due = invoice.value ? invoice.value.due_amount / 100 : total
  const paid = total - due
  paidAmount.value = paid > 0 ? paid : 0
}

async function submitForm() {
  v$.value.$touch()
  if (v$.value.$invalid || !invoice.value) {
    return
  }

  isSaving.value = true

  const paidAmountCents = Math.round(Number(paidAmount.value || 0) * 100)

  try {
    await invoiceStore.updateInvoiceStatus(
      {
        id: invoice.value.id,
        paid_status: 'PARTIALLY_PAID',
        paid_amount: paidAmountCents,
        reset_payments: true,
      },
      {
        message: t('invoices.mark_as_partially_paid_successfully'),
      }
    )

    const updatedDueAmount =
      paidAmountCents >= invoice.value.total
        ? 0
        : invoice.value.total - paidAmountCents

    invoice.value.due_amount = updatedDueAmount
    invoice.value.paid_status =
      updatedDueAmount === 0
        ? 'PAID'
        : updatedDueAmount === invoice.value.total
          ? 'UNPAID'
          : 'PARTIALLY_PAID'
    invoice.value.status =
      invoice.value.paid_status === 'PAID'
        ? 'COMPLETED'
        : invoice.value.viewed
          ? 'VIEWED'
          : invoice.value.sent
            ? 'SENT'
            : 'DRAFT'

    modalStore.refreshData && modalStore.refreshData()
    modalStore.closeModal()
  } finally {
    isSaving.value = false
  }
}

function onCancel() {
  modalStore.closeModal()
  setTimeout(() => {
    v$.value.$reset()
    paidAmount.value = 0
  }, 300)
}
</script>
