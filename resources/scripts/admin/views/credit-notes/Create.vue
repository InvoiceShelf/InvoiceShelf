<template>
  <BasePage class="relative credit-note-create">
    <form action="" @submit.prevent="submitCreditNoteData">
      <BasePageHeader :title="pageTitle" class="mb-5">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('general.home')"
            to="/admin/dashboard"
          />
          <BaseBreadcrumbItem
            :title="$t('credit_notes.credit_note', 2)"
            to="/admin/credit-notes"
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
                ? $t('credit_notes.update_credit_note')
                : $t('credit_notes.save_credit_note')
            }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <BaseCard>
        <BaseInputGrid>
          <BaseInputGroup
            :label="$t('credit_notes.date')"
            :content-loading="isLoadingContent"
            required
            :error="
              v$.currentCreditNote.credit_note_date.$error &&
              v$.currentCreditNote.credit_note_date.$errors[0].$message
            "
          >
            <BaseDatePicker
              v-model="creditNoteStore.currentCreditNote.credit_note_date"
              :content-loading="isLoadingContent"
              :calendar-button="true"
              calendar-button-icon="calendar"
              :invalid="v$.currentCreditNote.credit_note_date.$error"
              @update:modelValue="v$.currentCreditNote.credit_note_date.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('credit_notes.credit_note_number')"
            :content-loading="isLoadingContent"
            required
          >
            <BaseInput
              v-model="creditNoteStore.currentCreditNote.credit_note_number"
              :content-loading="isLoadingContent"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('credit_notes.customer')"
            :error="
              v$.currentCreditNote.customer_id.$error &&
              v$.currentCreditNote.customer_id.$errors[0].$message
            "
            :content-loading="isLoadingContent"
            required
          >
            <BaseCustomerSelectInput
              v-model="creditNoteStore.currentCreditNote.customer_id"
              :content-loading="isLoadingContent"
              v-if="!isLoadingContent"
              :invalid="v$.currentCreditNote.customer_id.$error"
              :placeholder="$t('customers.select_a_customer')"
              show-action
              @update:modelValue="
                selectNewCustomer(creditNoteStore.currentCreditNote.customer_id)
              "
            />
          </BaseInputGroup>

          <BaseInputGroup
            :content-loading="isLoadingContent"
            :label="$t('credit_notes.invoice')"
            :help-text="
              selectedInvoice
                ? `${t('credit_notes.amount_due')}: ${
                    creditNoteStore.currentCreditNote.maxPayableAmount / 100
                  }`
                : ''
            "
          >
            <BaseMultiselect
              v-model="creditNoteStore.currentCreditNote.invoice_id"
              :content-loading="isLoadingContent"
              value-prop="id"
              track-by="invoice_number"
              label="invoice_number"
              :options="invoiceList"
              :loading="isLoadingInvoices"
              :placeholder="$t('invoices.select_invoice')"
              @select="onSelectInvoice"
            >
              <template #singlelabel="{ value }">
                <div class="absolute left-3.5">
                  {{ value.invoice_number }} ({{
                    utils.formatMoney(value.total, value.customer.currency)
                  }})
                </div>
              </template>

              <template #option="{ option }">
                {{ option.invoice_number }} ({{
                  utils.formatMoney(option.total, option.customer.currency)
                }})
              </template>
            </BaseMultiselect>
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('credit_notes.amount')"
            :content-loading="isLoadingContent"
            :error="
              v$.currentCreditNote.amount.$error &&
              v$.currentCreditNote.amount.$errors[0].$message
            "
            required
          >
            <div class="relative w-full">
              <BaseMoney
                :key="creditNoteStore.currentCreditNote.currency"
                v-model="amount"
                :currency="creditNoteStore.currentCreditNote.currency"
                :content-loading="isLoadingContent"
                :invalid="v$.currentCreditNote.amount.$error"
                @update:modelValue="v$.currentCreditNote.amount.$touch()"
              />
            </div>
          </BaseInputGroup>

          <ExchangeRateConverter
            :store="creditNoteStore"
            store-prop="currentCreditNote"
            :v="v$.currentCreditNote"
            :is-loading="isLoadingContent"
            :is-edit="isEdit"
            :customer-currency="creditNoteStore.currentCreditNote.currency_id"
          />
        </BaseInputGrid>

        <!-- Credit note Custom Fields -->
        <CreditNoteCustomFields
          type="Credit Note"
          :is-edit="isEdit"
          :is-loading="isLoadingContent"
          :store="creditNoteStore"
          store-prop="currentCreditNote"
          :custom-field-scope="creditNoteValidationScope"
          class="mt-6"
        />

        <!-- Credit Note Note field -->
        <div class="relative mt-6">
          <div
            class="
              z-20
              float-right
              text-sm
              font-semibold
              leading-5
              text-primary-400
            "
          >
            <SelectNotePopup type="Credit Note" @select="onSelectNote" />
          </div>

          <label class="mb-4 text-sm font-medium text-gray-800">
            {{ $t('credit_notes.note') }}
          </label>

          <BaseCustomInput
            v-model="creditNoteStore.currentCreditNote.notes"
            :content-loading="isLoadingContent"
            :fields="CreditNoteFields"
            class="mt-1"
          />
        </div>

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
            isEdit ? $t('credit_notes.update_credit_note') : $t('credit_notes.save_credit_note')
          }}
        </BaseButton>
      </BaseCard>
    </form>
  </BasePage>
