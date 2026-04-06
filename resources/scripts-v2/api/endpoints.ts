export const API = {
  // Authentication & Password Reset
  LOGIN: '/login',
  LOGOUT: '/auth/logout',
  FORGOT_PASSWORD: '/api/v1/auth/password/email',
  RESET_PASSWORD: '/api/v1/auth/reset/password',
  AUTH_CHECK: '/api/v1/auth/check',
  CSRF_COOKIE: '/sanctum/csrf-cookie',
  REGISTER_WITH_INVITATION: '/api/v1/auth/register-with-invitation',

  // Invitation Registration (public)
  INVITATION_DETAILS: '/api/v1/invitations', // append /{token}/details

  // Invitations (user-scoped)
  INVITATIONS_PENDING: '/api/v1/invitations/pending',
  INVITATIONS: '/api/v1/invitations', // append /{token}/accept or /{token}/decline

  // Bootstrap & General
  BOOTSTRAP: '/api/v1/bootstrap',
  CONFIG: '/api/v1/config',
  CURRENT_COMPANY: '/api/v1/current-company',
  SEARCH: '/api/v1/search',
  SEARCH_USERS: '/api/v1/search/user',
  APP_VERSION: '/api/v1/app/version',
  COUNTRIES: '/api/v1/countries',

  // Dashboard
  DASHBOARD: '/api/v1/dashboard',

  // Customers
  CUSTOMERS: '/api/v1/customers',
  CUSTOMERS_DELETE: '/api/v1/customers/delete',
  CUSTOMER_STATS: '/api/v1/customers', // append /{id}/stats

  // Items & Units
  ITEMS: '/api/v1/items',
  ITEMS_DELETE: '/api/v1/items/delete',
  UNITS: '/api/v1/units',

  // Invoices
  INVOICES: '/api/v1/invoices',
  INVOICES_DELETE: '/api/v1/invoices/delete',
  INVOICE_TEMPLATES: '/api/v1/invoices/templates',

  // Recurring Invoices
  RECURRING_INVOICES: '/api/v1/recurring-invoices',
  RECURRING_INVOICES_DELETE: '/api/v1/recurring-invoices/delete',
  RECURRING_INVOICE_FREQUENCY: '/api/v1/recurring-invoice-frequency',

  // Estimates
  ESTIMATES: '/api/v1/estimates',
  ESTIMATES_DELETE: '/api/v1/estimates/delete',
  ESTIMATE_TEMPLATES: '/api/v1/estimates/templates',

  // Expenses
  EXPENSES: '/api/v1/expenses',
  EXPENSES_DELETE: '/api/v1/expenses/delete',

  // Expense Categories
  CATEGORIES: '/api/v1/categories',

  // Payments
  PAYMENTS: '/api/v1/payments',
  PAYMENTS_DELETE: '/api/v1/payments/delete',
  PAYMENT_METHODS: '/api/v1/payment-methods',

  // Custom Fields
  CUSTOM_FIELDS: '/api/v1/custom-fields',

  // Notes
  NOTES: '/api/v1/notes',

  // Tax Types
  TAX_TYPES: '/api/v1/tax-types',

  // Roles & Abilities
  ROLES: '/api/v1/roles',
  ABILITIES: '/api/v1/abilities',

  // Company
  COMPANY: '/api/v1/company',
  COMPANY_UPLOAD_LOGO: '/api/v1/company/upload-logo',
  COMPANY_SETTINGS: '/api/v1/company/settings',
  COMPANY_HAS_TRANSACTIONS: '/api/v1/company/has-transactions',
  COMPANIES: '/api/v1/companies',
  COMPANIES_DELETE: '/api/v1/companies/delete',
  TRANSFER_OWNERSHIP: '/api/v1/transfer/ownership', // append /{userId}

  // Company Invitations (company-scoped)
  COMPANY_INVITATIONS: '/api/v1/company-invitations',

  // Members
  MEMBERS: '/api/v1/members',
  MEMBERS_DELETE: '/api/v1/members/delete',

  // User Profile & Settings
  ME: '/api/v1/me',
  ME_SETTINGS: '/api/v1/me/settings',
  ME_UPLOAD_AVATAR: '/api/v1/me/upload-avatar',

  // Global Settings (admin)
  SETTINGS: '/api/v1/settings',

  // Mail Configuration (global)
  MAIL_DRIVERS: '/api/v1/mail/drivers',
  MAIL_CONFIG: '/api/v1/mail/config',
  MAIL_TEST: '/api/v1/mail/test',

  // Company Mail Configuration
  COMPANY_MAIL_DEFAULT_CONFIG: '/api/v1/company/mail/config',
  COMPANY_MAIL_CONFIG: '/api/v1/company/mail/company-config',
  COMPANY_MAIL_TEST: '/api/v1/company/mail/company-test',

  // PDF Configuration
  PDF_DRIVERS: '/api/v1/pdf/drivers',
  PDF_CONFIG: '/api/v1/pdf/config',

  // Disks & Backups
  DISKS: '/api/v1/disks',
  DISK_DRIVERS: '/api/v1/disk/drivers',
  DISK_PURPOSES: '/api/v1/disk/purposes',
  BACKUPS: '/api/v1/backups',
  DOWNLOAD_BACKUP: '/api/v1/download-backup',

  // Fonts
  FONTS_STATUS: '/api/v1/fonts/status',
  FONTS_INSTALL: '/api/v1/fonts',

  // Exchange Rates & Currencies
  CURRENCIES: '/api/v1/currencies',
  CURRENCIES_USED: '/api/v1/currencies/used',
  CURRENCIES_BULK_UPDATE: '/api/v1/currencies/bulk-update-exchange-rate',
  EXCHANGE_RATE_PROVIDERS: '/api/v1/exchange-rate-providers',
  USED_CURRENCIES: '/api/v1/used-currencies',
  SUPPORTED_CURRENCIES: '/api/v1/supported-currencies',

  // Serial Numbers
  NEXT_NUMBER: '/api/v1/next-number',
  NUMBER_PLACEHOLDERS: '/api/v1/number-placeholders',

  // Formats
  TIMEZONES: '/api/v1/timezones',
  DATE_FORMATS: '/api/v1/date/formats',
  TIME_FORMATS: '/api/v1/time/formats',

  // Modules
  MODULES: '/api/v1/modules',
  MODULES_CHECK: '/api/v1/modules/check',
  MODULES_DOWNLOAD: '/api/v1/modules/download',
  MODULES_UPLOAD: '/api/v1/modules/upload',
  MODULES_UNZIP: '/api/v1/modules/unzip',
  MODULES_COPY: '/api/v1/modules/copy',
  MODULES_COMPLETE: '/api/v1/modules/complete',

  // Self Update
  CHECK_UPDATE: '/api/v1/check/update',
  UPDATE_DOWNLOAD: '/api/v1/update/download',
  UPDATE_UNZIP: '/api/v1/update/unzip',
  UPDATE_COPY: '/api/v1/update/copy',
  UPDATE_DELETE: '/api/v1/update/delete',
  UPDATE_CLEAN: '/api/v1/update/clean',
  UPDATE_MIGRATE: '/api/v1/update/migrate',
  UPDATE_FINISH: '/api/v1/update/finish',

  // Super Admin
  SUPER_ADMIN_DASHBOARD: '/api/v1/super-admin/dashboard',
  SUPER_ADMIN_COMPANIES: '/api/v1/super-admin/companies',
  SUPER_ADMIN_USERS: '/api/v1/super-admin/users',
  SUPER_ADMIN_STOP_IMPERSONATING: '/api/v1/super-admin/stop-impersonating',
} as const
