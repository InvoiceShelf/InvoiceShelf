import { useUserStore } from '@/scripts/stores/user.store'
import type {
  EntityContext,
  EntityFormContext,
  EntityRecord,
  ExtensionEntity,
} from './types'

/** The host field that holds each entity's number, or its name where it has none. */
const NUMBER_FIELD: Partial<Record<ExtensionEntity, string>> = {
  invoice: 'invoice_number',
  estimate: 'estimate_number',
  customer: 'name',
  payment: 'payment_number',
  expense: 'expense_number',
  item: 'name',
}

const TOTAL_FIELD: Partial<Record<ExtensionEntity, string>> = {
  invoice: 'total',
  estimate: 'total',
  'recurring-invoice': 'total',
  payment: 'amount',
  expense: 'amount',
  item: 'price',
}

/**
 * The snapshot a module sees of a host record: plain values, frozen, built
 * fresh on every call, so a module can never write into a host store.
 */
export function toEntityRecord(entity: ExtensionEntity, raw: unknown): EntityRecord | null {
  if (raw === null || typeof raw !== 'object' || typeof (raw as { id?: unknown }).id !== 'number') {
    return null
  }

  const record = raw as Record<string, unknown>
  const text = (field: string | undefined): string | null =>
    field !== undefined && typeof record[field] === 'string' ? (record[field] as string) : null
  const number = (field: string | undefined): number | null =>
    field !== undefined && typeof record[field] === 'number' ? (record[field] as number) : null
  const currency = record.currency ?? (record.customer as { currency?: unknown } | undefined)?.currency

  return Object.freeze({
    entity,
    id: record.id as number,
    number: text(NUMBER_FIELD[entity]),
    status: text('status'),
    paid_status: text('paid_status'),
    type: entity === 'invoice' ? text('type') : null,
    customer_id: entity === 'customer' ? null : number('customer_id'),
    total: number(TOTAL_FIELD[entity]),
    due_amount: number('due_amount'),
    currency_code: currencyCodeOf(currency),
    related_invoice_id: number('related_invoice_id'),
  })
}

/**
 * Context for actions and panels on one record. Call it from a component's
 * setup: `record` and `refresh` are read when a module asks, so the module
 * always sees the record the view is showing now.
 */
export function entityContext(
  entity: ExtensionEntity,
  placement: 'list' | 'detail',
  record: () => unknown,
  refresh: () => unknown = () => undefined,
): EntityContext {
  const userStore = useUserStore()

  return Object.freeze({
    entity,
    placement,
    record: () => toEntityRecord(entity, record()),
    can: (ability: string | string[]) => userStore.hasAbilities(ability),
    refresh: async () => {
      await refresh()
    },
  })
}

/** The ISO code of a currency value from a host store, which may also be an empty string or an id. */
export function currencyCodeOf(value: unknown): string | null {
  return value !== null && typeof value === 'object' && typeof (value as { code?: unknown }).code === 'string'
    ? (value as { code: string }).code
    : null
}

export interface EntityFormSource {
  isEdit: () => boolean
  record?: () => unknown
  isRecurring?: () => boolean
  total?: () => number | null
  currencyCode?: () => string | null
  date?: () => string | null
  dueDate?: () => string | null
}

/** Context for form actions, form sections and save hooks on one record's form. */
export function entityFormContext(entity: ExtensionEntity, source: EntityFormSource): EntityFormContext {
  const userStore = useUserStore()

  return Object.freeze({
    entity,
    isEdit: () => source.isEdit(),
    record: () => (source.isEdit() ? toEntityRecord(entity, source.record?.()) : null),
    can: (ability: string | string[]) => userStore.hasAbilities(ability),
    isRecurring: () => source.isRecurring?.() ?? false,
    total: () => source.total?.() ?? null,
    currencyCode: () => source.currencyCode?.() ?? null,
    date: () => source.date?.() ?? null,
    dueDate: () => source.dueDate?.() ?? null,
  })
}
