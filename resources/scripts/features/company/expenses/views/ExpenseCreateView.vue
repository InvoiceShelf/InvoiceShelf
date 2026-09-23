<template>
  <BasePage class="relative">
    <form action="" class="flex flex-col gap-4 md:gap-5" @submit.prevent="submitForm">
      <!-- On phones Save moves to the bottom bar, still submitting this form -->
      <BasePageHeader :title="pageTitle" phone-actions="bar">
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
            :loading="isDownloadingReceipt"
            variant="white"
            type="button"
            @click="downloadReceipt"
          >
            <template #left="slotProps">
              <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
            </template>
            {{ $t('expenses.download_receipt') }}
          </BaseButton>

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
        </template>
      </BasePageHeader>

      <BaseCard container-class="p-4 md:p-5">
        <BaseInputGrid>
          <!-- Category -->
          <BaseInputGroup
            :label="$t('expenses.category')"
            :content-loading="isFetchingInitialData"
            required
          >
            <!-- A new company has no categories: one can be added from here -->
            <BaseMultiselect
              v-if="!isFetchingInitialData"
              :key="categoryReloadKey"
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
            >
              <template v-if="userStore.hasAbilities(ABILITIES.VIEW_EXPENSE)" #action>
                <BaseSelectAction @click="addCategory">
                  <BaseIcon
                    name="PlusIcon"
                    class="h-4 me-2 -ms-2 text-center text-primary-400"
                  />
                  {{ $t('settings.expense_category.add_new_category') }}
                </BaseSelectAction>
              </template>
            </BaseMultiselect>
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
              :options="globalStore.currencies"
              searchable
              :can-deselect="false"
              :placeholder="$t('customers.select_currency')"
              class="w-full"
              @update:model-value="onCurrencyChange"
            />
          </BaseInputGroup>

          <!-- Exchange Rate -->
          <ExchangeRateConverter
            :store="expenseStore"
            store-prop="currentExpense"
            :v="{ exchange_rate: { $error: false, $errors: [], $touch: () => {} } }"
            :is-loading="isFetchingInitialData"
            :is-edit="isEdit"
            :customer-currency="expenseStore.currentExpense.currency_id"
          />

          <!-- Customer -->
          <BaseInputGroup
            :content-loading="isFetchingInitialData"
            :label="$t('expenses.customer')"
          >
            <BaseCustomerSelectInput
              v-if="!isFetchingInitialData"
              v-model="expenseStore.currentExpense.customer_id"
              can-deselect
              show-action
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

          <!-- Custom fields join the form's own grid rather than forming a
               band of their own; they are attributes like the rest. -->
          <CustomFieldInput
            v-for="field in customFields"
            :key="field.id"
            :custom-field-scope="customFieldValidationScope"
            :field="field"
          />
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

        </BaseInputGrid>

        <ExpenseTaxSection
          v-model="expenseStore.currentExpense.taxes"
          :amount="expenseStore.currentExpense.amount"
          :currency="expenseStore.currentExpense.selectedCurrency"
          :is-loading="isFetchingInitialData"
        />
      </BaseCard>
    </form>

    <CategoryModal />
  </BasePage>
</template>

<script setup lang="ts">
import { ref, computed, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useExpenseStore } from '../store'
import { useGlobalStore } from '../../../../stores/global.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import { useUserStore } from '../../../../stores/user.store'
import { useModalStore } from '../../../../stores/modal.store'
import { ABILITIES } from '../../../../config/abilities'
import { handleApiError, getErrorTranslationKey } from '@/scripts/utils/error-handling'
import { formatDate } from '@/scripts/utils/format-date'
import CategoryModal from '@/scripts/features/company/settings/components/CategoryModal.vue'
import { downloadDocument } from '@/scripts/utils/documents'
import { ExchangeRateConverter } from '../../../shared/document-form'
import ExpenseTaxSection from '../components/ExpenseTaxSection.vue'
import CustomFieldInput from '@/scripts/features/shared/custom-fields/CustomFieldInput.vue'
import { useCustomFields } from '@/scripts/features/shared/custom-fields/use-custom-fields'
import type { ExpenseCategory } from '../../../../types/domain/expense'
import type { Currency } from '../../../../types/domain/currency'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const customFieldValidationScope = 'customFields'

const expenseStore = useExpenseStore()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const userStore = useUserStore()
const modalStore = useModalStore()

