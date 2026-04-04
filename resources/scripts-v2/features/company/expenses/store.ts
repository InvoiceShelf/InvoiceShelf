import { defineStore } from 'pinia'
import { expenseService } from '../../../api/services/expense.service'
import type {
  ExpenseListParams,
  ExpenseListResponse,
} from '../../../api/services/expense.service'
import type {
  Expense,
  ExpenseCategory,
  CreateExpensePayload,
} from '../../../types/domain/expense'
import type { PaymentMethod } from '../../../types/domain/payment'
import type { Currency } from '../../../types/domain/currency'
import type { CustomFieldValue } from '../../../types/domain/custom-field'

// ----------------------------------------------------------------
// Stub factories
// ----------------------------------------------------------------

export interface ReceiptFile {
  image?: string
  type?: string
  name?: string
}

export interface ExpenseFormData {
  id: number | null
  expense_date: string
  expense_number: string
  amount: number
  notes: string | null
  customer_id: number | null
  expense_category_id: number | null
  payment_method_id: number | null
  currency_id: number | null
  exchange_rate: number | null
  selectedCurrency: Currency | null
  attachment_receipt: File | null
  attachment_receipt_url: string | null
  receiptFiles: ReceiptFile[]
  customFields: CustomFieldValue[]
  fields: CustomFieldValue[]
}

function createExpenseStub(): ExpenseFormData {
  return {
    id: null,
    expense_date: '',
    expense_number: '',
    amount: 0,
    notes: '',
    customer_id: null,
    expense_category_id: null,
    payment_method_id: null,
    currency_id: null,
    exchange_rate: null,
    selectedCurrency: null,
    attachment_receipt: null,
    attachment_receipt_url: null,
    receiptFiles: [],
    customFields: [],
    fields: [],
  }
}

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

export interface ExpenseState {
  expenses: Expense[]
  totalExpenses: number
  selectAllField: boolean
  selectedExpenses: number[]
  paymentModes: PaymentMethod[]
  showExchangeRate: boolean
  currentExpense: ExpenseFormData
}

export const useExpenseStore = defineStore('expense', {
  state: (): ExpenseState => ({
    expenses: [],
    totalExpenses: 0,
    selectAllField: false,
    selectedExpenses: [],
    paymentModes: [],
    showExchangeRate: false,
    currentExpense: createExpenseStub(),
  }),

  getters: {
    getCurrentExpense: (state): ExpenseFormData => state.currentExpense,
    getSelectedExpenses: (state): number[] => state.selectedExpenses,
  },

  actions: {
    resetCurrentExpenseData(): void {
      this.currentExpense = createExpenseStub()
    },

    async fetchExpenses(
      params: ExpenseListParams,
    ): Promise<{ data: ExpenseListResponse }> {
      const response = await expenseService.list(params)
      this.expenses = response.data
      this.totalExpenses = response.meta.expense_total_count
      return { data: response }
    },

    async fetchExpense(id: number): Promise<{ data: { data: Expense } }> {
      const response = await expenseService.get(id)
      const data = response.data

      Object.assign(this.currentExpense, data)
      this.currentExpense.selectedCurrency = data.currency ?? null
      this.currentExpense.attachment_receipt = null

      if (data.attachment_receipt_url) {
        if (
          data.attachment_receipt_meta?.mime_type?.startsWith('image/')
        ) {
          this.currentExpense.receiptFiles = [
            {
              image: `/reports/expenses/${id}/receipt?${data.attachment_receipt_meta.uuid}`,
            },
          ]
        } else if (data.attachment_receipt_meta) {
          this.currentExpense.receiptFiles = [
            {
              type: 'document',
              name: data.attachment_receipt_meta.file_name,
            },
          ]
        }
      } else {
        this.currentExpense.receiptFiles = []
      }

      return { data: response }
    },

    async addExpense(
      data: Record<string, unknown>,
    ): Promise<{ data: { data: Expense } }> {
      const formData = toFormData(data)
      const response = await expenseService.create(formData)
      this.expenses.push(response.data)
      return { data: response }
    },

    async updateExpense(params: {
      id: number
      data: Record<string, unknown>
      isAttachmentReceiptRemoved: boolean
    }): Promise<{ data: { data: Expense } }> {
      const formData = toFormData(params.data)
      formData.append('_method', 'PUT')
      formData.append(
        'is_attachment_receipt_removed',
        String(params.isAttachmentReceiptRemoved),
      )

      const response = await expenseService.update(params.id, formData)
      const pos = this.expenses.findIndex((e) => e.id === response.data.id)
      if (pos !== -1) {
        this.expenses[pos] = response.data
      }
      return { data: response }
    },

    async deleteExpense(
      payload: { ids: number[] },
    ): Promise<{ data: { success: boolean } }> {
      const response = await expenseService.delete(payload)
      const id = payload.ids[0]
      const index = this.expenses.findIndex((e) => e.id === id)
      if (index !== -1) {
        this.expenses.splice(index, 1)
      }
      return { data: response }
    },

    async deleteMultipleExpenses(): Promise<{ data: { success: boolean } }> {
      const response = await expenseService.delete({
        ids: this.selectedExpenses,
      })
      this.selectedExpenses.forEach((expenseId) => {
        const index = this.expenses.findIndex((e) => e.id === expenseId)
        if (index !== -1) {
          this.expenses.splice(index, 1)
        }
      })
      this.selectedExpenses = []
      return { data: response }
    },

    async fetchPaymentModes(
      params?: Record<string, unknown>,
    ): Promise<{ data: { data: PaymentMethod[] } }> {
      const { paymentService } = await import(
        '../../../api/services/payment.service'
      )
      const response = await paymentService.listMethods(params as never)
      this.paymentModes = response.data
      return { data: response }
    },

    setSelectAllState(data: boolean): void {
      this.selectAllField = data
    },

    selectExpense(data: number[]): void {
      this.selectedExpenses = data
      this.selectAllField =
        this.selectedExpenses.length === this.expenses.length
    },

    selectAllExpenses(): void {
      if (this.selectedExpenses.length === this.expenses.length) {
        this.selectedExpenses = []
        this.selectAllField = false
      } else {
        this.selectedExpenses = this.expenses.map((e) => e.id)
        this.selectAllField = true
      }
    },
  },
})

/**
 * Convert an object to FormData, handling nested properties and files.
 */
function toFormData(obj: Record<string, unknown>): FormData {
  const formData = new FormData()

  for (const key of Object.keys(obj)) {
    const value = obj[key]
    if (value === null || value === undefined) {
      continue
    }
    if (value instanceof File) {
      formData.append(key, value)
    } else if (typeof value === 'object' && !(value instanceof Blob)) {
      formData.append(key, JSON.stringify(value))
    } else {
      formData.append(key, String(value))
    }
  }

  return formData
}

export type ExpenseStore = ReturnType<typeof useExpenseStore>
