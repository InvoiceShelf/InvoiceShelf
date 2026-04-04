import type { RouteRecordRaw } from 'vue-router'

const RecurringInvoiceIndexView = () =>
  import('./views/RecurringInvoiceIndexView.vue')
const RecurringInvoiceCreateView = () =>
  import('./views/RecurringInvoiceCreateView.vue')
const RecurringInvoiceDetailView = () =>
  import('./views/RecurringInvoiceDetailView.vue')

export const recurringInvoiceRoutes: RouteRecordRaw[] = [
  {
    path: 'recurring-invoices',
    name: 'recurring-invoices.index',
    component: RecurringInvoiceIndexView,
    meta: {
      ability: 'view-recurring-invoice',
      title: 'recurring_invoices.title',
    },
  },
  {
    path: 'recurring-invoices/create',
    name: 'recurring-invoices.create',
    component: RecurringInvoiceCreateView,
    meta: {
      ability: 'create-recurring-invoice',
      title: 'recurring_invoices.new_invoice',
    },
  },
  {
    path: 'recurring-invoices/:id/edit',
    name: 'recurring-invoices.edit',
    component: RecurringInvoiceCreateView,
    meta: {
      ability: 'edit-recurring-invoice',
      title: 'recurring_invoices.edit_invoice',
    },
  },
  {
    path: 'recurring-invoices/:id/view',
    name: 'recurring-invoices.view',
    component: RecurringInvoiceDetailView,
    meta: {
      ability: 'view-recurring-invoice',
      title: 'recurring_invoices.title',
    },
  },
]
