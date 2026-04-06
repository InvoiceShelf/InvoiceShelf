import { defineStore } from 'pinia'
import { client } from '@v2/api/client'
import type { Invoice } from '@v2/types/domain/invoice'
import type { Estimate, EstimateStatus } from '@v2/types/domain/estimate'
import type { Payment, PaymentMethod } from '@v2/types/domain/payment'
import type { Currency } from '@v2/types/domain/currency'
import type { Customer, Country } from '@v2/types/domain/customer'

// ----------------------------------------------------------------
// Types
// ----------------------------------------------------------------

export interface CustomerPortalMenuItem {
  title: string
  link: string
  icon?: string
  name?: string
}

export interface CustomerUserForm {
  avatar: string | number | null
  name: string
  email: string
  password: string
  confirm_password: string
  company: string
  billing: CustomerAddress
  shipping: CustomerAddress
}

export interface CustomerAddress {
  name: string | null
  address_street_1: string | null
  address_street_2: string | null
  city: string | null
  state: string | null
  country_id: number | null
  zip: string | null
  phone: string | null
  type: string
}

export interface DashboardData {
  recentInvoices: Invoice[]
  recentEstimates: Estimate[]
  invoiceCount: number
  estimateCount: number
  paymentCount: number
  totalDueAmount: number
}

export interface CustomerLoginData {
  email: string
  password: string
  device_name: string
  company: string
}

export interface PaginatedListParams {
  page?: number
  limit?: number | string
  orderByField?: string
  orderBy?: string
  [key: string]: unknown
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    last_page: number
    total: number
    [key: string]: unknown
  }
}

export interface CustomerPortalBootstrapMeta {
  menu: CustomerPortalMenuItem[]
  modules: string[]
  current_customer_currency?: Currency | null
  current_company_language?: string
}

export interface CustomerPortalBootstrapResponse {
  data: Customer
  meta: CustomerPortalBootstrapMeta
}

export interface CustomerPortalLoginResponse {
  success: boolean
}

export interface CustomerPortalForgotPasswordResponse {
  message: string
  data: string
}

export interface CustomerPortalResetPasswordResponse {
  message: string
}

// ----------------------------------------------------------------
// Address stub
// ----------------------------------------------------------------

function createAddressStub(type: string = 'billing'): CustomerAddress {
  return {
    name: null,
    address_street_1: null,
    address_street_2: null,
    city: null,
    state: null,
    country_id: null,
    zip: null,
    phone: null,
    type,
  }
}

function createUserFormStub(): CustomerUserForm {
  return {
    avatar: null,
    name: '',
    email: '',
    password: '',
    confirm_password: '',
    company: '',
    billing: createAddressStub('billing'),
    shipping: createAddressStub('shipping'),
  }
}

function createLoginDataStub(company: string = ''): CustomerLoginData {
  return {
    email: '',
    password: '',
    device_name: 'customer-portal-web',
    company,
  }
}

function normalizeAddress(
  address: Customer['billing'] | Customer['shipping'] | undefined,
  type: CustomerAddress['type'],
): CustomerAddress {
  return {
    name: address?.name ?? null,
    address_street_1: address?.address_street_1 ?? null,
    address_street_2: address?.address_street_2 ?? null,
    city: address?.city ?? null,
    state: address?.state ?? null,
    country_id: address?.country_id ?? null,
    zip: address?.zip ?? null,
    phone: address?.phone ?? null,
    type,
  }
}

function hydrateUserForm(customer: Customer): CustomerUserForm {
  return {
    avatar: customer.avatar ?? null,
    name: customer.name,
    email: customer.email ?? '',
    password: '',
    confirm_password: '',
    company: customer.company_name ?? customer.company?.name ?? '',
    billing: normalizeAddress(customer.billing, 'billing'),
    shipping: normalizeAddress(customer.shipping, 'shipping'),
  }
}

interface ResourceResponse<T> {
  data: T
}

// ----------------------------------------------------------------
// Helper to build customer API base URL
// ----------------------------------------------------------------

function customerApi(slug: string, path: string = ''): string {
  return `/api/v1/${slug}/customer${path}`
}

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

export interface CustomerPortalState {
  // Global
  companySlug: string
  isAppLoaded: boolean
  currentCompanyLanguage: string | null
  currency: Currency | null
  countries: Country[]
  currentUser: Customer | null
  mainMenu: CustomerPortalMenuItem[]
  enabledModules: string[]
  getDashboardDataLoaded: boolean

