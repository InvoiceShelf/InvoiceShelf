<template>
  <BasePage class="relative">
    <form action="" @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle" class="mb-5">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem
            :title="$t('general.home')"
            to="/admin/dashboard"
          />
          <BaseBreadcrumbItem
            :title="$t('expenses.expense', 2)"
            to="/admin/expenses"
          />
          <BaseBreadcrumbItem :title="pageTitle" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <BaseButton
            v-if="isEdit && expenseStore.currentExpense.attachment_receipt_url"
            :href="receiptDownloadUrl"
            tag="a"
            variant="primary-outline"
            type="button"
            class="mr-2"
          >
            <template #left="slotProps">
              <BaseIcon name="DownloadIcon" :class="slotProps.class" />
            </template>
            {{ $t('expenses.download_receipt') }}
          </BaseButton>

          <div class="hidden md:block">
            <BaseButton
              :loading="isSaving"
              :content-loading="isFetchingInitialData"
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
              {{
                isEdit
                  ? $t('expenses.update_expense')
                  : $t('expenses.save_expense')
              }}
            </BaseButton>
          </div>
        </template>
      </BasePageHeader>

      <BaseCard>
        <BaseInputGrid>
          <!-- Category -->
          <BaseInputGroup
            :label="$t('expenses.category')"
            :content-loading="isFetchingInitialData"
            required
          >
            <BaseMultiselect
              v-if="!isFetchingInitialData"
              v-model="expenseStore.currentExpense.expense_category_id"
              :content-loading="isFetchingInitialData"
              value-prop="id"
              label="name"
              track-by="id"
              :options="searchCategory"
              :filter-results="false"
              resolve-on-load
              :delay="500"
              searchable
              :placeholder="$t('expenses.categories.select_a_category')"
            />
          </BaseInputGroup>

          <!-- Expense Date -->
          <BaseInputGroup
            :label="$t('expenses.expense_date')"
            :content-loading="isFetchingInitialData"
            required
          >
            <BaseDatePicker
              v-model="expenseStore.currentExpense.expense_date"
              :content-loading="isFetchingInitialData"
              :calendar-button="true"
            />
          </BaseInputGroup>

          <!-- Expense Number -->
          <BaseInputGroup
            :label="$t('expenses.expense_number')"
            :content-loading="isFetchingInitialData"
          >
            <BaseInput
              v-model="expenseStore.currentExpense.expense_number"
              :content-loading="isFetchingInitialData"
              type="text"
              name="expense_number"
              :placeholder="$t('expenses.expense_number_placeholder')"
            />
          </BaseInputGroup>

          <!-- Amount -->
          <BaseInputGroup
            :label="$t('expenses.amount')"
            :content-loading="isFetchingInitialData"
            required
          >
            <BaseMoney
              :key="String(expenseStore.currentExpense.selectedCurrency)"
              v-model="amountData"
              class="focus:border focus:border-solid focus:border-primary-500"
              :currency="expenseStore.currentExpense.selectedCurrency"
            />
          </BaseInputGroup>

          <!-- Currency -->
          <BaseInputGroup
            :label="$t('expenses.currency')"
            :content-loading="isFetchingInitialData"
            required
          >
            <BaseMultiselect
              v-model="expenseStore.currentExpense.currency_id"
              value-prop="id"
              label="name"
              track-by="name"
              :content-loading="isFetchingInitialData"
              :options="currencies"
              searchable
              :can-deselect="false"
              :placeholder="$t('customers.select_currency')"
              class="w-full"
              @update:model-value="onCurrencyChange"
            />
          </BaseInputGroup>

          <!-- Customer -->
          <BaseInputGroup
            :content-loading="isFetchingInitialData"
            :label="$t('expenses.customer')"
          >
            <BaseMultiselect
              v-if="!isFetchingInitialData"
              v-model="expenseStore.currentExpense.customer_id"
              :content-loading="isFetchingInitialData"
              value-prop="id"
              label="name"
              track-by="id"
              :options="searchCustomer"
              :filter-results="false"
              resolve-on-load
              :delay="500"
              searchable
              :placeholder="$t('customers.select_a_customer')"
            />
          </BaseInputGroup>

          <!-- Payment Mode -->
          <BaseInputGroup
            :content-loading="isFetchingInitialData"
            :label="$t('payments.payment_mode')"
          >
            <BaseMultiselect
              v-model="expenseStore.currentExpense.payment_method_id"
              :content-loading="isFetchingInitialData"
              label="name"
              value-prop="id"
              track-by="name"
              :options="expenseStore.paymentModes"
              :placeholder="$t('payments.select_payment_mode')"
              searchable
            />
          </BaseInputGroup>
        </BaseInputGrid>

        <BaseInputGrid class="mt-4">
          <!-- Notes -->
          <BaseInputGroup
            :content-loading="isFetchingInitialData"
            :label="$t('expenses.note')"
          >
            <BaseTextarea
              v-model="expenseStore.currentExpense.notes"
              :content-loading="isFetchingInitialData"
              :row="4"
              rows="4"
            />
          </BaseInputGroup>

          <!-- Receipt -->
          <BaseInputGroup :label="$t('expenses.receipt')">
            <BaseFileUploader
              v-model="expenseStore.currentExpense.receiptFiles"
              accept="image/*,.doc,.docx,.pdf,.csv,.xlsx,.xls"
              @change="onFileInputChange"
              @remove="onFileInputRemove"
            />
          </BaseInputGroup>

          <!-- Mobile Save Button -->
          <div class="block md:hidden">
            <BaseButton
              :loading="isSaving"
              :tabindex="6"
              variant="primary"
              type="submit"
              class="flex justify-center w-full"
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
                  ? $t('expenses.update_expense')
                  : $t('expenses.save_expense')
              }}
            </BaseButton>
          </div>
        </BaseInputGrid>
      </BaseCard>
    </form>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, computed, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useExpenseStore } from '../store'
