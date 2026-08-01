<template>
  <BaseModal
    :show="modalActive"
    @close="closeModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>

    <form @submit.prevent="submitCreditNote">
      <div class="px-4 md:px-8 py-4 md:py-6 space-y-6">
        <div
          v-if="isFetching"
          class="flex items-center justify-center py-16"
        >
          <LoadingIcon class="h-6 animate-spin text-primary-400" />
        </div>

        <template v-else>
          <div>
            <h6 class="text-sm not-italic font-medium text-heading">
              {{ $t('invoices.credit_note_items') }}
            </h6>

            <div
              class="mt-2 overflow-x-auto rounded-xl border border-line-light bg-surface"
            >
              <table class="min-w-full text-center item-table">
                <colgroup>
                  <col style="width: 48px" />
                  <col style="width: 28%; min-width: 200px" />
                  <col style="width: 12%; min-width: 110px" />
                  <col style="width: 10%; min-width: 90px" />
                  <col style="width: 10%; min-width: 90px" />
                  <col style="width: 10%; min-width: 90px" />
                  <col style="width: 14%; min-width: 130px" />
                  <col style="width: 14%; min-width: 110px" />
                </colgroup>

                <thead class="bg-surface-secondary border-b border-line-light">
                  <tr>
                    <th class="px-4 py-3" />
                    <th
                      class="px-4 py-3 text-sm not-italic font-medium leading-5 text-left text-body"
                    >
                      {{ $t('items.item', 2) }}
                    </th>
                    <th
                      class="px-4 py-3 text-sm not-italic font-medium leading-5 text-right text-body"
                    >
                      {{ $t('invoices.item.price') }}
                    </th>
                    <th
                      class="px-4 py-3 text-sm not-italic font-medium leading-5 text-right text-body"
                    >
                      {{ $t('invoices.credit_note_original_quantity') }}
                    </th>
                    <th
                      class="px-4 py-3 text-sm not-italic font-medium leading-5 text-right text-body"
                    >
                      {{ $t('invoices.credit_note_already_credited') }}
                    </th>
                    <th
                      class="px-4 py-3 text-sm not-italic font-medium leading-5 text-right text-body"
                    >
                      {{ $t('invoices.credit_note_remaining_quantity') }}
                    </th>
                    <th
                      class="px-4 py-3 text-sm not-italic font-medium leading-5 text-right text-body"
                    >
                      {{ $t('invoices.credit_note_quantity_to_credit') }}
                    </th>
                    <th
                      class="px-4 py-3 text-sm not-italic font-medium leading-5 text-right text-body"
                    >
                      {{ $t('invoices.credit_note_amount') }}
                    </th>
                  </tr>
                </thead>

                <tbody>
                  <tr
                    v-for="(row, index) in form.rows"
                    :key="row.id"
                    class="bg-surface border-b border-line-light last:border-b-0"
                  >
                    <td class="px-4 py-4 align-top">
                      <BaseCheckbox
                        v-model="row.selected"
                        :disabled="row.available === 0"
                        @change="onToggleRow(row)"
                      />
                    </td>

                    <td class="px-4 py-4 text-left align-top">
                      <span class="block text-sm font-medium text-heading">
                        {{ row.name }}
                      </span>
                      <span
                        v-if="row.description"
                        class="block mt-0.5 text-xs text-muted"
                      >
                        {{ row.description }}
                      </span>
                      <span
                        v-if="row.available === 0"
                        class="block mt-1 text-xs italic text-subtle"
                      >
                        {{ $t('invoices.credit_note_fully_credited_line') }}
                      </span>
                    </td>

                    <td class="px-4 py-4 text-sm text-right align-top text-body">
                      <BaseFormatMoney :amount="row.price" :currency="currency" />
                    </td>

                    <td class="px-4 py-4 text-sm text-right align-top text-body">
                      {{ formatQuantity(row.invoiced) }}
                      <span v-if="row.unitName" class="text-xs text-muted">
                        {{ row.unitName }}
                      </span>
                    </td>

                    <td class="px-4 py-4 text-sm text-right align-top text-body">
                      {{ formatQuantity(row.credited) }}
                    </td>

                    <td
                      class="px-4 py-4 text-sm font-medium text-right align-top text-heading"
                    >
                      {{ formatQuantity(row.available) }}
                    </td>

                    <td class="px-4 py-4 text-right align-top">
                      <BaseInput
                        v-model="row.quantity"
                        :invalid="!!rowError(index)"
                        :disabled="!row.selected"
                        type="number"
                        small
                        step="0.01"
                        min="0"
                        @input="v$.rows.$touch()"
                      />
                      <span
                        v-if="rowError(index)"
                        class="block mt-1 text-xs text-left text-red-500"
                      >
                        {{ rowError(index) }}
                      </span>
                    </td>

                    <td
                      class="px-4 py-4 text-sm text-right align-top text-heading"
                    >
                      <BaseFormatMoney
                        :amount="rowAmount(row)"
                        :currency="currency"
                      />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <span
              v-if="selectionError"
              class="block mt-2 text-sm text-red-500"
            >
              {{ selectionError }}
            </span>

            <div class="flex justify-end mt-4">
              <div
                class="flex items-center justify-between w-full text-sm sm:w-80"
              >
                <span class="font-medium text-heading">
                  {{ $t('invoices.credit_note_credited_subtotal') }}
                </span>
                <BaseFormatMoney
                  class="font-semibold text-heading"
                  :amount="creditedSubtotal"
                  :currency="currency"
                />
              </div>
            </div>

            <p class="mt-2 text-xs text-right text-muted">
              {{ $t('invoices.credit_note_proportional_note') }}
            </p>
          </div>

          <BaseInputGroup :label="$t('invoices.credit_note_reason')">
            <BaseTextarea
              v-model="form.reason"
              :row="4"
              rows="4"
              maxlength="1000"
              :placeholder="$t('invoices.credit_note_reason_placeholder')"
            />
          </BaseInputGroup>
        </template>
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          @click="closeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton
          :loading="isSaving"
          :disabled="isSaving || isFetching"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              name="ReceiptRefundIcon"
              :class="slotProps.class"
            />
          </template>
          {{ $t('invoices.create_credit_note') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import useVuelidate from '@vuelidate/core'
import { helpers } from '@vuelidate/validators'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { invoiceService } from '@/scripts/api/services/invoice.service'
import { calcItemSubtotal } from '@/scripts/features/shared/document-form/use-document-calculations'
import {
  handleApiError,
  getErrorTranslationKey,
} from '@/scripts/utils/error-handling'
import LoadingIcon from '@/scripts/components/icons/LoadingIcon.vue'
import { useInvoiceStore } from '../store'
import type { Currency } from '@/scripts/types/domain/currency'
import type { CreditNoteItemPayload } from '@/scripts/types/domain/invoice'

/**
 * One line of the invoice being credited. Quantities are kept in integer
 * hundredths so the "is this the last unit?" comparisons never trip over
 * float drift; `quantity` is the raw string the number input hands back.
 */
interface CreditRow {
  id: number
  name: string
  description: string | null
  unitName: string | null
  price: number
  invoiced: number
  credited: number
  available: number
  selected: boolean
  quantity: string
}

interface CreditNoteForm {
  rows: CreditRow[]
  reason: string
}

const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const invoiceStore = useInvoiceStore()
const router = useRouter()
const { t } = useI18n()

const isFetching = ref<boolean>(false)
const isSaving = ref<boolean>(false)
const currency = ref<Currency | null>(null)

const form = reactive<CreditNoteForm>({
  rows: [],
  reason: '',
})

const modalActive = computed<boolean>(() => {
  return modalStore.active && modalStore.componentName === 'CreditNoteModal'
})

/** Quantity as integer hundredths; NaN when the input is not a number. */
function toHundredths(value: unknown): number {
  return Math.round(Number(value) * 100)
}

function formatQuantity(hundredths: number): string {
  return String(hundredths / 100)
}

const rules = computed(() => ({
  rows: {
    atLeastOne: helpers.withMessage(
      t('invoices.credit_note_select_at_least_one_item'),
      (value: CreditRow[]) =>
        value.some((row) => row.selected && toHundredths(row.quantity) > 0),
    ),
    // Only the selected rows are validated: an unchecked line contributes
    // nothing to the payload, so whatever is left in its input is irrelevant.
    $each: helpers.forEach({
      quantity: {
        required: helpers.withMessage(
          t('validation.required'),
          (value: unknown, row: CreditRow) =>
            !row.selected || String(value ?? '').trim() !== '',
        ),
        numeric: helpers.withMessage(
          t('validation.numbers_only'),
          (value: unknown, row: CreditRow) =>
            !row.selected || Number.isFinite(Number(value)),
        ),
        minValue: helpers.withMessage(
          t('validation.qty_must_greater_than_zero'),
          (value: unknown, row: CreditRow) =>
            !row.selected || toHundredths(value) >= 1,
        ),
        maxValue: helpers.withMessage(
          t('invoices.credit_note_quantity_exceeds_remaining'),
          (value: unknown, row: CreditRow) =>
            !row.selected || toHundredths(value) <= row.available,
        ),
      },
    }),
  },
}))

const v$ = useVuelidate(rules, form)

interface ForEachError {
  $message: string
}

/**
 * Per-row message out of `helpers.forEach`, whose results live under
 * `$each.$response` rather than on the validation property itself.
 */
function rowError(index: number): string {
  if (!v$.value.rows.$dirty) {
    return ''
  }

  const collection = v$.value.rows as unknown as {
    $each?: { $response?: { $errors?: Record<string, ForEachError[]>[] } }
  }

  const errors = collection.$each?.$response?.$errors?.[index]?.quantity ?? []

  return errors.length ? String(errors[0].$message) : ''
}

const selectionError = computed<string>(() => {
  if (!v$.value.rows.$dirty) {
    return ''
  }

  const hasSelection = form.rows.some(
    (row) => row.selected && toHundredths(row.quantity) > 0,
  )

  return hasSelection ? '' : t('invoices.credit_note_select_at_least_one_item')
})

/**
 * Line amount before document discount and taxes, which is all the client can
 * honestly show: how those are apportioned is decided by the server.
 */
function rowAmount(row: CreditRow): number {
  if (!row.selected) {
    return 0
  }

  const quantity = Number(row.quantity)

  if (!Number.isFinite(quantity) || quantity <= 0) {
    return 0
  }

  return calcItemSubtotal(row.price, quantity)
}

const creditedSubtotal = computed<number>(() => {
  return form.rows.reduce((sum, row) => sum + rowAmount(row), 0)
})

/**
 * Every line credited in full with nothing credited before is exactly what the
 * server's full-reversal path does, so it is sent as such: no item list at all.
 */
const isFullReversal = computed<boolean>(() => {
  return (
    form.rows.length > 0 &&
    form.rows.every(
      (row) =>
        row.credited === 0 &&
        row.selected &&
        toHundredths(row.quantity) === row.available,
    )
  )
})

function showApiError(err: unknown): void {
  const normalized = handleApiError(err)
  const translationKey = getErrorTranslationKey(normalized.message)

  notificationStore.showNotification({
    type: 'error',
    message: translationKey ? t(translationKey) : normalized.message,
  })
}

/**
 * Read the invoice straight from the API rather than through the invoice
 * store: `fetchInvoice` loads the document into the edit form's state, which
 * would clobber an in-progress edit and rescale its discounts.
 */
async function setInitialData(): Promise<void> {
  resetForm()

  const invoiceId = Number(modalStore.id)

  if (!invoiceId) {
    return
  }

  isFetching.value = true

  try {
    const response = await invoiceService.get(invoiceId)
    const invoice = response.data

    currency.value = invoice.customer?.currency ?? null

    const creditedQuantities = invoice.credited_quantities ?? {}

    form.rows = (invoice.items ?? []).map((item) => {
      const invoiced = toHundredths(item.quantity)
      const credited = toHundredths(creditedQuantities[String(item.id)] ?? 0)
      const available = Math.max(0, invoiced - credited)

      return {
        id: Number(item.id),
        name: item.name,
        description: item.description,
        unitName: item.unit_name,
        price: item.price,
        invoiced,
        credited,
        available,
        selected: available > 0,
        quantity: formatQuantity(available),
      }
    })
  } catch (err: unknown) {
    showApiError(err)
  } finally {
    isFetching.value = false
  }
}

function onToggleRow(row: CreditRow): void {
  if (row.selected && !(toHundredths(row.quantity) > 0)) {
    row.quantity = formatQuantity(row.available)
  }
}

async function submitCreditNote(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  const reason = form.reason.trim() ? form.reason.trim() : null

  isSaving.value = true

  try {
    const items: CreditNoteItemPayload[] = form.rows
      .filter((row) => row.selected && toHundredths(row.quantity) > 0)
      .map((row) => ({
        id: row.id,
        quantity: toHundredths(row.quantity) / 100,
      }))

    const response = await invoiceStore.createCreditNote({
      id: Number(modalStore.id),
      reason,
      ...(isFullReversal.value ? {} : { items }),
    })

    notificationStore.showNotification({
      type: 'success',
      message: t('invoices.credit_note_created'),
    })

    modalStore.refreshData?.()
    closeModal()

    router.push(`/admin/invoices/${response.data.data.id}/view`)
  } catch (err: unknown) {
    showApiError(err)
  } finally {
    isSaving.value = false
  }
}

function resetForm(): void {
  form.rows = []
  form.reason = ''
  currency.value = null
  v$.value.$reset()
}

function closeModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    resetForm()
  }, 300)
}
</script>
