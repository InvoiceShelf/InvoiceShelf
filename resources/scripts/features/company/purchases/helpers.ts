import { isAxiosError } from 'axios'
import { translate } from '@/scripts/plugins/i18n'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { getErrorTranslationKey } from '@/scripts/utils/error-handling'
import type {
  PurchaseKind,
  PurchaseRecord,
} from '@/scripts/types/domain/purchase'
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
/**
 * What one run of a recurring cost comes to, in minor units, as far as the
 * template says: an expense's amount, or a bill's lines after discount.
 * A bill's taxes are worked out when it is generated, so `plusTax` marks
 * a bill whose taxes come on top of the figure shown.
 */
export function recurringAmount(record: PurchaseRecord): {
  amount: number
  plusTax: boolean
} {
  const template = record.template ?? {}
  if (record.mode === 'EXPENSE')
    return { amount: Number(template.amount ?? 0), plusTax: false }
  const items = template.items ?? []
  const amount = items.reduce((sum, line) => {
    const gross = Number(line.quantity || 0) * Number(line.price || 0)
    return sum + Math.round(gross * (1 - Number(line.discount || 0) / 100))
  }, 0)
  return {
    amount,
    plusTax:
      !template.tax_included &&
      items.some((line) => (line.tax_type_ids ?? []).length > 0),
  }
}
/**
 * The company's calendar date for a stored schedule moment. Next-run times
 * are kept in the application's zone (UTC), and a run belongs to the day
 * it falls on where the company is.
 */
export function scheduleDate(moment?: string | null): string | null {
  if (!moment) return null
  const date = new Date(`${moment.replace(' ', 'T').slice(0, 19)}Z`)
  if (Number.isNaN(date.getTime())) return moment.slice(0, 10)
  const timeZone = useCompanyStore().selectedCompanySettings?.time_zone
  try {
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: timeZone || undefined,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(date)
  } catch {
    return moment.slice(0, 10)
  }
}
