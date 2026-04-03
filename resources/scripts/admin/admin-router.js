import abilities from '@/scripts/admin/stub/abilities'

const LayoutInstallation = () =>
  import('@/scripts/admin/layouts/LayoutInstallation.vue')

const Login = () => import('@/scripts/admin/views/auth/Login.vue')
const LayoutBasic = () => import('@/scripts/admin/layouts/LayoutBasic.vue')
const LayoutLogin = () => import('@/scripts/admin/layouts/LayoutLogin.vue')
const ResetPassword = () =>
  import('@/scripts/admin/views/auth/ResetPassword.vue')
const ForgotPassword = () =>
  import('@/scripts/admin/views/auth/ForgotPassword.vue')

// Dashboard
const Dashboard = () => import('@/scripts/admin/views/dashboard/Dashboard.vue')

// Customers
const CustomerIndex = () => import('@/scripts/admin/views/customers/Index.vue')
const CustomerCreate = () =>
  import('@/scripts/admin/views/customers/Create.vue')
const CustomerView = () => import('@/scripts/admin/views/customers/View.vue')

//Settings
const SettingsIndex = () =>
  import('@/scripts/admin/views/settings/SettingsIndex.vue')
const UserSettingsIndex = () =>
  import('@/scripts/admin/views/user-settings/UserSettingsIndex.vue')
const UserSettingsGeneral = () =>
  import('@/scripts/admin/views/user-settings/GeneralTab.vue')
const UserSettingsProfilePhoto = () =>
  import('@/scripts/admin/views/user-settings/ProfilePhotoTab.vue')
const UserSettingsSecurity = () =>
  import('@/scripts/admin/views/user-settings/SecurityTab.vue')
const CompanyInfo = () =>
  import('@/scripts/admin/views/settings/CompanyInfoSettings.vue')
const Preferences = () =>
  import('@/scripts/admin/views/settings/PreferencesSetting.vue')
const Customization = () =>
  import(
    '@/scripts/admin/views/settings/customization/CustomizationSetting.vue'
  )
const Notifications = () =>
  import('@/scripts/admin/views/settings/NotificationsSetting.vue')
const TaxTypes = () =>
  import('@/scripts/admin/views/settings/TaxTypesSetting.vue')
const PaymentMode = () =>
  import('@/scripts/admin/views/settings/PaymentsModeSetting.vue')
const CustomFieldsIndex = () =>
  import('@/scripts/admin/views/settings/CustomFieldsSetting.vue')
const NotesSetting = () =>
  import('@/scripts/admin/views/settings/NotesSetting.vue')
const ExpenseCategory = () =>
  import('@/scripts/admin/views/settings/ExpenseCategorySetting.vue')
const ExchangeRateSetting = () =>
  import('@/scripts/admin/views/settings/ExchangeRateProviderSetting.vue')
const RolesSettings = () =>
  import('@/scripts/admin/views/settings/RolesSettings.vue')
const CompanyMailConfig = () =>
  import('@/scripts/admin/views/settings/CompanyMailConfigSetting.vue')

// Items
const ItemsIndex = () => import('@/scripts/admin/views/items/Index.vue')
const ItemCreate = () => import('@/scripts/admin/views/items/Create.vue')

// Expenses
const ExpensesIndex = () => import('@/scripts/admin/views/expenses/Index.vue')
const ExpenseCreate = () => import('@/scripts/admin/views/expenses/Create.vue')

// Members
const MemberIndex = () => import('@/scripts/admin/views/members/Index.vue')
const MemberCreate = () => import('@/scripts/admin/views/members/Create.vue')

// Estimates
const EstimateIndex = () => import('@/scripts/admin/views/estimates/Index.vue')
const EstimateCreate = () =>
  import('@/scripts/admin/views/estimates/create/EstimateCreate.vue')
const EstimateView = () => import('@/scripts/admin/views/estimates/View.vue')

// Payments
const PaymentsIndex = () => import('@/scripts/admin/views/payments/Index.vue')
const PaymentCreate = () => import('@/scripts/admin/views/payments/Create.vue')
const PaymentView = () => import('@/scripts/admin/views/payments/View.vue')

const NotFoundPage = () => import('@/scripts/admin/views/errors/404.vue')

