import type { Company } from './company'
import type { Currency } from './currency'
import type { Tax } from './tax'

export interface Unit {
  id: number
  name: string
  /** UN/ECE Rec 20 Unit Code (EN 16931 BT-130), e.g. C62 = piece, HUR = hour. */
  unit_code: string
  company_id: number
  company?: Company
}

export interface Item {
  id: number
  name: string
  description: string | null
  price: number
  unit_id: number | null
  company_id: number
  creator_id: number
  currency_id: number | null
  created_at: string
  updated_at: string
  tax_per_item: string | null
  formatted_created_at: string
  unit?: Unit
  company?: Company
  taxes?: Tax[]
  currency?: Currency
}
