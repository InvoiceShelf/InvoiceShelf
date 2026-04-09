import type { RouteRecordRaw } from 'vue-router'
import InstallationLayout from '@/scripts/layouts/InstallationLayout.vue'

/**
 * The installation wizard is a multi-step flow. Every step is a child of the
 * /installation parent route, which renders InstallationLayout (logo, card
 * chrome, step progress dots) once and a <router-view /> inside the card.
 *
 * Step order — Language is intentionally first so the rest of the wizard
 * renders in the user's chosen locale:
 *
 *   1. LanguageView      (/installation/language)
 *   2. RequirementsView  (/installation/requirements)
 *   3. PermissionsView   (/installation/permissions)
 *   4. DatabaseView      (/installation/database)
 *   5. DomainView        (/installation/domain)
 *   6. MailView          (/installation/mail)
 *   7. AccountView       (/installation/account)
 *   8. CompanyView       (/installation/company)
 *   9. PreferencesView   (/installation/preferences)
 *
 * Each child view owns its own next() function and calls router.push() to
 * the next step by route name. There is no event-based step coordination —
 * the router IS the state machine.
 */

export const installationRoutes: RouteRecordRaw[] = [
  {
    path: '/installation',
    component: InstallationLayout,
    meta: {
      isInstallation: true,
    },
    children: [
      {
        path: '',
        redirect: { name: 'installation.language' },
      },
      {
        path: 'language',
        name: 'installation.language',
        component: () => import('./views/LanguageView.vue'),
        meta: {
          title: 'wizard.install_language.title',
          isInstallation: true,
        },
      },
      {
        path: 'requirements',
        name: 'installation.requirements',
        component: () => import('./views/RequirementsView.vue'),
        meta: {
          title: 'wizard.req.system_req',
          isInstallation: true,
        },
      },
      {
        path: 'permissions',
        name: 'installation.permissions',
        component: () => import('./views/PermissionsView.vue'),
        meta: {
          title: 'wizard.permissions.permissions',
          isInstallation: true,
        },
      },
      {
        path: 'database',
        name: 'installation.database',
        component: () => import('./views/DatabaseView.vue'),
        meta: {
          title: 'wizard.database.database',
          isInstallation: true,
        },
      },
      {
        path: 'domain',
        name: 'installation.domain',
        component: () => import('./views/DomainView.vue'),
        meta: {
          title: 'wizard.verify_domain.title',
          isInstallation: true,
        },
      },
      {
        path: 'mail',
        name: 'installation.mail',
        component: () => import('./views/MailView.vue'),
        meta: {
          title: 'wizard.mail.mail_config',
          isInstallation: true,
        },
      },
      {
        path: 'account',
        name: 'installation.account',
        component: () => import('./views/AccountView.vue'),
        meta: {
          title: 'wizard.account_info',
          isInstallation: true,
        },
      },
      {
        path: 'company',
        name: 'installation.company',
        component: () => import('./views/CompanyView.vue'),
        meta: {
          title: 'wizard.company_info',
          isInstallation: true,
        },
      },
      {
        path: 'preferences',
        name: 'installation.preferences',
        component: () => import('./views/PreferencesView.vue'),
        meta: {
          title: 'wizard.preferences',
          isInstallation: true,
        },
      },
    ],
  },
]
