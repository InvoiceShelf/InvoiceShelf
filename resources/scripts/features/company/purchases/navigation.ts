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
  mode?: 'BILL' | 'EXPENSE'
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
      label: 'view_switcher.one_time',
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
    {
      key: 'recurring',
      kind: 'recurring-costs',
      label: 'view_switcher.recurring',
      icon: 'ArrowPathIcon',
      ability: 'view-recurring-cost',
      mode: 'BILL',
    },
  ],
  expenses: [
    {
      key: 'expenses',
      kind: 'expenses',
      label: 'view_switcher.one_time',
      icon: 'CalculatorIcon',
      ability: 'view-expense',
    },
    {
      key: 'recurring',
      kind: 'recurring-costs',
      label: 'view_switcher.recurring',
      icon: 'ArrowPathIcon',
      ability: 'view-recurring-cost',
      mode: 'EXPENSE',
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
export function purchaseParent(kind: PurchaseKind, mode?: string) {
  if (kind === 'supplier-credits')
    return { path: '/admin/bills', query: { view: 'credits' } }
  if (kind === 'supplier-refunds')
    return { path: '/admin/supplier-payments', query: { view: 'refunds' } }
  if (kind === 'recurring-costs')
    return {
      path: mode === 'EXPENSE' ? '/admin/expenses' : '/admin/bills',
      query: { view: 'recurring' },
    }
  return { path: `/admin/${kind}`, query: {} }
}
export const purchaseCreateLabels: Record<PurchaseKind, string> = {
  suppliers: 'purchases.new_supplier',
  bills: 'purchases.new_bill',
  'supplier-payments': 'purchases.new_payment',
  'supplier-credits': 'purchases.new_credit',
  'supplier-refunds': 'purchases.new_refund',
  'recurring-costs': 'purchases.new_schedule',
}

export const purchaseHelpKeys: Record<PurchaseKind | 'expenses', string> = {
  suppliers: 'purchases.intro_suppliers',
  bills: 'purchases.bill_help',
  expenses: 'page_help.expenses',
  'supplier-payments': 'purchases.payment_help',
  'supplier-credits': 'purchases.intro_supplier-credits',
  'supplier-refunds': 'purchases.refund_help',
  'recurring-costs': 'page_help.recurring_costs',
}
