import { defineStore } from 'pinia'
import { recurringInvoiceService } from '../../../api/services/recurring-invoice.service'
import type {
  RecurringInvoiceListParams,
  RecurringInvoiceListResponse,
  FrequencyDateParams,
} from '../../../api/services/recurring-invoice.service'
import type {
  RecurringInvoice,
  RecurringInvoiceLimitBy,
} from '../../../types/domain/recurring-invoice'
import type { Invoice, InvoiceItem, DiscountType } from '../../../types/domain/invoice'
import type { Tax, TaxType } from '../../../types/domain/tax'
import type { Currency } from '../../../types/domain/currency'
import type { Customer } from '../../../types/domain/customer'
import type { Note } from '../../../types/domain/note'
import type { CustomFieldValue } from '../../../types/domain/custom-field'
import type {
  DocumentTax,
  DocumentItem,
} from '../../shared/document-form/use-document-calculations'

// ----------------------------------------------------------------
// Frequency options
// ----------------------------------------------------------------

export interface FrequencyOption {
  label: string
  value: string
}

// ----------------------------------------------------------------
// Stub factories
// ----------------------------------------------------------------

function createTaxStub(): DocumentTax {
  return {
    id: crypto.randomUUID(),
    name: '',
    tax_type_id: 0,
    type: 'GENERAL',
    amount: 0,
    percent: null,
    compound_tax: false,
    calculation_type: null,
    fixed_amount: 0,
  }
}

