import type { Customer } from './customer'
import type { User } from './user'
import type { Company } from './company'
import type { Currency } from './currency'
import type { Tax } from './tax'
import type { CustomFieldValue } from './custom-field'
import type { DiscountType } from './invoice'

export enum EstimateStatus {
  DRAFT = 'DRAFT',
  SENT = 'SENT',
  VIEWED = 'VIEWED',
  EXPIRED = 'EXPIRED',
  ACCEPTED = 'ACCEPTED',
  REJECTED = 'REJECTED',
}

export interface EstimateItem {
  id: number | string
  name: string
  description: string | null
  discount_type: DiscountType
  quantity: number
  unit_name: string | null
  discount: number
  discount_val: number
  price: number
  tax: number
  total: number
  item_id: number | null
  estimate_id: number | null
  company_id: number
  exchange_rate: number
  base_discount_val: number
  base_price: number
  base_tax: number
  base_total: number
  taxes?: Tax[]
  fields?: CustomFieldValue[]
}

export interface Estimate {
  id: number
  estimate_date: string
  expiry_date: string
  estimate_number: string
  status: EstimateStatus
  reference_number: string | null
  tax_per_item: string | null
  tax_included: boolean | null
  discount_per_item: string | null
  notes: string | null
  discount: number
  discount_type: DiscountType
  discount_val: number
  sub_total: number
  total: number
  tax: number
  unique_hash: string
  creator_id: number
  template_name: string | null
  customer_id: number
  exchange_rate: number
  base_discount_val: number
  base_sub_total: number
  base_total: number
  base_tax: number
  sequence_number: number
  currency_id: number
  formatted_expiry_date: string
  formatted_estimate_date: string
  estimate_pdf_url: string
  sales_tax_type: string | null
  sales_tax_address_type: string | null
  items?: EstimateItem[]
  customer?: Customer
  creator?: User
  taxes?: Tax[]
  fields?: CustomFieldValue[]
  company?: Company
  currency?: Currency
}

export interface CreateEstimatePayload {
  estimate_date: string
  expiry_date: string
  estimate_number: string
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
  items: CreateEstimateItemPayload[]
  taxes?: Partial<Tax>[]
  customFields?: CustomFieldValue[]
  fields?: CustomFieldValue[]
}

export interface CreateEstimateItemPayload {
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