  // User form
  userForm: CustomerUserForm

  // Dashboard
  recentInvoices: Invoice[]
  recentEstimates: Estimate[]
  invoiceCount: number
  estimateCount: number
  paymentCount: number
  totalDueAmount: number

  // Invoices
  invoices: Invoice[]
  totalInvoices: number
  selectedViewInvoice: Invoice | null

  // Estimates
  estimates: Estimate[]
  totalEstimates: number
  selectedViewEstimate: Estimate | null

  // Payments
  payments: Payment[]
  totalPayments: number
  selectedViewPayment: Payment | null

  // Auth
  loginData: CustomerLoginData
}

export const useCustomerPortalStore = defineStore('customerPortal', {
  state: (): CustomerPortalState => ({
    companySlug: '',
    isAppLoaded: false,
    currentCompanyLanguage: null,
    currency: null,
    countries: [],
    currentUser: null,
    mainMenu: [],
    enabledModules: [],
    getDashboardDataLoaded: false,

    userForm: createUserFormStub(),

    recentInvoices: [],
    recentEstimates: [],
    invoiceCount: 0,
    estimateCount: 0,
    paymentCount: 0,
    totalDueAmount: 0,

    invoices: [],
    totalInvoices: 0,
    selectedViewInvoice: null,

    estimates: [],
    totalEstimates: 0,
    selectedViewEstimate: null,

    payments: [],
    totalPayments: 0,
    selectedViewPayment: null,

    loginData: createLoginDataStub(),
  }),

  actions: {
    // ---- Bootstrap ----

    resetState(companySlug: string = ''): void {
      this.$reset()
      this.companySlug = companySlug
      this.loginData = createLoginDataStub(companySlug)
    },

    async bootstrap(slug: string): Promise<CustomerPortalBootstrapResponse> {
      this.resetState(slug)

      const { data } = await client.get<CustomerPortalBootstrapResponse>(
        customerApi(slug, '/bootstrap'),
      )

      this.currentUser = data.data
      this.mainMenu = data.meta.menu ?? []
      this.currency = data.data.currency ?? data.meta.current_customer_currency ?? null
      this.enabledModules = data.meta.modules ?? []
      this.currentCompanyLanguage = data.meta.current_company_language ?? 'en'
      this.userForm = hydrateUserForm(data.data)
      this.isAppLoaded = true

      await window.loadLanguage?.(this.currentCompanyLanguage)

      return data
    },

    async fetchCountries(): Promise<Country[]> {
      if (this.countries.length) {
        return this.countries
      }

      const { data } = await client.get<ResourceResponse<Country[]>>(
        customerApi(this.companySlug, '/countries'),
      )
      this.countries = data.data
      return this.countries
    },

    // ---- Dashboard ----

    async loadDashboard(): Promise<void> {
      const { data } = await client.get(
        customerApi(this.companySlug, '/dashboard'),
      )
      this.totalDueAmount = data.due_amount
      this.estimateCount = data.estimate_count
      this.invoiceCount = data.invoice_count
      this.paymentCount = data.payment_count
      this.recentInvoices = data.recentInvoices
      this.recentEstimates = data.recentEstimates
      this.getDashboardDataLoaded = true
    },

    // ---- Invoices ----

    async fetchInvoices(
      params: PaginatedListParams,
    ): Promise<{ data: PaginatedResponse<Invoice> }> {
      const { data } = await client.get(
        customerApi(this.companySlug, '/invoices'),
        { params },
      )
      this.invoices = data.data
      if (data.meta?.invoiceTotalCount !== undefined) {
        this.totalInvoices = data.meta.invoiceTotalCount
      }
      return { data }
    },

    async fetchViewInvoice(id: number | string): Promise<{ data: { data: Invoice } }> {
      const { data } = await client.get(
        customerApi(this.companySlug, `/invoices/${id}`),
      )
      this.selectedViewInvoice = data.data
      return { data }
    },

    async searchInvoices(
      params: PaginatedListParams,
    ): Promise<Invoice[]> {
      const { data } = await client.get(
        customerApi(this.companySlug, '/invoices'),
        { params },
      )
      this.invoices = data.data ?? data
      return this.invoices
    },

    // ---- Estimates ----

    async fetchEstimates(
      params: PaginatedListParams,
    ): Promise<{ data: PaginatedResponse<Estimate> }> {
      const { data } = await client.get(
        customerApi(this.companySlug, '/estimates'),
        { params },
      )
      this.estimates = data.data
      if (data.meta?.estimateTotalCount !== undefined) {
        this.totalEstimates = data.meta.estimateTotalCount
      }
      return { data }
    },

    async fetchViewEstimate(id: number | string): Promise<{ data: { data: Estimate } }> {
      const { data } = await client.get(
        customerApi(this.companySlug, `/estimates/${id}`),
      )
      this.selectedViewEstimate = data.data
      return { data }
    },

    async searchEstimates(
      params: PaginatedListParams,
    ): Promise<Estimate[]> {
      const { data } = await client.get(
        customerApi(this.companySlug, '/estimates'),
        { params },
      )
      this.estimates = data.data ?? data
      return this.estimates
    },

    async updateEstimateStatus(
      id: number | string,
      status: EstimateStatus,
    ): Promise<void> {
      await client.post(
        customerApi(this.companySlug, `/estimate/${id}/status`),
        { status },
      )
      const pos = this.estimates.findIndex((e) => e.id === Number(id))
      if (pos !== -1) {
        this.estimates[pos].status = status
      }
    },

    // ---- Payments ----

    async fetchPayments(
      params: PaginatedListParams,
    ): Promise<{ data: PaginatedResponse<Payment> }> {
      const { data } = await client.get(
        customerApi(this.companySlug, '/payments'),
        { params },
      )
      this.payments = data.data
      if (data.meta?.paymentTotalCount !== undefined) {
        this.totalPayments = data.meta.paymentTotalCount
      }
      return { data }
    },

    async fetchViewPayment(id: number | string): Promise<{ data: { data: Payment } }> {
      const { data } = await client.get(
        customerApi(this.companySlug, `/payments/${id}`),
      )
      this.selectedViewPayment = data.data
      return { data }
    },

    async searchPayments(
      params: PaginatedListParams,
    ): Promise<Payment[]> {
      const { data } = await client.get(
        customerApi(this.companySlug, '/payments'),
        { params },
      )
      this.payments = data.data ?? data
      return this.payments
    },

    async fetchPaymentModes(search: string): Promise<PaymentMethod[]> {
      const { data } = await client.get(
        customerApi(this.companySlug, '/payment-method'),
        { params: search ? { search } : {} },
      )
      return data.data
    },

    // ---- User / Settings ----

    async fetchCurrentUser(): Promise<void> {
      const { data } = await client.get<ResourceResponse<Customer>>(
        customerApi(this.companySlug, '/me'),
      )
      this.currentUser = data.data
      this.userForm = hydrateUserForm(data.data)
    },

    async updateCurrentUser(formData: FormData): Promise<{ data: ResourceResponse<Customer> }> {
      const { data } = await client.post<ResourceResponse<Customer>>(
        customerApi(this.companySlug, '/profile'),
        formData,
      )
      this.currentUser = data.data
      this.userForm = hydrateUserForm(data.data)
      return { data }
    },

    copyBillingToShipping(): void {
      this.userForm.shipping = {
        ...this.userForm.billing,
        type: 'shipping',
      }
    },

    // ---- Auth ----

    async login(loginData: CustomerLoginData): Promise<CustomerPortalLoginResponse> {
      await client.get('/sanctum/csrf-cookie')
      const { data } = await client.post<CustomerPortalLoginResponse>(
        `/${loginData.company}/customer/login`,
        loginData,
      )
      this.loginData.email = ''
      this.loginData.password = ''
      this.loginData.company = loginData.company
      return data
    },

    async forgotPassword(payload: {
      email: string
      company: string
    }): Promise<CustomerPortalForgotPasswordResponse> {
      const { data } = await client.post<CustomerPortalForgotPasswordResponse>(
        `/api/v1/${payload.company}/customer/auth/password/email`,
        payload,
      )
      return data
    },

    async resetPassword(
      payload: { email: string; password: string; password_confirmation: string; token: string },
      company: string,
    ): Promise<CustomerPortalResetPasswordResponse> {
      const { data } = await client.post<CustomerPortalResetPasswordResponse>(
        `/api/v1/${company}/customer/auth/reset/password`,
        payload,
      )
      return data
    },

    async logout(): Promise<void> {
      const companySlug = this.companySlug

      try {
        await client.post(`/${companySlug}/customer/logout`)
      } finally {
        this.resetState(companySlug)
      }
    },
  },
})

export type CustomerPortalStore = ReturnType<typeof useCustomerPortalStore>