function createRecurringInvoiceItemStub(): DocumentItem {
  return {
    id: crypto.randomUUID(),
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

export interface RecurringInvoiceFormData {
  id: number | null
  customer: Customer | null
  customer_id: number | null
  template_name: string | null
  starts_at: string
  next_invoice_at: string
  next_invoice_date: string
  frequency: string | null
  selectedFrequency: FrequencyOption | null
  status: string
  limit_by: RecurringInvoiceLimitBy | string
  limit_count: number | null
  limit_date: string | null
  send_automatically: boolean
  notes: string | null
  discount: number
  discount_type: DiscountType
  discount_val: number
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
  currency: Currency | Record<string, unknown> | null
  currency_id: number | null
  exchange_rate: number | null
  unique_hash?: string
  invoices?: Invoice[]
}

function createRecurringInvoiceStub(): RecurringInvoiceFormData {
  return {
    id: null,
    customer: null,
    customer_id: null,
    template_name: null,
    starts_at: '',
    next_invoice_at: '',
    next_invoice_date: '',
    frequency: null,
    selectedFrequency: null,
    status: 'ACTIVE',
    limit_by: 'NONE',
    limit_count: null,
    limit_date: null,
    send_automatically: false,
    notes: '',
    discount: 0,
    discount_type: 'fixed',
    discount_val: 0,
    tax: 0,
    sub_total: 0,
    total: 0,
    tax_per_item: null,
    tax_included: false,
    sales_tax_type: null,
    sales_tax_address_type: null,
    discount_per_item: null,
    taxes: [],
    items: [createRecurringInvoiceItemStub()],
    customFields: [],
    fields: [],
    selectedNote: null,
    currency: null,
    currency_id: null,
    exchange_rate: null,
    invoices: [],
  }
}

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

export interface RecurringInvoiceState {
  templates: { name: string; path?: string }[]
  recurringInvoices: RecurringInvoice[]
  selectedRecurringInvoices: number[]
  totalRecurringInvoices: number
  isFetchingInitialSettings: boolean
  isFetchingInvoice: boolean
  isFetchingViewData: boolean
  showExchangeRate: boolean
  selectAllField: boolean
  newRecurringInvoice: RecurringInvoiceFormData
  frequencies: FrequencyOption[]
}

export const useRecurringInvoiceStore = defineStore('recurring-invoice', {
  state: (): RecurringInvoiceState => ({
    templates: [],
    recurringInvoices: [],
    selectedRecurringInvoices: [],
    totalRecurringInvoices: 0,
    isFetchingInitialSettings: false,
    isFetchingInvoice: false,
    isFetchingViewData: false,
    showExchangeRate: false,
    selectAllField: false,
    newRecurringInvoice: createRecurringInvoiceStub(),
    frequencies: [],
  }),

  getters: {
    getSubTotal(state): number {
      return state.newRecurringInvoice.items.reduce(
        (sum: number, item: DocumentItem) => sum + (item.total ?? 0),
        0,
      )
    },

    getNetTotal(): number {
      return this.getSubtotalWithDiscount - this.getTotalTax
    },

    getTotalSimpleTax(state): number {
      return state.newRecurringInvoice.taxes.reduce(
        (sum: number, tax: DocumentTax) => {
          if (!tax.compound_tax) return sum + (tax.amount ?? 0)
          return sum
        },
        0,
      )
    },

    getTotalCompoundTax(state): number {
      return state.newRecurringInvoice.taxes.reduce(
        (sum: number, tax: DocumentTax) => {
          if (tax.compound_tax) return sum + (tax.amount ?? 0)
          return sum
        },
        0,
      )
    },

    getTotalTax(): number {
      if (
        this.newRecurringInvoice.tax_per_item === 'NO' ||
        this.newRecurringInvoice.tax_per_item === null
      ) {
        return this.getTotalSimpleTax + this.getTotalCompoundTax
      }
      return this.newRecurringInvoice.items.reduce(
        (sum: number, item: DocumentItem) => sum + (item.tax ?? 0),
        0,
      )
    },

    getSubtotalWithDiscount(): number {
      return this.getSubTotal - this.newRecurringInvoice.discount_val
    },

    getTotal(): number {
      if (this.newRecurringInvoice.tax_included) {
        return this.getSubtotalWithDiscount
      }
      return this.getSubtotalWithDiscount + this.getTotalTax
    },
  },

  actions: {
    initFrequencies(t: (key: string) => string): void {
      this.frequencies = [
        {
          label: t('recurring_invoices.frequency.every_minute'),
          value: '* * * * *',
        },
        {
          label: t('recurring_invoices.frequency.every_30_minute'),
          value: '*/30 * * * *',
        },
        {
          label: t('recurring_invoices.frequency.every_hour'),
          value: '0 * * * *',
        },
        {
          label: t('recurring_invoices.frequency.every_2_hour'),
          value: '0 */2 * * *',
        },
        {
          label: t('recurring_invoices.frequency.every_day_at_midnight'),
          value: '0 0 * * *',
        },
        {
          label: t('recurring_invoices.frequency.every_week'),
          value: '0 0 * * 0',
        },
        {
          label: t(
            'recurring_invoices.frequency.every_15_days_at_midnight',
          ),
          value: '0 5 */15 * *',
        },
        {
          label: t(
            'recurring_invoices.frequency.on_the_first_day_of_every_month_at_midnight',
          ),
          value: '0 0 1 * *',
        },
        {
          label: t('recurring_invoices.frequency.every_6_month'),
          value: '0 0 1 */6 *',
        },
        {
          label: t(
            'recurring_invoices.frequency.every_year_on_the_first_day_of_january_at_midnight',
          ),
          value: '0 0 1 1 *',
        },
        {
          label: t('recurring_invoices.frequency.custom'),
          value: 'CUSTOM',
        },
      ]
    },

    resetCurrentRecurringInvoice(): void {
      this.newRecurringInvoice = createRecurringInvoiceStub()
    },

    deselectItem(index: number): void {
      this.newRecurringInvoice.items[index] = {
        ...createRecurringInvoiceItemStub(),
        taxes: [createTaxStub()],
      }
    },

    addItem(): void {
      this.newRecurringInvoice.items.push({
        ...createRecurringInvoiceItemStub(),
        taxes: [createTaxStub()],
      })
    },

    removeItem(index: number): void {
      this.newRecurringInvoice.items.splice(index, 1)
    },

    updateItem(data: DocumentItem & { index: number }): void {
      Object.assign(this.newRecurringInvoice.items[data.index], { ...data })
    },

    setTemplate(name: string): void {
      this.newRecurringInvoice.template_name = name
    },

    setSelectedFrequency(): void {
      const found = this.frequencies.find(
        (f) => f.value === this.newRecurringInvoice.frequency,
      )
      if (found) {
        this.newRecurringInvoice.selectedFrequency = found
      } else {
        this.newRecurringInvoice.selectedFrequency = {
          label: 'Custom',
          value: 'CUSTOM',
        }
      }
    },

    resetSelectedNote(): void {
      this.newRecurringInvoice.selectedNote = null
    },

    selectNote(data: Note): void {
      this.newRecurringInvoice.selectedNote = null
      this.newRecurringInvoice.selectedNote = data
    },

    resetSelectedCustomer(): void {
      this.newRecurringInvoice.customer = null
      this.newRecurringInvoice.customer_id = null
    },

    async selectCustomer(id: number): Promise<unknown> {
      const { customerService } = await import(
        '../../../api/services/customer.service'
      )
      const response = await customerService.get(id)
      this.newRecurringInvoice.customer =
        response.data as unknown as Customer
      this.newRecurringInvoice.customer_id = response.data.id
      return response
    },

    async fetchRecurringInvoices(
      params: RecurringInvoiceListParams & {
        from_date?: string
        to_date?: string
      },
    ): Promise<{ data: RecurringInvoiceListResponse }> {
      const response = await recurringInvoiceService.list(params)
      this.recurringInvoices = response.data
      this.totalRecurringInvoices =
        response.meta.recurring_invoice_total_count
      return { data: response }
    },

    async fetchRecurringInvoice(
      id: number,
    ): Promise<{ data: { data: RecurringInvoice } }> {
      this.isFetchingViewData = true
      try {
        const response = await recurringInvoiceService.get(id)
        Object.assign(this.newRecurringInvoice, response.data)
        this.newRecurringInvoice.invoices = response.data.invoices ?? []
        this.setSelectedFrequency()
        this.isFetchingViewData = false
        return { data: response }
      } catch (err) {
        this.isFetchingViewData = false
        throw err
      }
    },

    async addRecurringInvoice(
      data: Record<string, unknown>,
    ): Promise<{ data: { data: RecurringInvoice } }> {
      const response = await recurringInvoiceService.create(data as never)
      this.recurringInvoices = [
        ...this.recurringInvoices,
        response.data,
      ]
      return { data: response }
    },

    async updateRecurringInvoice(
      data: Record<string, unknown>,
    ): Promise<{ data: { data: RecurringInvoice } }> {
      const response = await recurringInvoiceService.update(
        data.id as number,
        data as never,
      )
      const pos = this.recurringInvoices.findIndex(
        (inv) => inv.id === response.data.id,
      )
      if (pos !== -1) {
        this.recurringInvoices[pos] = response.data
      }
      return { data: response }
    },

    async deleteRecurringInvoice(
      payload: { ids: number[] },
    ): Promise<{ data: { success: boolean } }> {
      const response = await recurringInvoiceService.delete(payload)
      const id = payload.ids[0]
      const index = this.recurringInvoices.findIndex((inv) => inv.id === id)
      if (index !== -1) {
        this.recurringInvoices.splice(index, 1)
      }
      return { data: response }
    },

    async deleteMultipleRecurringInvoices(
      singleId?: number | null,
    ): Promise<{ data: { success: boolean } }> {
      const ids = singleId
        ? [singleId]
        : this.selectedRecurringInvoices
      const response = await recurringInvoiceService.delete({ ids })
      this.selectedRecurringInvoices.forEach((invoiceId) => {
        const index = this.recurringInvoices.findIndex(
          (inv) => inv.id === invoiceId,
        )
        if (index !== -1) {
          this.recurringInvoices.splice(index, 1)
        }
      })
      this.selectedRecurringInvoices = []
      return { data: response }
    },

    async fetchRecurringInvoiceFrequencyDate(
      params: FrequencyDateParams,
    ): Promise<void> {
      const response =
        await recurringInvoiceService.getFrequencyDate(params)
      this.newRecurringInvoice.next_invoice_at =
        response.next_invoice_at
    },

    selectRecurringInvoice(data: number[]): void {
      this.selectedRecurringInvoices = data
      this.selectAllField =
        this.selectedRecurringInvoices.length ===
        this.recurringInvoices.length
    },

    selectAllRecurringInvoices(): void {
      if (
        this.selectedRecurringInvoices.length ===
        this.recurringInvoices.length
      ) {
        this.selectedRecurringInvoices = []
        this.selectAllField = false
      } else {
        this.selectedRecurringInvoices = this.recurringInvoices.map(
          (inv) => inv.id,
        )
        this.selectAllField = true
      }
    },

    addSalesTaxUs(taxTypes: TaxType[]): void {
      const salesTax = createTaxStub()
      const found = this.newRecurringInvoice.taxes.find(
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

    async fetchRecurringInvoiceInitialSettings(
      isEdit: boolean,
      routeParams?: { id?: string; query?: Record<string, string> },
      companySettings?: Record<string, string>,
      companyCurrency?: Currency,
    ): Promise<void> {
      this.isFetchingInitialSettings = true

      if (companyCurrency) {
        this.newRecurringInvoice.currency = companyCurrency
      }

      if (routeParams?.query?.customer) {
        try {
          await this.selectCustomer(
            Number(routeParams.query.customer),
          )
        } catch {
          // Silently fail
        }
      }

      const editActions: Promise<unknown>[] = []

      if (!isEdit && companySettings) {
        this.newRecurringInvoice.tax_per_item =
          companySettings.tax_per_item ?? null
        this.newRecurringInvoice.discount_per_item =
          companySettings.discount_per_item ?? null
        this.newRecurringInvoice.sales_tax_type =
          companySettings.sales_tax_type ?? null
        this.newRecurringInvoice.sales_tax_address_type =
          companySettings.sales_tax_address_type ?? null
        this.newRecurringInvoice.starts_at = formatDate(new Date())
        this.newRecurringInvoice.next_invoice_date = formatDate(
          addDays(new Date(), 7),
        )
      } else if (isEdit && routeParams?.id) {
        editActions.push(
          this.fetchRecurringInvoice(Number(routeParams.id)),
        )
      }

      try {
        const [, , , , editRes] = await Promise.all([
          Promise.resolve(), // placeholder for items fetch
          this.resetSelectedNote(),
          Promise.resolve(), // placeholder for invoice templates
          Promise.resolve(), // placeholder for tax types fetch
          ...editActions,
        ])

        if (!isEdit) {
          if (this.templates.length) {
            this.setTemplate(this.templates[0].name)
          }
        } else if (editRes) {
          const res = editRes as { data: { data: RecurringInvoice } }
          if (res?.data?.data?.template_name) {
            this.setTemplate(res.data.data.template_name)
          }
          this.addSalesTaxUs([])
        }
      } catch {
        // Error handling
      } finally {
        this.isFetchingInitialSettings = false
      }
    },
  },
})

function formatDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function addDays(date: Date, days: number): Date {
  const result = new Date(date)
  result.setDate(result.getDate() + days)
  return result
}

export type RecurringInvoiceStore = ReturnType<typeof useRecurringInvoiceStore>