// Invoice
const InvoiceIndex = () => import('@/scripts/admin/views/invoices/Index.vue')
const InvoiceCreate = () =>
  import('@/scripts/admin/views/invoices/create/InvoiceCreate.vue')
const InvoiceView = () => import('@/scripts/admin/views/invoices/View.vue')

// Recurring Invoice
const RecurringInvoiceIndex = () =>
  import('@/scripts/admin/views/recurring-invoices/Index.vue')
const RecurringInvoiceCreate = () =>
  import(
    '@/scripts/admin/views/recurring-invoices/create/RecurringInvoiceCreate.vue'
  )
const RecurringInvoiceView = () =>
  import('@/scripts/admin/views/recurring-invoices/View.vue')

// Reports
const ReportsIndex = () =>
  import('@/scripts/admin/views/reports/layout/Index.vue')

// Installation
const Installation = () =>
  import('@/scripts/admin/views/installation/Installation.vue')

// Modules
const ModuleIndex = () => import('@/scripts/admin/views/modules/Index.vue')

const ModuleView = () => import('@/scripts/admin/views/modules/View.vue')
const InvoicePublicPage = () =>
  import('@/scripts/components/InvoicePublicPage.vue')

// Administration (Super Admin)
const AdminCompaniesIndex = () =>
  import('@/scripts/admin/views/administration/companies/Index.vue')
const AdminCompaniesEdit = () =>
  import('@/scripts/admin/views/administration/companies/Edit.vue')
const AdminUsersIndex = () =>
  import('@/scripts/admin/views/administration/users/Index.vue')
const AdminUsersEdit = () =>
  import('@/scripts/admin/views/administration/users/Edit.vue')
const AdminSettingsIndex = () =>
  import('@/scripts/admin/views/administration/settings/SettingsIndex.vue')
const AdminMailConfig = () =>
  import('@/scripts/admin/views/settings/MailConfigSetting.vue')
const AdminPDFGeneration = () =>
  import('@/scripts/admin/views/settings/PDFGenerationSetting.vue')
const AdminBackup = () =>
  import('@/scripts/admin/views/settings/BackupSetting.vue')
const AdminUpdateApp = () =>
  import('@/scripts/admin/views/settings/UpdateAppSetting.vue')
const AdminFileDisk = () =>
  import('@/scripts/admin/views/settings/FileDiskSetting.vue')

