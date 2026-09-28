import type { RouteRecordRaw, RouteLocation } from 'vue-router'
import type { PurchaseKind } from '@/scripts/types/domain/purchase'
import { entityAbility } from './helpers'
import {
  purchaseViews,
  purchaseParent,
  type PurchaseSection,
} from './navigation'

const kinds: PurchaseKind[] = [
  'suppliers',
  'bills',
  'supplier-payments',
  'supplier-credits',
  'supplier-refunds',
  'recurring-costs',
]
const forms = {
  suppliers: () => import('./views/SupplierFormView.vue'),
  bills: () => import('./views/BillFormView.vue'),
  'supplier-credits': () => import('./views/BillFormView.vue'),
  'supplier-payments': () => import('./views/PaymentFormView.vue'),
  'supplier-refunds': () => import('./views/RefundFormView.vue'),
  'recurring-costs': () => import('./views/RecurringCostFormView.vue'),
}
export const purchaseRoutes: RouteRecordRaw[] = [
  ...kinds.flatMap((kind): RouteRecordRaw[] => [
    ...(['suppliers', 'bills', 'supplier-payments'].includes(kind)
      ? [
          {
            path: kind,
            name: `purchases.${kind}`,
            component: () => import('./views/PurchaseWorkspaceView.vue'),
            props: { section: kind },
            meta: {
              menuParent:
                kind === 'recurring-costs'
                  ? undefined
                  : purchaseParent(kind).path,
              requiresAuth: true,
              ability: purchaseViews[kind as PurchaseSection].map(
                (view) => view.ability,
              ),
              title: `purchases.${kind}`,
            },
          },
        ]
      : [
          {
            path: kind,
            name: `purchases.${kind}`,
            redirect: (to: RouteLocation) => {
              const parent = purchaseParent(
                kind,
                String(to.query.mode || 'BILL'),
              )
              return {
                path: parent.path,
                query: { ...to.query, ...parent.query },
              }
            },
          },
        ]),
    {
      path: `${kind}/create`,
      name: `purchases.${kind}.create`,
      component: forms[kind],
      props: { kind },
      meta: {
        menuParent:
          kind === 'recurring-costs' ? undefined : purchaseParent(kind).path,
        requiresAuth: true,
        ability: `create-${entityAbility(kind)}`,
        title: `purchases.${kind}`,
      },
    },
    {
      path: `${kind}/:id/view`,
      name: `purchases.${kind}.view`,
      component: () => import('./views/PurchaseDetailView.vue'),
      props: { kind },
      meta: {
        menuParent:
          kind === 'recurring-costs' ? undefined : purchaseParent(kind).path,
        requiresAuth: true,
        ability: `view-${entityAbility(kind)}`,
        title: `purchases.${kind}`,
      },
    },
    ...(['suppliers', 'bills', 'recurring-costs'].includes(kind)
      ? [
          {
            path: `${kind}/:id/edit`,
            name: `purchases.${kind}.edit`,
            component: forms[kind],
            props: { kind },
            meta: {
              menuParent:
                kind === 'recurring-costs'
                  ? undefined
                  : purchaseParent(kind).path,
              requiresAuth: true,
              ability: `edit-${entityAbility(kind)}`,
              title: `purchases.${kind}`,
            },
          },
        ]
      : []),
  ]),
  {
    path: 'reports/purchases',
    name: 'purchases.report',
    redirect: (to) => ({
      path: '/admin/reports',
      query: { ...to.query, report: 'purchases' },
    }),
    meta: {
      requiresAuth: true,
      ability: 'view-financial-reports',
      title: 'purchases.report',
    },
  },
]
