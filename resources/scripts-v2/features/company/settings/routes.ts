import type { RouteRecordRaw } from 'vue-router'

const settingsRoutes: RouteRecordRaw[] = [
  {
    path: 'settings',
    component: () => import('./views/SettingsLayoutView.vue'),
    children: [
      {
        path: '',
        redirect: 'company-info',
      },
      {
        path: 'account-settings',
        name: 'settings.account',
        component: () => import('./views/AccountSettingsView.vue'),
      },
      {
        path: 'company-info',
        name: 'settings.company-info',
        component: () => import('./views/CompanyInfoView.vue'),
      },
      {
        path: 'preferences',
        name: 'settings.preferences',
        component: () => import('./views/PreferencesView.vue'),
      },
      {
        path: 'customization',
        name: 'settings.customization',
        component: () => import('./views/CustomizationView.vue'),
      },
      {
        path: 'tax-types',
        name: 'settings.tax-types',
        component: () => import('./views/TaxTypesView.vue'),
      },
      {
        path: 'payment-modes',
        name: 'settings.payment-modes',
        component: () => import('./views/PaymentModesView.vue'),
      },
      {
        path: 'custom-fields',
        name: 'settings.custom-fields',
        component: () => import('./views/CustomFieldsView.vue'),
      },
      {
        path: 'notes',
        name: 'settings.notes',
        component: () => import('./views/NotesView.vue'),
      },
      {
        path: 'notifications',
        name: 'settings.notifications',
        component: () => import('./views/NotificationsView.vue'),
      },
      {
        path: 'expense-categories',
        name: 'settings.expense-categories',
        component: () => import('./views/ExpenseCategoriesView.vue'),
      },
      {
        path: 'exchange-rate',
        name: 'settings.exchange-rate',
        component: () => import('./views/ExchangeRateView.vue'),
      },
      {
        path: 'mail-config',
        name: 'settings.mail-config',
        component: () => import('./views/MailConfigView.vue'),
      },
      {
        path: 'roles',
        name: 'settings.roles',
        component: () => import('./views/RolesView.vue'),
      },
    ],
  },
]

export default settingsRoutes
