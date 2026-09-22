import { createRouter, createWebHashHistory, createWebHistory } from 'vue-router'
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
]

/**
 * Top-level route definitions assembled from all feature modules.
 */
const routes: RouteRecordRaw[] = [
  // Installation wizard (no auth), which the client build declares as an
  // empty list: a client connects to a server that is already set up.
  ...installationRoutes,

  // Public invoice view (no auth, no layout). It is reached by a link a
  // customer was emailed, which opens in a browser, not in the app.
  ...(__INVOICESHELF_CLIENT__
    ? []
    : [
        {
          path: '/customer/invoices/view/:hash',
          name: 'invoice.public',
          component: InvoicePublicPage,
        },
      ]),

  // Auth routes (login, register, forgot/reset password)
  ...authRoutes,

  // Admin area: company-scoped routes
  {
    path: '/admin',
    name: 'admin',
    component: CompanyLayout,
    meta: { requiresAuth: true },
    children: companyChildren,
  },

  // Admin area: super admin routes (separate top-level entry to keep
  // the admin feature module self-contained)
  ...adminRoutes,

  // Customer portal, which the client build declares as an empty list:
  // staff and admin only there, and the portal stays on the web.
  ...customerPortalRoutes,

  // A client opens on an empty hash, which no other route claims.
  ...(__INVOICESHELF_CLIENT__
    ? [{ path: '/', redirect: { name: 'login' } }]
    : []),

  // Catch-all 404
  {
    path: '/:catchAll(.*)',
    name: 'not-found',
    component: NotFoundView,
  },
]

const router = createRouter({
  // A client is served from a file package under capacitor:// or https://
  // with no server to rewrite paths, so its history lives in the hash.
  history: __INVOICESHELF_CLIENT__ ? createWebHashHistory() : createWebHistory(),
  linkActiveClass: 'active',
  routes,
})

router.beforeEach(authGuard)

export default router
