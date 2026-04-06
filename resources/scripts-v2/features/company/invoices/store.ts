import { defineStore } from 'pinia'
import { useNotificationStore } from '../../../stores/notification.store'
import { useCompanyStore } from '../../../stores/company.store'
import { invoiceService } from '../../../api/services/invoice.service'
import type {
  InvoiceListParams,
  InvoiceListResponse,
  SendInvoicePayload,
  InvoiceStatusPayload,
  InvoiceTemplate,
} from '../../../api/services/invoice.service'
import type { Invoice, InvoiceItem, DiscountType } from '../../../types/domain/invoice'
import type { Tax, TaxType } from '../../../types/domain/tax'
import type { Currency } from '../../../types/domain/currency'
import type { Customer } from '../../../types/domain/customer'
import type { Note } from '../../../types/domain/note'
import type { CustomFieldValue } from '../../../types/domain/custom-field'
import type { DocumentTax, DocumentItem } from '../../shared/document-form/use-document-calculations'
import { generateClientId } from '../../../utils'

// ----------------------------------------------------------------
// Stub factories
// ----------------------------------------------------------------

function createTaxStub(): DocumentTax {
  return {
    id: generateClientId(),
    name: '',
    tax_type_id: 0,
    type: 'GENERAL',
    amount: null,
    percent: null,
    compound_tax: false,
    calculation_type: null,
    fixed_amount: 0,
  }
}

function createInvoiceItemStub(): DocumentItem {
  return {
    id: generateClientId(),
    invoice_id: null,
    item_id: null,
    name: '',
    description: null,
    quantity: 1,
    price: 0,
    discount_type: 'fixed',
    discount_val: 0,
    discount: 0,
    total: 0,
    totalTax: 0,
    totalSimpleTax: 0,
    totalCompoundTax: 0,
    tax: 0,
    taxes: [createTaxStub()],
    unit_name: null,
  }
}

export interface InvoiceFormData {
  id: number | null
  invoice_number: string
  customer: Customer | null
  customer_id: number | null
  template_name: string | null
  invoice_date: string
  due_date: string
  notes: string | null
  discount: number
  discount_type: DiscountType
  discount_val: number
  reference_number: string | null
  tax: number
  sub_total: number
  total: number
  tax_per_item: string | null
  tax_included: boolean
  sales_tax_type: string | null
  sales_tax_address_type: string | null
  discount_per_item: string | null
  taxes: DocumentTax[]
  items: DocumentItem[]
  customFields: CustomFieldValue[]
  fields: CustomFieldValue[]
  selectedNote: Note | null
  selectedCurrency: Currency | Record<string, unknown> | string
  unique_hash?: string
  exchange_rate?: number | null
  currency_id?: number
}

function createInvoiceStub(): InvoiceFormData {
  return {
    id: null,
    invoice_number: '',
    customer: null,
    customer_id: null,
    template_name: null,
    invoice_date: '',
    due_date: '',
    notes: '',
    discount: 0,
    discount_type: 'fixed',
    discount_val: 0,
    reference_number: null,
    tax: 0,
    sub_total: 0,
    total: 0,
    tax_per_item: null,
    tax_included: false,
    sales_tax_type: null,
    sales_tax_address_type: null,
    discount_per_item: null,
    taxes: [],
    items: [createInvoiceItemStub()],
    customFields: [],
    fields: [],
    selectedNote: null,
    selectedCurrency: '',
  }
}

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

export interface InvoiceState {
  templates: InvoiceTemplate[]
  invoices: Invoice[]
  selectedInvoices: number[]
  selectAllField: boolean
  invoiceTotalCount: number
  showExchangeRate: boolean
  isFetchingInitialSettings: boolean
  isFetchingInvoice: boolean
  newInvoice: InvoiceFormData
}

