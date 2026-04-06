import type { RouteRecordRaw } from 'vue-router'
import { ABILITIES } from '@v2/config/abilities'

const settingsRoutes: RouteRecordRaw[] = [
  // User account settings — standalone page with sidebar tabs (General, Profile Photo, Security)
  {
    path: 'account-settings',
    component: () => import('./views/UserSettingsLayoutView.vue'),
    meta: {
      requiresAuth: true,
    },
    children: [
      {
        path: '',
        name: 'settings.account',
        redirect: { name: 'settings.account.general' },
      },
      {
        path: 'general',
        name: 'settings.account.general',
        component: () => import('./views/UserGeneralView.vue'),
      },
      {
        path: 'profile-photo',
        name: 'settings.account.profile-photo',
        component: () => import('./views/UserProfilePhotoView.vue'),
      },
      {
        path: 'security',
        name: 'settings.account.security',
        component: () => import('./views/UserSecurityView.vue'),
      },
    ],
  },
  {
    path: 'settings',
    component: () => import('./views/SettingsLayoutView.vue'),
    children: [
      {
        path: 'roles-settings',
        redirect: { name: 'settings.roles' },
      },
      {
        path: 'exchange-rate-provider',
        redirect: { name: 'settings.exchange-rate' },
      },
      {
        path: 'payment-mode',
        redirect: { name: 'settings.payment-modes' },
      },
      {
        path: 'expense-category',
        redirect: { name: 'settings.expense-categories' },
      },
      {
        path: 'mail-configuration',
        redirect: { name: 'settings.mail-config' },
      },
      {
        path: 'company-info',
        name: 'settings.company-info',
        meta: {
          requiresAuth: true,
          isOwner: true,
        },
        component: () => import('./views/CompanyInfoView.vue'),
      },
      {
        path: 'preferences',
        name: 'settings.preferences',
        meta: {
          requiresAuth: true,
          isOwner: true,
        },
        component: () => import('./views/PreferencesView.vue'),
      },
      {
        path: 'customization',
        name: 'settings.customization',
        meta: {
          requiresAuth: true,
          isOwner: true,
        },
        component: () => import('./views/CustomizationView.vue'),
      },
      {
        path: 'tax-types',
        name: 'settings.tax-types',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_TAX_TYPE,
        },
        component: () => import('./views/TaxTypesView.vue'),
      },
      {
        path: 'payment-modes',
        name: 'settings.payment-modes',
        meta: {
          requiresAuth: true,
        },
        component: () => import('./views/PaymentModesView.vue'),
      },
      {
        path: 'custom-fields',
        name: 'settings.custom-fields',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_CUSTOM_FIELDS,
        },
        component: () => import('./views/CustomFieldsView.vue'),
      },
      {
        path: 'notes',
        name: 'settings.notes',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_NOTE,
        },
        component: () => import('./views/NotesView.vue'),
      },
      {
        path: 'notifications',
        name: 'settings.notifications',
        meta: {
          requiresAuth: true,
          isOwner: true,
        },
        component: () => import('./views/NotificationsView.vue'),
      },
      {
        path: 'expense-categories',
        name: 'settings.expense-categories',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_EXPENSE,
        },
        component: () => import('./views/ExpenseCategoriesView.vue'),
      },
      {
        path: 'exchange-rate',
        name: 'settings.exchange-rate',
        meta: {
          requiresAuth: true,
          ability: ABILITIES.VIEW_EXCHANGE_RATE,
        },
        component: () => import('./views/ExchangeRateView.vue'),
      },
      {
        path: 'mail-config',
        name: 'settings.mail-config',
        meta: {
          requiresAuth: true,
          isOwner: true,
        },
        component: () => import('./views/MailConfigView.vue'),
      },
      {
        path: 'roles',
        name: 'settings.roles',
        meta: {
          requiresAuth: true,
          isOwner: true,
        },
        component: () => import('./views/RolesView.vue'),
      },
      {
        path: 'danger-zone',
        name: 'settings.danger-zone',
        meta: {
          requiresAuth: true,
          isOwner: true,
        },
        component: () => import('./views/DangerZoneView.vue'),
      },
    ],
  },
  {
    path: 'user-settings/:tab?',
    redirect: { name: 'settings.account' },
  },
  {
    path: 'settings/account-settings',
    redirect: { name: 'settings.account' },
  },
  {
    path: 'company-info',
    redirect: { name: 'settings.company-info' },
  },
  {
    path: 'preferences',
    redirect: { name: 'settings.preferences' },
  },
  {
    path: 'customization',
    redirect: { name: 'settings.customization' },
  },
  {
    path: 'notifications',
    redirect: { name: 'settings.notifications' },
  },
  {
    path: 'roles-settings',
    redirect: { name: 'settings.roles' },
  },
  {
    path: 'exchange-rate-provider',
    redirect: { name: 'settings.exchange-rate' },
  },
  {
    path: 'tax-types',
    redirect: { name: 'settings.tax-types' },
  },
  {
    path: 'payment-mode',
    redirect: { name: 'settings.payment-modes' },
  },
  {
    path: 'custom-fields',
    redirect: { name: 'settings.custom-fields' },
  },
  {
    path: 'notes',
    redirect: { name: 'settings.notes' },
  },
  {
    path: 'expense-category',
    redirect: { name: 'settings.expense-categories' },
  },
  {
    path: 'mail-configuration',
    redirect: { name: 'settings.mail-config' },
  },
]

export default settingsRoutes
