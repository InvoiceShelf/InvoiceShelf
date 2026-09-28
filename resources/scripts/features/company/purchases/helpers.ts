import { isAxiosError } from 'axios'
import { translate } from '@/scripts/plugins/i18n'
import { getErrorTranslationKey } from '@/scripts/utils/error-handling'
import type { PurchaseKind } from '@/scripts/types/domain/purchase'
export const entityAbility = (kind: PurchaseKind) =>
  ({
    suppliers: 'supplier',
    bills: 'bill',
    'supplier-payments': 'supplier-payment',
    'supplier-credits': 'supplier-credit',
    'supplier-refunds': 'supplier-refund',
  })[kind]
export function purchaseError(error: unknown): string {
  if (isAxiosError(error)) {
    const messages = error.response?.data?.errors as
      | Record<string, string[]>
      | undefined
    return messages
      ? Object.values(messages).flat().map(translateMessage).join(' ')
      : translateMessage(error.response?.data?.message ?? error.message)
  }
  return error instanceof Error ? error.message : String(error)
}
function translateMessage(message: string): string {
  const key = getErrorTranslationKey(message)
  return key ? translate(key) : message
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
