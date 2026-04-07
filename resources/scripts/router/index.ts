import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'

// Ensure route meta augmentation is loaded
import './types'

// Feature routes
import { authRoutes } from '../features/auth/routes'
import { adminRoutes } from '../features/admin/routes'
import { installationRoutes } from '../features/installation/routes'
import { customerPortalRoutes } from '../features/customer-portal/routes'

// Company feature routes (children of /admin)
import dashboardRoutes from '../features/company/dashboard/routes'
import customerRoutes from '../features/company/customers/routes'
import { invoiceRoutes } from '../features/company/invoices/routes'
import { estimateRoutes } from '../features/company/estimates/routes'
import { recurringInvoiceRoutes } from '../features/company/recurring-invoices/routes'
import { paymentRoutes } from '../features/company/payments/routes'
import { expenseRoutes } from '../features/company/expenses/routes'
import itemRoutes from '../features/company/items/routes'
import memberRoutes from '../features/company/members/routes'
import reportRoutes from '../features/company/reports/routes'
import settingsRoutes from '../features/company/settings/routes'
import { moduleRoutes } from '../features/company/modules/routes'

// Guard
import { authGuard } from './guards'

// Layouts (lazy-loaded)
const CompanyLayout = () => import('../layouts/CompanyLayout.vue')
const NotFoundView = () => import('../features/errors/NotFoundView.vue')
const NoCompanyView = () => import('../features/company/NoCompanyView.vue')
const InvoicePublicPage = () => import('../components/base/InvoicePublicPage.vue')

/**
 * All company-scoped children routes that live under `/admin` with
 * the CompanyLayout wrapper. Each feature module exports its own
 * route array; we merge them here.
 */
const companyChildren: RouteRecordRaw[] = [
  // No-company fallback
  {
    path: 'no-company',
    name: 'no.company',
    component: NoCompanyView,
  },
  // Feature routes
  ...dashboardRoutes,
  ...customerRoutes,
  ...invoiceRoutes,
  ...estimateRoutes,
  ...recurringInvoiceRoutes,
  ...paymentRoutes,
  ...expenseRoutes,
  ...itemRoutes,
  ...memberRoutes,
  ...reportRoutes,
  ...settingsRoutes,
  ...moduleRoutes,
]

/**
 * Top-level route definitions assembled from all feature modules.
 */
const routes: RouteRecordRaw[] = [
  // Installation wizard (no auth)
  ...installationRoutes,

  // Public invoice view (no auth, no layout)
  {
    path: '/customer/invoices/view/:hash',
    name: 'invoice.public',
    component: InvoicePublicPage,
  },

  // Auth routes (login, register, forgot/reset password)
  ...authRoutes,

  // Admin area: company-scoped routes
  {
    path: '/admin',
    component: CompanyLayout,
    meta: { requiresAuth: true },
    children: companyChildren,
  },

  // Admin area: super admin routes (separate top-level entry to keep
  // the admin feature module self-contained)
  ...adminRoutes,

  // Customer portal
  ...customerPortalRoutes,

  // Catch-all 404
  {
    path: '/:catchAll(.*)',
    name: 'not-found',
    component: NotFoundView,
  },
]

const router = createRouter({
  history: createWebHistory(),
  linkActiveClass: 'active',
  routes,
})

router.beforeEach(authGuard)

export default router
