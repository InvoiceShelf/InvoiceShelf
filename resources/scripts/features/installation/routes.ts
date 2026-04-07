import type { RouteRecordRaw } from 'vue-router'

/**
 * The installation wizard is a multi-step flow rendered inside a single
 * parent view. Individual step views are not routed independently -- they
 * are controlled by the parent Installation component via dynamic
 * components. This route simply mounts the wizard entry point.
 *
 * The individual step views are:
 *   1. RequirementsView
 *   2. PermissionsView
 *   3. DatabaseView
 *   4. DomainView
 *   5. MailView
 *   6. AccountView
 *   7. CompanyView
 *   8. PreferencesView
 */

export const installationRoutes: RouteRecordRaw[] = [
  {
    path: '/installation',
    name: 'installation',
    component: () => import('./views/RequirementsView.vue'),
    meta: {
      title: 'wizard.req.system_req',
      isInstallation: true,
    },
  },
  {
    path: '/installation/permissions',
    name: 'installation.permissions',
    component: () => import('./views/PermissionsView.vue'),
    meta: {
      title: 'wizard.permissions.permissions',
      isInstallation: true,
    },
  },
  {
    path: '/installation/database',
    name: 'installation.database',
    component: () => import('./views/DatabaseView.vue'),
    meta: {
      title: 'wizard.database.database',
      isInstallation: true,
    },
  },
  {
    path: '/installation/domain',
    name: 'installation.domain',
    component: () => import('./views/DomainView.vue'),
    meta: {
      title: 'wizard.verify_domain.title',
      isInstallation: true,
    },
  },
  {
    path: '/installation/mail',
    name: 'installation.mail',
    component: () => import('./views/MailView.vue'),
    meta: {
      title: 'wizard.mail.mail_config',
      isInstallation: true,
    },
  },
  {
    path: '/installation/account',
    name: 'installation.account',
    component: () => import('./views/AccountView.vue'),
    meta: {
      title: 'wizard.account_info',
      isInstallation: true,
    },
  },
  {
    path: '/installation/company',
    name: 'installation.company',
    component: () => import('./views/CompanyView.vue'),
    meta: {
      title: 'wizard.company_info',
      isInstallation: true,
    },
  },
  {
    path: '/installation/preferences',
    name: 'installation.preferences',
    component: () => import('./views/PreferencesView.vue'),
    meta: {
      title: 'wizard.preferences',
      isInstallation: true,
    },
  },
]
