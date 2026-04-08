import type { RouteRecordRaw } from 'vue-router'

const CompanyModulesIndexView = () => import('./views/CompanyModulesIndexView.vue')
const ModuleSettingsView = () => import('./views/ModuleSettingsView.vue')

/**
 * Company-context module routes.
 *
 * - `/admin/modules` — read-only Active Modules index, lists every module the
 *   super admin has activated on this instance with a "Settings" link.
 * - `/admin/modules/:slug/settings` — schema-rendered settings form for a
 *   specific active module, backed by the InvoiceShelf\Modules\Registry::settingsFor()
 *   schema and CompanySetting persistence (per-company values).
 *
 * The marketplace browser (install/uninstall/activate) lives in the super-admin
 * context at `/admin/administration/modules`, see features/admin/modules/routes.ts.
 */
export const moduleRoutes: RouteRecordRaw[] = [
  {
    path: 'modules',
    name: 'modules.index',
    component: CompanyModulesIndexView,
    meta: {
      requiresAuth: true,
      ability: 'manage-module',
      title: 'modules.title',
    },
  },
  {
    path: 'modules/:slug/settings',
    name: 'modules.settings',
    component: ModuleSettingsView,
    meta: {
      requiresAuth: true,
      ability: 'manage-module',
      title: 'modules.settings.title',
    },
  },
]
