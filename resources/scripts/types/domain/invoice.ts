import type { Customer } from './customer'
import type { User } from './user'
import type { Company } from './company'
import type { Currency } from './currency'
import type { Tax } from './tax'
import type { CustomFieldValue } from './custom-field'

export enum InvoiceStatus {
  DRAFT = 'DRAFT',
  SENT = 'SENT',
  VIEWED = 'VIEWED',
  COMPLETED = 'COMPLETED',
}

export enum InvoicePaidStatus {
  UNPAID = 'UNPAID',
  PARTIALLY_PAID = 'PARTIALLY_PAID',
  PAID = 'PAID',
}

export type DiscountType = 'fixed' | 'percentage'

export interface InvoiceItem {
  id: number | string
  name: string
  description: string | null
  discount_type: DiscountType
  price: number
  quantity: number
  unit_name: string | null
  discount: number
  discount_val: number
  tax: number
  total: number
  invoice_id: number | null
  item_id: number | null
  company_id: number
  base_price: number
  exchange_rate: number
  base_discount_val: number
  base_tax: number
  base_total: number
  recurring_invoice_id: number | null
  taxes?: Tax[]
  fields?: CustomFieldValue[]
}

export interface Invoice {
  id: number
  invoice_date: string
  due_date: string
  invoice_number: string
  reference_number: string | null
  status: InvoiceStatus
  paid_status: InvoicePaidStatus
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
  sent: boolean | null
  viewed: boolean | null
  unique_hash: string
  template_name: string | null
  customer_id: number
  recurring_invoice_id: number | null
  sequence_number: number
  exchange_rate: number
  base_discount_val: number
  base_sub_total: number
  base_total: number
  creator_id: number
  base_tax: number
  base_due_amount: number
  currency_id: number
  formatted_created_at: string
  invoice_pdf_url: string
  formatted_invoice_date: string
  formatted_due_date: string
  allow_edit: boolean
  payment_module_enabled: boolean
  sales_tax_type: string | null
  sales_tax_address_type: string | null
  overdue: boolean | null
  items?: InvoiceItem[]
  customer?: Customer
  creator?: User
  taxes?: Tax[]
  fields?: CustomFieldValue[]
  company?: Company
  currency?: Currency
}

export interface CreateInvoicePayload {
  invoice_date: string
  due_date: string
  invoice_number: string
  reference_number?: string | null
  customer_id: number
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
  items: CreateInvoiceItemPayload[]
  taxes?: Partial<Tax>[]
  customFields?: CustomFieldValue[]
  fields?: CustomFieldValue[]
}

export interface CreateInvoiceItemPayload {
  item_id?: number | null
  name: string
  description?: string | null
  quantity: number
  price: number
  discount_type?: DiscountType
  discount?: number
  discount_val?: number
  tax?: number
  total?: number
  taxes?: Partial<Tax>[]
  unit_name?: string | null
}
