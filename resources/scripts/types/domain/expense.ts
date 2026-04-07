import type { Customer } from './customer'
import type { User } from './user'
import type { Company } from './company'
import type { Currency } from './currency'
import type { PaymentMethod } from './payment'
import type { CustomFieldValue } from './custom-field'

export interface ExpenseCategory {
  id: number
  name: string
  description: string | null
  company_id: number
  amount: number | null
  formatted_created_at: string
  company?: Company
}

export interface ReceiptUrl {
  url: string
  type: string
}

export interface ReceiptMeta {
  id: number
  name: string
  file_name: string
  mime_type: string
  size: number
  disk: string
  collection_name: string
}

export interface Expense {
  id: number
  expense_date: string
  expense_number: string | null
  amount: number
  notes: string | null
  customer_id: number | null
  attachment_receipt_url: ReceiptUrl | null
  attachment_receipt: string | null
  attachment_receipt_meta: ReceiptMeta | null
  company_id: number
  expense_category_id: number | null
  creator_id: number
  formatted_expense_date: string
  formatted_created_at: string
  exchange_rate: number
  currency_id: number
  base_amount: number
  payment_method_id: number | null
  customer?: Customer
  expense_category?: ExpenseCategory
  creator?: User
  fields?: CustomFieldValue[]
  company?: Company
  currency?: Currency
  payment_method?: PaymentMethod
}

export interface CreateExpensePayload {
  expense_date: string
  amount: number
  expense_category_id?: number | null
  customer_id?: number | null
  payment_method_id?: number | null
  notes?: string | null
  exchange_rate?: number
  currency_id?: number
  customFields?: CustomFieldValue[]
  fields?: CustomFieldValue[]
}
