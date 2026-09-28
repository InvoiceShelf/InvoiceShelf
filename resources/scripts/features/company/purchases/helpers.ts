import { isAxiosError } from 'axios'
import type { PurchaseKind } from '@/scripts/types/domain/purchase'
export const entityAbility = (kind: PurchaseKind) =>
  ({
    suppliers: 'supplier',
    bills: 'bill',
    'supplier-payments': 'supplier-payment',
    'supplier-credits': 'supplier-credit',
    'supplier-refunds': 'supplier-refund',
    'recurring-costs': 'recurring-cost',
  })[kind]
export function purchaseError(error: unknown): string {
  if (isAxiosError(error)) {
    const messages = error.response?.data?.errors as
      | Record<string, string[]>
      | undefined
    return messages
      ? Object.values(messages).flat().join(' ')
      : (error.response?.data?.message ?? error.message)
  }
  return error instanceof Error ? error.message : String(error)
}
export const localDate = () => new Date().toLocaleDateString('en-CA')
export const blankLine = () => ({
  description: '',
  expense_category_id: null as number | null,
  quantity: 1,
  price: 0,
  discount: 0,
  tax_type_ids: [] as number[],
})