</template>

<script setup>
import ExchangeRateConverter from '@/scripts/admin/components/estimate-invoice-common/ExchangeRateConverter.vue'

import {
  ref,
  reactive,
  computed,
  inject,
  watchEffect,
  onBeforeUnmount,
} from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  required,
  numeric,
  helpers,
  between,
  requiredIf,
  decimal,
} from '@vuelidate/validators'

import useVuelidate from '@vuelidate/core'
import { useCustomerStore } from '@/scripts/admin/stores/customer'
import { useCreditNoteStore } from '@/scripts/admin/stores/credit-note'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useCustomFieldStore } from '@/scripts/admin/stores/custom-field'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useModalStore } from '@/scripts/stores/modal'
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useGlobalStore } from '@/scripts/admin/stores/global'

import SelectNotePopup from '@/scripts/admin/components/SelectNotePopup.vue'
import CreditNoteCustomFields from '@/scripts/admin/components/custom-fields/CreateCustomFields.vue'

const route = useRoute()
const router = useRouter()

const creditNoteStore = useCreditNoteStore()
const notificationStore = useNotificationStore()
const customerStore = useCustomerStore()
const customFieldStore = useCustomFieldStore()
const companyStore = useCompanyStore()
const modalStore = useModalStore()
const invoiceStore = useInvoiceStore()
const globalStore = useGlobalStore()

const utils = inject('utils')
const { t } = useI18n()

let isSaving = ref(false)
let isLoadingInvoices = ref(false)
let invoiceList = ref([])
const selectedInvoice = ref(null)

const creditNoteValidationScope = 'newEstimate'

const CreditNoteFields = reactive([
  'customer',
  'company',
  'customerCustom',
  'creditNote',
  'creditNoteCustom',
])

const amount = computed({
  get: () => creditNoteStore.currentCreditNote.amount / 100,
  set: (value) => {
    creditNoteStore.currentCreditNote.amount = Math.round(value * 100)
  },
})

const isLoadingContent = computed(() => creditNoteStore.isFetchingInitialData)

const isEdit = computed(() => route.name === 'credit-notes.edit')

const pageTitle = computed(() => {
  if (isEdit.value) {
    return t('credit_notes.edit_credit_note')
  }
  return t('credit_notes.new_credit_note')
})

const rules = computed(() => {
  return {
    currentCreditNote: {
      customer_id: {
        required: helpers.withMessage(t('validation.required'), required),
      },
      credit_note_date: {
        required: helpers.withMessage(t('validation.required'), required),
      },
      amount: {
        required: helpers.withMessage(t('validation.required'), required),
        between: helpers.withMessage(
          t('validation.payment_greater_than_due_amount'),
          between(0, creditNoteStore.currentCreditNote.maxPayableAmount)
        ),
      },
      exchange_rate: {
        required: requiredIf(function () {
          helpers.withMessage(t('validation.required'), required)
          return creditNoteStore.showExchangeRate
        }),
        decimal: helpers.withMessage(
          t('validation.valid_exchange_rate'),
          decimal
        ),
      },
    },
  }
})