export const useInvoiceStore = defineStore('invoice', {
  state: (): InvoiceState => ({
    templates: [],
    invoices: [],
    selectedInvoices: [],
    selectAllField: false,
    invoiceTotalCount: 0,
    showExchangeRate: false,
    isFetchingInitialSettings: false,
    isFetchingInvoice: false,
    newInvoice: createInvoiceStub(),
  }),

  getters: {
    getInvoice:
      (state) =>
      (id: number): Invoice | undefined => {
        return state.invoices.find((invoice) => invoice.id === id)
      },

    getSubTotal(state): number {
      return state.newInvoice.items.reduce(
        (sum: number, item: DocumentItem) => sum + (item.total ?? 0),
        0,
      )
    },

    getNetTotal(): number {
      return this.getSubtotalWithDiscount - this.getTotalTax
    },

    getTotalSimpleTax(state): number {
      return state.newInvoice.taxes.reduce(
        (sum: number, tax: DocumentTax) => {
          if (!tax.compound_tax) return sum + (tax.amount ?? 0)
          return sum
        },
        0,
      )
    },

    getTotalCompoundTax(state): number {
      return state.newInvoice.taxes.reduce(
        (sum: number, tax: DocumentTax) => {
          if (tax.compound_tax) return sum + (tax.amount ?? 0)
          return sum
        },
        0,
      )
    },

    getTotalTax(): number {
      if (
        this.newInvoice.tax_per_item === 'NO' ||
        this.newInvoice.tax_per_item === null
      ) {
        return this.getTotalSimpleTax + this.getTotalCompoundTax
      }
      return this.newInvoice.items.reduce(
        (sum: number, item: DocumentItem) => sum + (item.tax ?? 0),
        0,
      )
    },

    getSubtotalWithDiscount(): number {
      return this.getSubTotal - this.newInvoice.discount_val
    },

    getTotal(): number {
      if (this.newInvoice.tax_included) {
        return this.getSubtotalWithDiscount
      }
      return this.getSubtotalWithDiscount + this.getTotalTax
    },

    isEdit(state): boolean {
      return !!state.newInvoice.id
    },
  },

  actions: {
    resetCurrentInvoice(): void {
      this.newInvoice = createInvoiceStub()
    },

    async previewInvoice(params: SendInvoicePayload): Promise<unknown> {
      return invoiceService.sendPreview(params)
    },

    async fetchInvoices(
      params: InvoiceListParams & { invoice_number?: string },
    ): Promise<{ data: InvoiceListResponse }> {
      const response = await invoiceService.list(params)
      this.invoices = response.data
      this.invoiceTotalCount = response.meta.invoice_total_count
      return { data: response }
    },

    async fetchInvoice(id: number): Promise<{ data: { data: Invoice } }> {
      const response = await invoiceService.get(id)
      this.setInvoiceData(response.data)
      this.setCustomerAddresses(this.newInvoice.customer)
      return { data: response }
    },

    setInvoiceData(invoice: Invoice): void {
      Object.assign(this.newInvoice, invoice)

      if (this.newInvoice.tax_per_item === 'YES') {
        this.newInvoice.items.forEach((item) => {
          if (item.taxes && !item.taxes.length) {
            item.taxes.push(createTaxStub())
          }
        })
      }

      if (this.newInvoice.discount_per_item === 'YES') {
        this.newInvoice.items.forEach((item, index) => {
          if (item.discount_type === 'fixed') {
            this.newInvoice.items[index].discount = item.discount / 100
          }
        })
      } else {
        if (this.newInvoice.discount_type === 'fixed') {
          this.newInvoice.discount = this.newInvoice.discount / 100
        }
      }
    },

    setCustomerAddresses(customer: Customer | null): void {
      if (!customer) return
      const business = (customer as Record<string, unknown>).customer_business as
        | Record<string, unknown>
        | undefined

      if (business?.billing_address) {
        ;(this.newInvoice.customer as Record<string, unknown>).billing_address =
          business.billing_address
      }
      if (business?.shipping_address) {
        ;(this.newInvoice.customer as Record<string, unknown>).shipping_address =
          business.shipping_address
      }
    },

    addSalesTaxUs(taxTypes: TaxType[]): void {
      const salesTax = createTaxStub()
      const found = this.newInvoice.taxes.find(
        (t) => t.name === 'Sales Tax' && t.type === 'MODULE',
      )
      if (found) {
        for (const key in found) {
          if (Object.prototype.hasOwnProperty.call(salesTax, key)) {
            ;(salesTax as Record<string, unknown>)[key] = (
              found as Record<string, unknown>
            )[key]
          }
        }
        salesTax.id = found.tax_type_id
        taxTypes.push(salesTax as unknown as TaxType)
      }
    },

    async sendInvoice(data: SendInvoicePayload): Promise<unknown> {
      const response = await invoiceService.send(data)
      return response
    },

    async addInvoice(data: Record<string, unknown>): Promise<{ data: { data: Invoice } }> {
      const response = await invoiceService.create(data as never)
      this.invoices = [...this.invoices, response.data]

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'invoices.created_message',
      })

      return { data: response }
    },

    async deleteInvoice(payload: { ids: number[] }): Promise<{ data: { success: boolean } }> {
      const response = await invoiceService.delete(payload)
      const id = payload.ids[0]
      const index = this.invoices.findIndex((inv) => inv.id === id)
      if (index !== -1) {
        this.invoices.splice(index, 1)
      }
      return { data: response }
    },

    async deleteMultipleInvoices(): Promise<{ data: { success: boolean } }> {
      const response = await invoiceService.delete({ ids: this.selectedInvoices })
      this.selectedInvoices.forEach((invoiceId) => {
        const index = this.invoices.findIndex((inv) => inv.id === invoiceId)
        if (index !== -1) {
          this.invoices.splice(index, 1)
        }
      })
      this.selectedInvoices = []
      return { data: response }
    },

    async updateInvoice(data: Record<string, unknown>): Promise<{ data: { data: Invoice } }> {
      const response = await invoiceService.update(data.id as number, data as never)
      const pos = this.invoices.findIndex((inv) => inv.id === response.data.id)
      if (pos !== -1) {
        this.invoices[pos] = response.data
      }

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'invoices.updated_message',
      })

      return { data: response }
    },

    async cloneInvoice(data: { id: number }): Promise<{ data: { data: Invoice } }> {
      const response = await invoiceService.clone(data.id)
      return { data: response }
    },

    async markAsSent(data: InvoiceStatusPayload): Promise<unknown> {
      const response = await invoiceService.changeStatus(data)
      const pos = this.invoices.findIndex((inv) => inv.id === data.id)
      if (pos !== -1 && this.invoices[pos]) {
        this.invoices[pos].status = 'SENT' as Invoice['status']
      }
      return response
    },

    async getNextNumber(
      params?: Record<string, unknown>,
      setState = false,
    ): Promise<{ data: { nextNumber: string } }> {
      const response = await invoiceService.getNextNumber(params as never)
      if (setState) {
        this.newInvoice.invoice_number = response.nextNumber
      }
      return { data: response }
    },

    async searchInvoice(queryString: string): Promise<unknown> {
      return invoiceService.list(
        Object.fromEntries(new URLSearchParams(queryString)) as never,
      )
    },

    selectInvoice(data: number[]): void {
      this.selectedInvoices = data
      this.selectAllField =
        this.selectedInvoices.length === this.invoices.length
    },

    selectAllInvoices(): void {
      if (this.selectedInvoices.length === this.invoices.length) {
        this.selectedInvoices = []
        this.selectAllField = false
      } else {
        this.selectedInvoices = this.invoices.map((inv) => inv.id)
        this.selectAllField = true
      }
    },

    async selectCustomer(id: number): Promise<unknown> {
      const { customerService } = await import(
        '../../../api/services/customer.service'
      )
      const response = await customerService.get(id)
      this.newInvoice.customer = response.data as unknown as Customer
      this.newInvoice.customer_id = response.data.id
      if (response.data.currency) {
        this.newInvoice.currency_id = (response.data.currency as { id: number }).id
      }
      return response
    },

    async fetchInvoiceTemplates(): Promise<{ data: { invoiceTemplates: InvoiceTemplate[] } }> {
      const response = await invoiceService.getTemplates()
      this.templates = response.invoiceTemplates
      return { data: response }
    },

    selectNote(data: Note): void {
      this.newInvoice.selectedNote = null
      this.newInvoice.selectedNote = data
    },

    setTemplate(name: string): void {
      this.newInvoice.template_name = name
    },

    resetSelectedCustomer(): void {
      this.newInvoice.customer = null
      this.newInvoice.customer_id = null
    },

    addItem(): void {
      this.newInvoice.items.push(createInvoiceItemStub())
    },

    updateItem(data: DocumentItem & { index: number }): void {
      Object.assign(this.newInvoice.items[data.index], { ...data })
    },

    removeItem(index: number): void {
      this.newInvoice.items.splice(index, 1)
    },

    deselectItem(index: number): void {
      this.newInvoice.items[index] = createInvoiceItemStub()
    },

    resetSelectedNote(): void {
      this.newInvoice.selectedNote = null
    },

    async fetchInvoiceInitialSettings(
      isEdit: boolean,
      routeParams?: { id?: string; query?: Record<string, string> },
      companySettingsParam?: Record<string, string>,
      companyCurrency?: Currency,
      userSettings?: Record<string, string>,
    ): Promise<void> {
      this.isFetchingInitialSettings = true

      const companyStore = useCompanyStore()
      const companySettings = companySettingsParam ?? companyStore.selectedCompanySettings

      if (companyCurrency || companyStore.selectedCompanyCurrency) {
        this.newInvoice.selectedCurrency = companyCurrency ?? companyStore.selectedCompanyCurrency!
      }

      // If customer is specified in route query
      if (routeParams?.query?.customer) {
        try {
          await this.selectCustomer(Number(routeParams.query.customer))
        } catch {
          // Silently fail
        }
      }

      const editActions: Promise<unknown>[] = []

      if (!isEdit && companySettings) {
        this.newInvoice.tax_per_item = companySettings.tax_per_item ?? null
        this.newInvoice.sales_tax_type = companySettings.sales_tax_type ?? null
        this.newInvoice.sales_tax_address_type =
          companySettings.sales_tax_address_type ?? null
        this.newInvoice.discount_per_item =
          companySettings.discount_per_item ?? null

        let dateFormat = 'YYYY-MM-DD'
        if (companySettings.invoice_use_time === 'YES') {
          dateFormat += ' HH:mm'
        }

        const now = new Date()
        this.newInvoice.invoice_date = formatDate(now, dateFormat)

        if (companySettings.invoice_set_due_date_automatically === 'YES') {
          const dueDate = new Date(now)
          dueDate.setDate(
            dueDate.getDate() +
              Number(companySettings.invoice_due_date_days ?? 7),
          )
          this.newInvoice.due_date = formatDate(dueDate, 'YYYY-MM-DD')
        }
      } else if (isEdit && routeParams?.id) {
        editActions.push(this.fetchInvoice(Number(routeParams.id)))
      }

      try {
        const [, , templatesRes, nextNumRes] = await Promise.all([
          Promise.resolve(), // placeholder for items fetch
          this.resetSelectedNote(),
          this.fetchInvoiceTemplates(),
          this.getNextNumber(),
          Promise.resolve(), // placeholder for tax types fetch
          ...editActions,
        ])

        if (!isEdit) {
          if (nextNumRes?.data?.nextNumber) {
            this.newInvoice.invoice_number = nextNumRes.data.nextNumber
          }

          if (templatesRes?.data?.invoiceTemplates?.length) {
            this.setTemplate(this.templates[0].name)
            if (userSettings?.default_invoice_template) {
              this.newInvoice.template_name =
                userSettings.default_invoice_template
            }
          }
        }
      } catch {
        // Error handling
      } finally {
        this.isFetchingInitialSettings = false
      }
    },
  },
})

/** Simple date formatter without moment dependency */
function formatDate(date: Date, format: string): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')

  let result = `${year}-${month}-${day}`
  if (format.includes('HH:mm')) {
    result += ` ${hours}:${minutes}`
  }
  return result
}

export type InvoiceStore = ReturnType<typeof useInvoiceStore>
