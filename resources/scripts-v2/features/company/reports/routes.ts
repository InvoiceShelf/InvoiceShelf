import type { RouteRecordRaw } from 'vue-router'

const reportRoutes: RouteRecordRaw[] = [
  {
    path: 'reports/sales',
    name: 'reports.sales',
    component: () => import('./views/SalesReportView.vue'),
    meta: {
      ability: 'view-financial-report',
    },
  },
  {
    path: 'reports/profit-loss',
    name: 'reports.profit-loss',
    component: () => import('./views/ProfitLossReportView.vue'),
    meta: {
      ability: 'view-financial-report',
    },
  },
  {
    path: 'reports/expenses',
    name: 'reports.expenses',
    component: () => import('./views/ExpensesReportView.vue'),
    meta: {
      ability: 'view-financial-report',
    },
  },
  {
    path: 'reports/taxes',
    name: 'reports.taxes',
    component: () => import('./views/TaxReportView.vue'),
    meta: {
      ability: 'view-financial-report',
    },
  },
]

export default reportRoutes