const isSaving = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(false)
const isAttachmentReceiptRemoved = ref<boolean>(false)
const isDownloadingReceipt = ref<boolean>(false)

const amountData = computed<number>({
  get: () => expenseStore.currentExpense.amount / 100,
  set: (value: number) => {
    expenseStore.currentExpense.amount = Math.round(value * 100)
  },
})

const isEdit = computed<boolean>(() => route.name === 'expenses.edit')

const customFields = useCustomFields({
  store: expenseStore,
  storeProp: 'currentExpense',
  type: 'Expense',
  isEdit: () => isEdit.value,
})

const pageTitle = computed<string>(() =>
  isEdit.value ? t('expenses.edit_expense') : t('expenses.new_expense'),
)

const receiptPath = computed<string>(() =>
  isEdit.value ? `/reports/expenses/${route.params.id}/download-receipt` : '',
)

/**
 * The receipt used to be a plain link, which only reaches a server the page
 * shares an origin with. Fetching it through the API client works from a
 * client too, and a missing file now says so instead of saving the error.
 */
async function downloadReceipt(): Promise<void> {
  if (!receiptPath.value) {
    return
  }

  isDownloadingReceipt.value = true

  try {
    await downloadDocument(receiptPath.value, {}, 'receipt')
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: t('pdf.download_failed'),
    })
  } finally {
    isDownloadingReceipt.value = false
  }
}

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
  const found = globalStore.currencies.find((c: Currency) => c.id === currencyId)
  expenseStore.currentExpense.selectedCurrency = found ?? null
}

// A category added from the select, kept in its options until they include it
const createdCategory = ref<ExpenseCategory | null>(null)
const categoryReloadKey = ref<number>(0)

async function searchCategory(
  search: string,
): Promise<ExpenseCategory[]> {
  const { expenseService } = await import(
    '../../../../api/services/expense.service'
  )
  const res = await expenseService.listCategories({ search })
  const categories = res.data ?? []

  if (createdCategory.value && !categories.some((c) => c.id === createdCategory.value?.id)) {
    categories.unshift(createdCategory.value)
  }

  return categories
}

function addCategory(): void {
  modalStore.openModal({
    title: t('settings.expense_category.add_category'),
    componentName: 'CategoryModal',
    size: 'sm',
    refreshData: (category: unknown) => {
      const saved = category as ExpenseCategory | undefined

      if (saved?.id) {
        createdCategory.value = saved
        categoryReloadKey.value++
        expenseStore.currentExpense.expense_category_id = saved.id
      }
    },
  })
}

async function loadData(): Promise<void> {
  await globalStore.fetchCurrencies()

  const companyCurrency = companyStore.selectedCompanyCurrency
  if (!isEdit.value && companyCurrency) {
    expenseStore.currentExpense.currency_id = companyCurrency.id
    expenseStore.currentExpense.selectedCurrency = companyCurrency
  }

  isFetchingInitialData.value = true
  await expenseStore.fetchPaymentModes({ limit: 'all' })

  if (isEdit.value) {
    await expenseStore.fetchExpense(Number(route.params.id))
    if (expenseStore.currentExpense.selectedCurrency) {
      expenseStore.currentExpense.currency_id =
        expenseStore.currentExpense.selectedCurrency.id
    }
  } else {
    // A new expense is dated today, like a new invoice or payment
    expenseStore.currentExpense.expense_date ||= formatDate(new Date())

    if (route.query.customer) {
      expenseStore.currentExpense.customer_id = Number(route.query.customer)
    }
  }

  isFetchingInitialData.value = false
}

async function submitForm(): Promise<void> {
  isSaving.value = true

  const formData: Record<string, unknown> = {
    ...expenseStore.currentExpense,
    expense_number: expenseStore.currentExpense.expense_number || '',
    taxes: expenseStore.currentExpense.taxes.map((tax) => ({
      tax_type_id: tax.tax_type_id,
      amount: tax.amount,
    })),
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
  } catch (error) {
    isSaving.value = false

    // Say why it did not save instead of leaving the form as it was
    const normalized = handleApiError(error)
    const translationKey = getErrorTranslationKey(normalized.message)
    notificationStore.showNotification({
      type: 'error',
      message: translationKey ? t(translationKey) : normalized.message,
    })
  }
}

onBeforeUnmount(() => {
  expenseStore.resetCurrentExpenseData()
})
</script>