export default [
  {
    path: '/installation',
    component: LayoutInstallation,
    meta: { requiresAuth: false },
    children: [
      {
        path: '/installation',
        component: Installation,
        name: 'installation',
      },
    ],
  },

  {
    path: '/customer/invoices/view/:hash',
    component: InvoicePublicPage,
    name: 'invoice.public',
  },

  {
    path: '/',
    component: LayoutLogin,
    meta: { requiresAuth: false, redirectIfAuthenticated: true },
    children: [
      {
        path: '',
        component: Login,
      },
      {
        path: 'login',
        name: 'login',
        component: Login,
      },
      {
        path: 'forgot-password',
        component: ForgotPassword,
        name: 'forgot-password',
      },
      {
        path: '/reset-password/:token',
        component: ResetPassword,
        name: 'reset-password',
      },
    ],
  },
  {
    path: '/admin',
    component: LayoutBasic,
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'dashboard',
        meta: { ability: abilities.DASHBOARD },
        component: Dashboard,
      },

      // Customers
      {
        path: 'customers',
        meta: { ability: abilities.VIEW_CUSTOMER },
        component: CustomerIndex,
      },
      {
        path: 'customers/create',
        name: 'customers.create',
        meta: { ability: abilities.CREATE_CUSTOMER },
        component: CustomerCreate,
      },
      {
        path: 'customers/:id/edit',
        name: 'customers.edit',
        meta: { ability: abilities.EDIT_CUSTOMER },
        component: CustomerCreate,
      },
      {
        path: 'customers/:id/view',
        name: 'customers.view',
        meta: { ability: abilities.VIEW_CUSTOMER },
        component: CustomerView,
      },
      // Payments
      {
        path: 'payments',
        meta: { ability: abilities.VIEW_PAYMENT },
        component: PaymentsIndex,
      },
      {
        path: 'payments/create',
        name: 'payments.create',
        meta: { ability: abilities.CREATE_PAYMENT },
        component: PaymentCreate,
      },
      {
        path: 'payments/:id/create',
        name: 'invoice.payments.create',
        meta: { ability: abilities.CREATE_PAYMENT },
        component: PaymentCreate,
      },
      {
        path: 'payments/:id/edit',
        name: 'payments.edit',
        meta: { ability: abilities.EDIT_PAYMENT },
        component: PaymentCreate,
      },
      {
        path: 'payments/:id/view',
        name: 'payments.view',
        meta: { ability: abilities.VIEW_PAYMENT },
        component: PaymentView,
      },

      // user settings
      {
        path: 'user-settings',
        name: 'user.settings',
        component: UserSettingsIndex,
        children: [
          {
            path: 'general',
            name: 'user.settings.general',
            component: UserSettingsGeneral,
          },
          {
            path: 'profile-photo',
            name: 'user.settings.profile-photo',
            component: UserSettingsProfilePhoto,
          },
          {
            path: 'security',
            name: 'user.settings.security',
            component: UserSettingsSecurity,
          },
        ],
      },

      //settings
      {
        path: 'settings',
        name: 'settings',
        component: SettingsIndex,
        children: [
          {
            path: 'company-info',
            name: 'company.info',
            meta: { isOwner: true },
            component: CompanyInfo,
          },
          {
            path: 'preferences',
            name: 'preferences',
            meta: { isOwner: true },
            component: Preferences,
          },
          {
            path: 'customization',
            name: 'customization',
            meta: { isOwner: true },
            component: Customization,
          },
          {
            path: 'notifications',
            name: 'notifications',
            meta: { isOwner: true },
            component: Notifications,
          },
          {
            path: 'roles-settings',
            name: 'roles.settings',
            meta: { isOwner: true },
            component: RolesSettings,
          },
          {
            path: 'exchange-rate-provider',
            name: 'exchange.rate.provider',
            meta: { ability: abilities.VIEW_EXCHANGE_RATE },
            component: ExchangeRateSetting,
          },
          {
            path: 'tax-types',
            name: 'tax.types',
            meta: { ability: abilities.VIEW_TAX_TYPE },
            component: TaxTypes,
          },
          {
            path: 'notes',
            name: 'notes',
            meta: { ability: abilities.VIEW_ALL_NOTES },
            component: NotesSetting,
          },
          {
            path: 'payment-mode',
            name: 'payment.mode',
            component: PaymentMode,
          },
          {
            path: 'custom-fields',
            name: 'custom.fields',
            meta: { ability: abilities.VIEW_CUSTOM_FIELDS },
            component: CustomFieldsIndex,
          },
          {
            path: 'expense-category',
            name: 'expense.category',
            meta: { ability: abilities.VIEW_EXPENSE },
            component: ExpenseCategory,
          },
          {
            path: 'mail-configuration',
            name: 'company.mailconfig',
            meta: { isOwner: true },
            component: CompanyMailConfig,
          },
        ],
      },

      // Items
      {
        path: 'items',
        meta: { ability: abilities.VIEW_ITEM },
        component: ItemsIndex,
      },
      {
        path: 'items/create',
        name: 'items.create',
        meta: { ability: abilities.CREATE_ITEM },
        component: ItemCreate,
      },
      {
        path: 'items/:id/edit',
        name: 'items.edit',
        meta: { ability: abilities.EDIT_ITEM },
        component: ItemCreate,
      },

      // Expenses
      {
        path: 'expenses',
        meta: { ability: abilities.VIEW_EXPENSE },
        component: ExpensesIndex,
      },
      {
        path: 'expenses/create',
        name: 'expenses.create',
        meta: { ability: abilities.CREATE_EXPENSE },
        component: ExpenseCreate,
      },
      {
        path: 'expenses/:id/edit',
        name: 'expenses.edit',
        meta: { ability: abilities.EDIT_EXPENSE },
        component: ExpenseCreate,
      },

      // Members
      {
        path: 'members',
        name: 'members.index',
        meta: { isOwner: true },
        component: MemberIndex,
      },
      {
        path: 'members/create',
        meta: { isOwner: true },
        name: 'members.create',
        component: MemberCreate,
      },
      {
        path: 'members/:id/edit',
        name: 'members.edit',
        meta: { isOwner: true },
        component: MemberCreate,
      },

      // Estimates
      {
        path: 'estimates',
        name: 'estimates.index',
        meta: { ability: abilities.VIEW_ESTIMATE },
        component: EstimateIndex,
      },
      {
        path: 'estimates/create',
        name: 'estimates.create',
        meta: { ability: abilities.CREATE_ESTIMATE },
        component: EstimateCreate,
      },
      {
        path: 'estimates/:id/view',
        name: 'estimates.view',
        meta: { ability: abilities.VIEW_ESTIMATE },
        component: EstimateView,
      },
      {
        path: 'estimates/:id/edit',
        name: 'estimates.edit',
        meta: { ability: abilities.EDIT_ESTIMATE },
        component: EstimateCreate,
      },

      // Invoices
      {
        path: 'invoices',
        name: 'invoices.index',
        meta: { ability: abilities.VIEW_INVOICE },
        component: InvoiceIndex,
      },
      {
        path: 'invoices/create',
        name: 'invoices.create',
        meta: { ability: abilities.CREATE_INVOICE },
        component: InvoiceCreate,
      },
      {
        path: 'invoices/:id/view',
        name: 'invoices.view',
        meta: { ability: abilities.VIEW_INVOICE },
        component: InvoiceView,
      },
      {
        path: 'invoices/:id/edit',
        name: 'invoices.edit',
        meta: { ability: abilities.EDIT_INVOICE },
        component: InvoiceCreate,
      },

      // Recurring Invoices
      {
        path: 'recurring-invoices',
        name: 'recurring-invoices.index',
        meta: { ability: abilities.VIEW_RECURRING_INVOICE },
        component: RecurringInvoiceIndex,
      },
      {
        path: 'recurring-invoices/create',
        name: 'recurring-invoices.create',
        meta: { ability: abilities.CREATE_RECURRING_INVOICE },
        component: RecurringInvoiceCreate,
      },
      {
        path: 'recurring-invoices/:id/view',
        name: 'recurring-invoices.view',
        meta: { ability: abilities.VIEW_RECURRING_INVOICE },
        component: RecurringInvoiceView,
      },
      {
        path: 'recurring-invoices/:id/edit',
        name: 'recurring-invoices.edit',
        meta: { ability: abilities.EDIT_RECURRING_INVOICE },
        component: RecurringInvoiceCreate,
      },

      // Modules
      {
        path: 'modules',
        name: 'modules.index',
        meta: { isOwner: true },
        component: ModuleIndex,
      },

      {
        path: 'modules/:slug',
        name: 'modules.view',
        meta: { isOwner: true },
        component: ModuleView,
      },

      // Reports
      {
        path: 'reports',
        meta: { ability: abilities.VIEW_FINANCIAL_REPORT },
        component: ReportsIndex,
      },

      // Administration (Super Admin)
      {
        path: 'administration/companies',
        name: 'admin.companies.index',
        meta: { isSuperAdmin: true },
        component: AdminCompaniesIndex,
      },
      {
        path: 'administration/companies/:id/edit',
        name: 'admin.companies.edit',
        meta: { isSuperAdmin: true },
        component: AdminCompaniesEdit,
      },
      {
        path: 'administration/users',
        name: 'admin.users.index',
        meta: { isSuperAdmin: true },
        component: AdminUsersIndex,
      },
      {
        path: 'administration/users/:id/edit',
        name: 'admin.users.edit',
        meta: { isSuperAdmin: true },
        component: AdminUsersEdit,
      },
      {
        path: 'administration/settings',
        name: 'admin.settings',
        meta: { isSuperAdmin: true },
        component: AdminSettingsIndex,
        children: [
          {
            path: 'mail-configuration',
            name: 'admin.settings.mail',
            component: AdminMailConfig,
          },
          {
            path: 'pdf-generation',
            name: 'admin.settings.pdf',
            component: AdminPDFGeneration,
          },
          {
            path: 'backup',
            name: 'admin.settings.backup',
            component: AdminBackup,
          },
          {
            path: 'update-app',
            name: 'admin.settings.update',
            component: AdminUpdateApp,
          },
          {
            path: 'file-disk',
            name: 'admin.settings.filedisk',
            component: AdminFileDisk,
          },
        ],
      },
    ],
  },
  { path: '/:catchAll(.*)', component: NotFoundPage },
]
