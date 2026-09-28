import type { PurchaseKind } from '@/scripts/types/domain/purchase'

export type PurchaseSection =
  | 'suppliers'
  | 'bills'
  | 'expenses'
  | 'supplier-payments'
export interface PurchaseView {
  key: string
  kind: PurchaseKind | 'expenses'
  label: string
  icon: string
  ability: string
}
export const purchaseViews: Record<PurchaseSection, PurchaseView[]> = {
  suppliers: [
    {
      key: 'suppliers',
      kind: 'suppliers',
      label: 'purchases.suppliers',
      icon: 'UserGroupIcon',
      ability: 'view-supplier',
    },
  ],
  bills: [
    {
      key: 'bills',
      kind: 'bills',
      label: 'purchases.bills',
      icon: 'DocumentTextIcon',
      ability: 'view-bill',
    },
    {
      key: 'credits',
      kind: 'supplier-credits',
      label: 'view_switcher.credits',
      icon: 'ArrowUturnLeftIcon',
      ability: 'view-supplier-credit',
    },
  ],
  expenses: [
    {
      key: 'expenses',
      kind: 'expenses',
      label: 'navigation.expenses',
      icon: 'CalculatorIcon',
      ability: 'view-expense',
    },
  ],
  'supplier-payments': [
    {
      key: 'payments',
      kind: 'supplier-payments',
      label: 'view_switcher.payments',
      icon: 'CreditCardIcon',
      ability: 'view-supplier-payment',
    },
    {
      key: 'refunds',
      kind: 'supplier-refunds',
      label: 'view_switcher.refunds',
      icon: 'ArrowDownLeftIcon',
      ability: 'view-supplier-refund',
    },
  ],
}
export function purchaseParent(kind: PurchaseKind) {
  if (kind === 'supplier-credits')
    return { path: '/admin/bills', query: { view: 'credits' } }
  if (kind === 'supplier-refunds')
    return { path: '/admin/supplier-payments', query: { view: 'refunds' } }
  return { path: `/admin/${kind}`, query: {} }
}
export const purchaseCreateLabels: Record<PurchaseKind, string> = {
  suppliers: 'purchases.new_supplier',
  bills: 'purchases.new_bill',
  'supplier-payments': 'purchases.new_payment',
  'supplier-credits': 'purchases.new_credit',
  'supplier-refunds': 'purchases.new_refund',
}

export const purchaseHelpKeys: Record<PurchaseKind | 'expenses', string> = {
  suppliers: 'purchases.intro_suppliers',
  bills: 'purchases.bill_help',
  expenses: 'page_help.expenses',
  'supplier-payments': 'purchases.payment_help',
  'supplier-credits': 'purchases.intro_supplier-credits',
  'supplier-refunds': 'purchases.refund_help',
}