import type { ExpenseCategory } from '../../../../types/domain/expense'
import type { Customer } from '../../../../types/domain/customer'
import type { Currency } from '../../../../types/domain/currency'

interface Props {
  currencies?: Currency[]
  companyCurrency?: Currency | null
}

const props = withDefaults(defineProps<Props>(), {
  currencies: () => [],
  companyCurrency: null,
})

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const expenseStore = useExpenseStore()

const isSaving = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(false)
const isAttachmentReceiptRemoved = ref<boolean>(false)

const amountData = computed<number>({
  get: () => expenseStore.currentExpense.amount / 100,
  set: (value: number) => {
    expenseStore.currentExpense.amount = Math.round(value * 100)
  },
})

const isEdit = computed<boolean>(() => route.name === 'expenses.edit')

const pageTitle = computed<string>(() =>
  isEdit.value ? t('expenses.edit_expense') : t('expenses.new_expense'),
)

const receiptDownloadUrl = computed<string>(() =>
  isEdit.value ? `/reports/expenses/${route.params.id}/download-receipt` : '',
)

// Initialize
expenseStore.resetCurrentExpenseData()
loadData()

function onFileInputChange(_fileName: string, file: File): void {
  expenseStore.currentExpense.attachment_receipt = file
}

function onFileInputRemove(): void {
  expenseStore.currentExpense.attachment_receipt = null
  isAttachmentReceiptRemoved.value = true
}

function onCurrencyChange(currencyId: number): void {
  const found = props.currencies.find((c) => c.id === currencyId)
  expenseStore.currentExpense.selectedCurrency = found ?? null
}

async function searchCategory(
  search: string,
): Promise<ExpenseCategory[]> {
  const { expenseService } = await import(
    '../../../../api/services/expense.service'
  )
  const res = await expenseService.listCategories({ search })
  return res.data
}

async function searchCustomer(search: string): Promise<Customer[]> {
  const { customerService } = await import(
    '../../../../api/services/customer.service'
  )
  const res = await customerService.list({ search })
  return res.data
}

async function loadData(): Promise<void> {
  if (!isEdit.value && props.companyCurrency) {
    expenseStore.currentExpense.currency_id = props.companyCurrency.id
    expenseStore.currentExpense.selectedCurrency = props.companyCurrency
  }

  isFetchingInitialData.value = true
  await expenseStore.fetchPaymentModes({ limit: 'all' })

  if (isEdit.value) {
    await expenseStore.fetchExpense(Number(route.params.id))
    if (expenseStore.currentExpense.selectedCurrency) {
      expenseStore.currentExpense.currency_id =
        expenseStore.currentExpense.selectedCurrency.id
    }
  } else if (route.query.customer) {
    expenseStore.currentExpense.customer_id = Number(route.query.customer)
  }

  isFetchingInitialData.value = false
}

async function submitForm(): Promise<void> {
  isSaving.value = true

  const formData: Record<string, unknown> = {
    ...expenseStore.currentExpense,
    expense_number: expenseStore.currentExpense.expense_number || '',
  }

  try {
    if (isEdit.value) {
      await expenseStore.updateExpense({
        id: Number(route.params.id),
        data: formData,
        isAttachmentReceiptRemoved: isAttachmentReceiptRemoved.value,
      })
    } else {
      await expenseStore.addExpense(formData)
    }
    isSaving.value = false
    expenseStore.currentExpense.attachment_receipt = null
    isAttachmentReceiptRemoved.value = false
    router.push('/admin/expenses')
  } catch {
    isSaving.value = false
  }
}

onBeforeUnmount(() => {
  expenseStore.resetCurrentExpenseData()
})
</script>
