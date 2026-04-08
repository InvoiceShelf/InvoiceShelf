import type { RouteRecordRaw } from 'vue-router'

const ModuleIndexView = () => import('./views/ModuleIndexView.vue')
const ModuleDetailView = () => import('./views/ModuleDetailView.vue')

/**
 * Super-admin marketplace browser routes.
 *
 * These are mounted as children of `/admin/administration` in features/admin/routes.ts,
 * meaning they require `meta.isSuperAdmin` and the admin-mode bootstrap.
 *
 * Company-context module routes (the read-only Active Modules index and the
 * schema-rendered settings page) live in features/company/modules/routes.ts.
 */
export const adminModuleRoutes: RouteRecordRaw[] = [
  {
    path: 'modules',
    name: 'admin.modules.index',
    component: ModuleIndexView,
    meta: {
      isSuperAdmin: true,
      title: 'modules.title',
    },
  },
  {
    path: 'modules/:slug',
    name: 'admin.modules.view',
    component: ModuleDetailView,
    meta: {
      isSuperAdmin: true,
      title: 'modules.title',
    },
  },
]
