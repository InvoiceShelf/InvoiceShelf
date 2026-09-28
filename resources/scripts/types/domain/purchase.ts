import type { Currency } from './currency'
import type { CustomField, CustomFieldValue } from './custom-field'
import type { CustomFieldItem } from '@/scripts/features/shared/custom-fields/use-custom-fields'

export type PurchaseKind =
  | 'suppliers'
  | 'bills'
  | 'supplier-payments'
  | 'supplier-credits'
  | 'supplier-refunds'
  | 'recurring-costs'
export interface PurchaseLine {
  id?: number
  source_bill_item_id?: number
  description: string
  expense_category_id: number | null
  quantity: number | string
  price: number
  discount: number
  tax_type_ids: number[]
  total?: number
  tax?: number
  base_total?: number
}
export interface PurchaseRecord {
  fields?: CustomFieldValue[]
  id: number
  number?: string
  name?: string
  supplier_id?: number
  supplier?: PurchaseRecord
  supplier_snapshot?: SupplierSnapshot
  currency_id?: number
  currency?: Currency
  exchange_rate?: number
  reference?: string | null
  document_date?: string
  due_date?: string
  payment_date?: string
  amount?: number
  total?: number
  sub_total?: number
  tax?: number
  due_amount?: number
  base_due_amount?: number
  available_amount?: number
  status?: string
  settlement_status?: string
  creditable_quantities?: Record<string, number>
  timezone?: string
  financial_locked_at?: string | null
  tax_included?: boolean
  notes?: string | null
  items?: PurchaseLine[]
  allocations?: Allocation[]
  payment_allocations?: Allocation[]
  credit_allocations?: Allocation[]
  refunds?: PurchaseRecord[]
  credits?: PurchaseRecord[]
  attachments?: Array<{ id: number; name: string; size: number; url: string }>
  activities?: Array<{
    id: number
    action: string
    created_at: string
    details: Record<string, unknown>
  }>
  balances?: Array<{
    currency_id: number
    currency: Currency
    due: number
    advances: number
    credits: number
  }>
  contact_name?: string | null
  email?: string | null
  phone?: string | null
  website?: string | null
  tax_id?: string | null
  payment_terms?: number
  expense_category_id?: number | null
  enabled?: boolean
  addresses?: Array<{
    address_street_1?: string
    address_street_2?: string
    city?: string
    state?: string
    zip?: string
    country_id?: number | null
  }>
  source_bill_id?: number | null
  source_expense_id?: number | null
  supplier_payment_id?: number | null
  supplier_credit_id?: number | null
  void_reason?: string | null
  mode?: 'BILL' | 'EXPENSE'
  frequency?: 'DAY' | 'WEEK' | 'MONTH' | 'YEAR'
  interval?: number
  starts_at?: string
  next_run_at?: string | null
  ends_at?: string | null
  max_occurrences?: number | null
  occurrence_count?: number
  due_days?: number
  auto_record_paid?: boolean
  template?: Record<string, unknown>
  last_error?: string | null
  occurrences?: Array<{
    id: number
    scheduled_for: string
    record_type: string
    record_id: number
  }>
}
export interface Allocation {
  id?: number
  bill_id: number
  amount: number
  bill?: PurchaseRecord
  payment?: PurchaseRecord
  credit?: PurchaseRecord
}
export interface PurchaseOptions {
  custom_fields?: CustomField[]
  categories: Array<{ id: number; name: string }>
  currencies: Currency[]
  taxes: Array<{
    id: number
    name: string
    percent: number
    calculation_type: string
    fixed_amount: number | null
    compound_tax: boolean
  }>
  payment_methods: Array<{ id: number; name: string }>
}
export interface PurchaseReport {
  currency: Currency
  cash: {
    direct_expenses: number
    supplier_payments: number
    supplier_refunds: number
    net_cash_out: number
  }
  purchases: { gross: number; tax: number; net: number }
  categories: Array<{
    expense_category_id: number
    name: string
    gross: number
    tax: number
    net: number
  }>
  taxes: Array<{ tax_type_id: number; name: string; amount: number }>
  payables: {
    outstanding: number
    overdue: number
    due_soon: number
    due_later: number
    available_advances: number
    available_credits: number
  }
  aging: PurchaseRecord[]
}

export interface SupplierAddress {
  address_street_1?: string
  address_street_2?: string
  city?: string
  state?: string
  zip?: string
  country_id?: number | null
}
export interface SupplierSnapshot {
  name?: string
  contact_name?: string | null
  email?: string | null
  tax_id?: string | null
  addresses?: SupplierAddress[]
}
export interface SupplierDraft {
  customFields: CustomFieldItem[]
  name: string
  contact_name: string
  email: string
  phone: string
  website: string
  tax_id: string
  currency_id: number | null
  payment_terms: number
  expense_category_id: number | null
  enabled: boolean
  notes: string
  addresses: SupplierAddress[]
}
