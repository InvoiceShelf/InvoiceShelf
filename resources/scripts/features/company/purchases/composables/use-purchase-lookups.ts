import {
  inject,
  provide,
  ref,
  computed,
  watch,
  type InjectionKey,
  type Ref,
} from 'vue'
import { useUserStore } from '@/scripts/stores/user.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
export type PurchaseLookupKind = 'category' | 'method' | 'tax'
export interface PurchaseLookup {
  id: number
  name: string
  [key: string]: unknown
}
function createLookups(
  shared?: Ref<Record<PurchaseLookupKind, PurchaseLookup[]>>,
) {
  const user = useUserStore()
  const company = useCompanyStore()
  const active = ref<PurchaseLookupKind | null>(null)
  let onSaved: ((record: PurchaseLookup) => void) | undefined
  const records =
    shared ??
    ref<Record<PurchaseLookupKind, PurchaseLookup[]>>({
      category: [],
      method: [],
      tax: [],
    })
  const allowed = computed(() => ({
    category: user.hasAbilities(['create-expense', 'edit-expense']),
    method: user.hasAbilities(['create-payment', 'edit-payment']),
    tax: user.hasAbilities('create-tax-type'),
  }))
  function open(
    kind: PurchaseLookupKind,
    saved: (record: PurchaseLookup) => void,
  ) {
    if (!allowed.value[kind]) return
    onSaved = saved
    active.value = kind
  }
  function close() {
    active.value = null
    onSaved = undefined
  }
  function saved(value: unknown) {
    const record = value as PurchaseLookup | undefined
    if (!active.value || !record?.id) return
    records.value[active.value] = [
      ...records.value[active.value].filter((row) => row.id !== record.id),
      record,
    ]
    onSaved?.(record)
  }
  watch(
    () => company.selectedCompany?.id,
    () => {
      close()
      records.value = { category: [], method: [], tax: [] }
    },
  )
  return { active, records, allowed, open, close, saved }
}
export type PurchaseLookups = ReturnType<typeof createLookups>
const key: InjectionKey<PurchaseLookups> = Symbol('purchaseLookups')
export function providePurchaseLookups() {
  const context = createLookups(inject(key, undefined)?.records)
  provide(key, context)
  return context
}
export function usePurchaseLookups() {
  const context = inject(key)
  if (!context) throw new Error('Purchase lookup host is required')
  return context
}
