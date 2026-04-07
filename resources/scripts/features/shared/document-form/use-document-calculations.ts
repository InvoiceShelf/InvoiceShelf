import { computed, type Ref } from 'vue'
import type { Tax } from '../../../types/domain/tax'

export interface DocumentItem {
  id: number | string
  name: string
  description: string | null
  quantity: number
  price: number
  discount: number
  discount_val: number
  discount_type: 'fixed' | 'percentage'
  tax: number
  total: number
  sub_total?: number
  totalTax?: number
  totalSimpleTax?: number
  totalCompoundTax?: number
  taxes?: Partial<Tax>[]
  item_id?: number | null
  unit_name?: string | null
  invoice_id?: number | null
  estimate_id?: number | null
  [key: string]: unknown
}

export interface DocumentFormData {
  id: number | null
  customer: Record<string, unknown> | null
  customer_id: number | null
  items: DocumentItem[]
  taxes: DocumentTax[]
  discount: number
  discount_val: number
  discount_type: 'fixed' | 'percentage'
  tax_per_item: string | null
  tax_included: boolean | null
  discount_per_item: string | null
  notes: string | null
  template_name: string | null
  exchange_rate?: number | null
  currency_id?: number
  selectedCurrency?: Record<string, unknown> | string
  [key: string]: unknown
}

export interface DocumentTax {
  id: number | string
  tax_type_id: number
  name: string
  amount: number
  percent: number | null
  calculation_type: string | null
  fixed_amount: number
  compound_tax: boolean
  type?: string
  [key: string]: unknown
}

export interface DocumentStore {
  getSubTotal: number
  getNetTotal: number
  getTotalSimpleTax: number
  getTotalCompoundTax: number
  getTotalTax: number
  getSubtotalWithDiscount: number
  getTotal: number
  [key: string]: unknown
}

export interface UseDocumentCalculationsOptions {
  items: Ref<DocumentItem[]>
  taxes: Ref<DocumentTax[]>
  discountVal: Ref<number>
  taxPerItem: Ref<string | null>
  taxIncluded: Ref<boolean | null>
}

export function useDocumentCalculations(options: UseDocumentCalculationsOptions) {
  const { items, taxes, discountVal, taxPerItem, taxIncluded } = options

  const subTotal = computed<number>(() => {
    return items.value.reduce((sum: number, item: DocumentItem) => {
      return sum + (item.total ?? 0)
    }, 0)
  })

  const totalSimpleTax = computed<number>(() => {
    return taxes.value.reduce((sum: number, tax: DocumentTax) => {
      if (!tax.compound_tax) {
        return sum + (tax.amount ?? 0)
      }
      return sum
    }, 0)
  })

  const totalCompoundTax = computed<number>(() => {
    return taxes.value.reduce((sum: number, tax: DocumentTax) => {
      if (tax.compound_tax) {
        return sum + (tax.amount ?? 0)
      }
      return sum
    }, 0)
  })

  const totalTax = computed<number>(() => {
    if (taxPerItem.value === 'NO' || taxPerItem.value === null) {
      return totalSimpleTax.value + totalCompoundTax.value
    }
    return items.value.reduce((sum: number, item: DocumentItem) => {
      return sum + (item.tax ?? 0)
    }, 0)
  })

  const subtotalWithDiscount = computed<number>(() => {
    return subTotal.value - discountVal.value
  })

  const netTotal = computed<number>(() => {
    return subtotalWithDiscount.value - totalTax.value
  })

  const total = computed<number>(() => {
    if (taxIncluded.value) {
      return subtotalWithDiscount.value
    }
    return subtotalWithDiscount.value + totalTax.value
  })

  return {
    subTotal,
    totalSimpleTax,
    totalCompoundTax,
    totalTax,
    subtotalWithDiscount,
    netTotal,
    total,
  }
}

/** Calculate item-level subtotal (price * quantity) */
export function calcItemSubtotal(price: number, quantity: number): number {
  return Math.round(price * quantity)
}

/** Calculate item-level discount value */
export function calcItemDiscountVal(
  subtotal: number,
  discount: number,
  discountType: 'fixed' | 'percentage',
): number {
  const absSubtotal = Math.abs(subtotal)
  if (discountType === 'percentage') {
    return Math.round((absSubtotal * discount) / 100)
  }
  return Math.min(Math.round(discount * 100), absSubtotal)
}

/** Calculate item-level total after discount */
export function calcItemTotal(subtotal: number, discountVal: number): number {
  return subtotal - discountVal
}

/** Calculate tax amount for a given total and tax config */
export function calcTaxAmount(
  total: number,
  percent: number | null,
  fixedAmount: number | null,
  calculationType: string | null,
  taxIncluded: boolean | null,
): number {
  if (calculationType === 'fixed' && fixedAmount != null) {
    return fixedAmount
  }
  if (!total || !percent) return 0
  if (taxIncluded) {
    return Math.round(total - total / (1 + percent / 100))
  }
  return Math.round((total * percent) / 100)
}
