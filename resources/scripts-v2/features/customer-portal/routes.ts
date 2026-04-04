import type { RouteRecordRaw } from 'vue-router'

const CustomerPortalLayout = () => import('./components/CustomerPortalLayout.vue')
const CustomerDashboardView = () => import('./views/CustomerDashboardView.vue')
const CustomerInvoicesView = () => import('./views/CustomerInvoicesView.vue')
const CustomerInvoiceDetailView = () => import('./views/CustomerInvoiceDetailView.vue')
const CustomerEstimatesView = () => import('./views/CustomerEstimatesView.vue')
const CustomerEstimateDetailView = () => import('./views/CustomerEstimateDetailView.vue')
const CustomerPaymentsView = () => import('./views/CustomerPaymentsView.vue')
const CustomerPaymentDetailView = () => import('./views/CustomerPaymentDetailView.vue')
const CustomerSettingsView = () => import('./views/CustomerSettingsView.vue')

export const customerPortalRoutes: RouteRecordRaw[] = [
  {
    path: '/:company/customer',
    component: CustomerPortalLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'customer-portal.dashboard',
        component: CustomerDashboardView,
      },
      {
        path: 'invoices',
        name: 'customer-portal.invoices',
        component: CustomerInvoicesView,
      },
      {
        path: 'invoices/:id/view',
        name: 'customer-portal.invoices.view',
        component: CustomerInvoiceDetailView,
      },
      {
        path: 'estimates',
        name: 'customer-portal.estimates',
        component: CustomerEstimatesView,
      },
      {
        path: 'estimates/:id/view',
        name: 'customer-portal.estimates.view',
        component: CustomerEstimateDetailView,
      },
      {
        path: 'payments',
        name: 'customer-portal.payments',
        component: CustomerPaymentsView,
      },
      {
        path: 'payments/:id/view',
        name: 'customer-portal.payments.view',
        component: CustomerPaymentDetailView,
      },
      {
        path: 'settings',
        name: 'customer-portal.settings',
        component: CustomerSettingsView,
      },
    ],
  },
]
