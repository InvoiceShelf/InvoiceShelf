import type { RouteRecordRaw } from 'vue-router'

const InvoiceCreateView = () =>
  import('../invoices/views/InvoiceCreateView.vue')
const RecurringInvoiceDetailView = () =>
  import('./views/RecurringInvoiceDetailView.vue')

export const recurringInvoiceRoutes: RouteRecordRaw[] = [
  {
    path: 'recurring-invoices',
    redirect: '/admin/invoices?view=recurring',
  },
  {
    path: 'recurring-invoices/create',
    name: 'recurring-invoices.create',
    redirect: '/admin/invoices/create?recurring=1',
  },
  {
    path: 'recurring-invoices/:id/edit',
    name: 'recurring-invoices.edit',
    component: InvoiceCreateView,
    meta: {
      requiresAuth: true,
      ability: 'edit-recurring-invoice',
      title: 'recurring_invoices.edit_invoice',
    },
  },
  {
    path: 'recurring-invoices/:id/view',
    name: 'recurring-invoices.view',
    component: RecurringInvoiceDetailView,
    meta: {
      requiresAuth: true,
      ability: 'view-recurring-invoice',
      title: 'recurring_invoices.title',
    },
  },
]
