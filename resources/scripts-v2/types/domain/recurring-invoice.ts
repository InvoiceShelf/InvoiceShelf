import type { Customer } from './customer'
import type { User } from './user'
import type { Company } from './company'
import type { Currency } from './currency'
import type { Tax } from './tax'
import type { Invoice, InvoiceItem } from './invoice'
import type { CustomFieldValue } from './custom-field'
import type { DiscountType } from './invoice'

export enum RecurringInvoiceStatus {
  ACTIVE = 'ACTIVE',
  ON_HOLD = 'ON_HOLD',
  COMPLETED = 'COMPLETED',
}

export enum RecurringInvoiceLimitBy {
  NONE = 'NONE',
  COUNT = 'COUNT',
  DATE = 'DATE',
}

export interface RecurringInvoice {
  id: number
  starts_at: string
  formatted_starts_at: string
  formatted_created_at: string
  formatted_next_invoice_at: string
  formatted_limit_date: string
  send_automatically: boolean
  customer_id: number
  company_id: number
  creator_id: number
  status: RecurringInvoiceStatus
  next_invoice_at: string
  frequency: string
  limit_by: RecurringInvoiceLimitBy
  limit_count: number | null
  limit_date: string | null
  exchange_rate: number
  tax_per_item: string | null
  tax_included: boolean | null
  discount_per_item: string | null
  notes: string | null
  discount_type: DiscountType
  discount: number
  discount_val: number
  sub_total: number
  total: number
  tax: number
  due_amount: number
  template_name: string | null
  sales_tax_type: string | null
  sales_tax_address_type: string | null
  fields?: CustomFieldValue[]
  items?: InvoiceItem[]
  customer?: Customer
  company?: Company
  invoices?: Invoice[]
  taxes?: Tax[]
  creator?: User
  currency?: Currency
}

export interface CreateRecurringInvoicePayload {
  starts_at: string
  frequency: string
  customer_id: number
  send_automatically?: boolean
  limit_by?: RecurringInvoiceLimitBy
  limit_count?: number | null
  limit_date?: string | null
  template_name?: string | null
  notes?: string | null
  discount_type?: DiscountType
  discount?: number
  discount_val?: number
  tax_per_item?: string | null
  tax_included?: boolean | null
  discount_per_item?: string | null
  sales_tax_type?: string | null
  sales_tax_address_type?: string | null
  items: Partial<InvoiceItem>[]
  taxes?: Partial<Tax>[]
  customFields?: CustomFieldValue[]
  fields?: CustomFieldValue[]
}
