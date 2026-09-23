import type { RouteRecordRaw } from 'vue-router'

const CustomerPortalAuthLayout = () => import('./components/CustomerPortalAuthLayout.vue')
const CustomerPortalLayout = () => import('./components/CustomerPortalLayout.vue')
const CustomerPortalLoginView = () => import('./views/auth/CustomerPortalLoginView.vue')
const CustomerPortalForgotPasswordView = () => import('./views/auth/CustomerPortalForgotPasswordView.vue')
const CustomerPortalResetPasswordView = () => import('./views/auth/CustomerPortalResetPasswordView.vue')
const CustomerDashboardView = () => import('./views/CustomerDashboardView.vue')
const CustomerInvoicesView = () => import('./views/CustomerInvoicesView.vue')
const CustomerInvoiceDetailView = () => import('./views/CustomerInvoiceDetailView.vue')
const CustomerEstimatesView = () => import('./views/CustomerEstimatesView.vue')
const CustomerEstimateDetailView = () => import('./views/CustomerEstimateDetailView.vue')
const CustomerPaymentsView = () => import('./views/CustomerPaymentsView.vue')
const CustomerPaymentDetailView = () => import('./views/CustomerPaymentDetailView.vue')
const CustomerSettingsView = () => import('./views/CustomerSettingsView.vue')

const portalRoutes: RouteRecordRaw[] = [
  {
    path: '/:company/customer',
    component: CustomerPortalAuthLayout,
    meta: {
      isCustomerPortal: true,
      customerPortalGuest: true,
    },
    children: [
      {
        path: 'login',
        alias: '',
        name: 'customer-portal.login',
        component: CustomerPortalLoginView,
      },
      {
        path: 'forgot-password',
        name: 'customer-portal.forgot-password',
        component: CustomerPortalForgotPasswordView,
      },
      {
        path: 'reset/password/:token',
        name: 'customer-portal.reset-password',
        component: CustomerPortalResetPasswordView,
      },
    ],
  },
  {
    path: '/:company/customer',
    component: CustomerPortalLayout,
    meta: {
      isCustomerPortal: true,
    },
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

/**
 * Empty in the mobile client: the portal is session-authenticated and for
 * customers, while a client is a staff tool signed in with a bearer token.
 * Declaring it here, rather than at the point of use, is what keeps the
 * portal's views out of the client package entirely.
 */
export const customerPortalRoutes: RouteRecordRaw[] = __INVOICESHELF_CLIENT__ ? [] : portalRoutes