const v$ = useVuelidate(rules, creditNoteStore, {
  $scope: creditNoteValidationScope,
})

watchEffect(() => {
  // fetch customer and its invoices
  creditNoteStore.currentCreditNote.customer_id
    ? onCustomerChange(creditNoteStore.currentCreditNote.customer_id)
    : ''
  if (route.query.customer) {
    creditNoteStore.currentCreditNote.customer_id = route.query.customer
  }
})

// Reset State on Create
creditNoteStore.resetCurrentCreditNote()

if (route.query.customer) {
  creditNoteStore.currentCreditNote.customer_id = route.query.customer
}

creditNoteStore.fetchCreditNoteInitialData(isEdit.value)

if (route.params.id && !isEdit.value) {
  setInvoiceFromUrl()
}

function onSelectNote(data) {
  creditNoteStore.currentCreditNote.notes = '' + data.notes
}

async function setInvoiceFromUrl() {
  let res = await invoiceStore.fetchInvoice(route?.params?.id)

  creditNoteStore.currentCreditNote.customer_id = res.data.data.customer.id
  creditNoteStore.currentCreditNote.invoice_id = res.data.data.id
}

async function onSelectInvoice(id) {
  if (id) {
    selectedInvoice.value = invoiceList.value.find((inv) => inv.id === id)

    amount.value = selectedInvoice.value.due_amount / 100
    creditNoteStore.currentCreditNote.maxPayableAmount =
      selectedInvoice.value.due_amount
  }
}

function onCustomerChange(customer_id) {
  if (customer_id) {
    let data = {
      customer_id: customer_id,
      status: 'DUE',
      limit: 'all',
    }

    if (isEdit.value) {
      data.status = ''
    }

    isLoadingInvoices.value = true

    Promise.all([
      invoiceStore.fetchInvoices(data),
      customerStore.fetchCustomer(customer_id),
    ])
      .then(async ([res1, res2]) => {
        if (res1) {
          invoiceList.value = [...res1.data.data]
        }

        if (res2 && res2.data) {
          creditNoteStore.currentCreditNote.selectedCustomer = res2.data.data
          creditNoteStore.currentCreditNote.customer = res2.data.data
          creditNoteStore.currentCreditNote.currency = res2.data.data.currency
          if(isEdit.value && !customerStore.editCustomer && creditNoteStore.currentCreditNote.customer_id) {
            customerStore.editCustomer = res2.data.data
          }
        }

        if (creditNoteStore.currentCreditNote.invoice_id) {
          selectedInvoice.value = invoiceList.value.find(
            (inv) => inv.id === creditNoteStore.currentCreditNote.invoice_id
          )

          creditNoteStore.currentCreditNote.maxPayableAmount =
            selectedInvoice.value.due_amount +
            creditNoteStore.currentCreditNote.amount

          if (amount.value === 0) {
            amount.value = selectedInvoice.value.due_amount / 100
          }
        }

        if (isEdit.value) {
          // remove all invoices that are paid except currently selected invoice
          invoiceList.value = invoiceList.value.filter((v) => {
            return (
              v.due_amount > 0 || v.id == creditNoteStore.currentCreditNote.invoice_id
            )
          })
        }

        isLoadingInvoices.value = false
      })
      .catch((error) => {
        isLoadingInvoices.value = false
        console.error(error, 'error')
      })
  }
}

onBeforeUnmount(() => {
  creditNoteStore.resetCurrentCreditNote()
  invoiceList.value = []
  customerStore.editCustomer = null
})

async function submitCreditNoteData() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return false
  }

  isSaving.value = true

  let data = {
    ...creditNoteStore.currentCreditNote,
  }

  let response = null

  try {
    const action = isEdit.value
      ? creditNoteStore.updateCreditNote
      : creditNoteStore.addCreditNote

    response = await action(data)

    router.push(`/admin/credit-notes/${response.data.data.id}/view`)
  } catch (err) {
    isSaving.value = false
  }
}

function selectNewCustomer(id) {
  let params = {
    userId: id,
  }

  if (route.params.id) params.model_id = route.params.id

  creditNoteStore.currentCreditNote.invoice_id = selectedInvoice.value = null
  creditNoteStore.currentCreditNote.amount = 0
  invoiceList.value = []
  creditNoteStore.getNextNumber(params, true)
}
</script>
