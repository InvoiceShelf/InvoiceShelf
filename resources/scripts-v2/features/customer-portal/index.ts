export { customerPortalRoutes } from './routes'

export { useCustomerPortalStore } from './store'
export type {
  CustomerPortalState,
  CustomerPortalStore,
  CustomerPortalMenuItem,
  CustomerUserForm,
  CustomerAddress,
  CustomerLoginData,
  DashboardData,
  PaginatedListParams,
  PaginatedResponse,
} from './store'

// Views
export { default as CustomerDashboardView } from './views/CustomerDashboardView.vue'
export { default as CustomerInvoicesView } from './views/CustomerInvoicesView.vue'
export { default as CustomerInvoiceDetailView } from './views/CustomerInvoiceDetailView.vue'
export { default as CustomerEstimatesView } from './views/CustomerEstimatesView.vue'
export { default as CustomerEstimateDetailView } from './views/CustomerEstimateDetailView.vue'
export { default as CustomerPaymentsView } from './views/CustomerPaymentsView.vue'
export { default as CustomerPaymentDetailView } from './views/CustomerPaymentDetailView.vue'
export { default as CustomerSettingsView } from './views/CustomerSettingsView.vue'

// Components
export { default as CustomerPortalLayout } from './components/CustomerPortalLayout.vue'
