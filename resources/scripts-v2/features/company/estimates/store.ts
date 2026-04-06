import { defineStore } from 'pinia'
import { useNotificationStore } from '../../../stores/notification.store'
import { useCompanyStore } from '../../../stores/company.store'
import { useUserStore } from '../../../stores/user.store'
import { estimateService } from '../../../api/services/estimate.service'
import type {
  EstimateListParams,
  EstimateListResponse,
  SendEstimatePayload,
  EstimateStatusPayload,
  EstimateTemplate,
} from '../../../api/services/estimate.service'
import type { Estimate, EstimateItem, DiscountType } from '../../../types/domain/estimate'
import type { Invoice } from '../../../types/domain/invoice'
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

function createEstimateItemStub(): DocumentItem {
  return {
    id: generateClientId(),
    estimate_id: null,
    item_id: null,
    name: '',
    description: null,
    quantity: 1,
    price: 0,
    discount_type: 'fixed',
    discount_val: 0,
    discount: 0,
    total: 0,
    sub_total: 0,
    totalTax: 0,
    totalSimpleTax: 0,
    totalCompoundTax: 0,
    tax: 0,
    taxes: [createTaxStub()],
    unit_name: null,
  }
}

export interface EstimateFormData {
  id: number | null
  customer: Customer | null
  template_name: string | null
  tax_per_item: string | null
  tax_included: boolean
  sales_tax_type: string | null
  sales_tax_address_type: string | null
  discount_per_item: string | null
  estimate_date: string
  expiry_date: string
  estimate_number: string
  customer_id: number | null
  sub_total: number
  total: number
  tax: number
  notes: string | null
  discount_type: DiscountType
  discount_val: number
  reference_number: string | null
  discount: number
  items: DocumentItem[]
  taxes: DocumentTax[]
  customFields: CustomFieldValue[]
  fields: CustomFieldValue[]
  selectedNote: Note | null
  selectedCurrency: Currency | Record<string, unknown> | string
  unique_hash?: string
  exchange_rate?: number | null
  currency_id?: number
}

function createEstimateStub(): EstimateFormData {
  return {
    id: null,
    customer: null,
    template_name: '',
    tax_per_item: null,
    tax_included: false,
    sales_tax_type: null,
    sales_tax_address_type: null,
    discount_per_item: null,
    estimate_date: '',
    expiry_date: '',
    estimate_number: '',
    customer_id: null,
    sub_total: 0,
    total: 0,
    tax: 0,
    notes: '',
    discount_type: 'fixed',
    discount_val: 0,
    reference_number: null,
    discount: 0,
    items: [createEstimateItemStub()],
    taxes: [],
    customFields: [],
    fields: [],
    selectedNote: null,
    selectedCurrency: '',
  }
}

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

export interface EstimateState {
  templates: EstimateTemplate[]
  estimates: Estimate[]
  selectAllField: boolean
  selectedEstimates: number[]
  totalEstimateCount: number
  isFetchingInitialSettings: boolean
  showExchangeRate: boolean
  newEstimate: EstimateFormData
}

export const useEstimateStore = defineStore('estimate', {
  state: (): EstimateState => ({
    templates: [],
    estimates: [],
    selectAllField: false,
    selectedEstimates: [],
    totalEstimateCount: 0,
    isFetchingInitialSettings: false,
    showExchangeRate: false,
    newEstimate: createEstimateStub(),
  }),

  getters: {
    getSubTotal(state): number {
      return state.newEstimate.items.reduce(
        (sum: number, item: DocumentItem) => sum + (item.total ?? 0),
        0,
      )
    },

    getNetTotal(): number {
      return this.getSubtotalWithDiscount - this.getTotalTax
    },

    getTotalSimpleTax(state): number {
      return state.newEstimate.taxes.reduce(
        (sum: number, tax: DocumentTax) => {
          if (!tax.compound_tax) return sum + (tax.amount ?? 0)
          return sum
        },
        0,
      )
    },

    getTotalCompoundTax(state): number {
      return state.newEstimate.taxes.reduce(
        (sum: number, tax: DocumentTax) => {
          if (tax.compound_tax) return sum + (tax.amount ?? 0)
          return sum
        },
        0,
      )
    },

    getTotalTax(): number {
      if (
        this.newEstimate.tax_per_item === 'NO' ||
        this.newEstimate.tax_per_item === null
      ) {
        return this.getTotalSimpleTax + this.getTotalCompoundTax
      }
      return this.newEstimate.items.reduce(
        (sum: number, item: DocumentItem) => sum + (item.tax ?? 0),
        0,
      )
    },

    getSubtotalWithDiscount(): number {
      return this.getSubTotal - this.newEstimate.discount_val
    },

    getTotal(): number {
      if (this.newEstimate.tax_included) {
        return this.getSubtotalWithDiscount
      }
      return this.getSubtotalWithDiscount + this.getTotalTax
    },

    isEdit(state): boolean {
      return !!state.newEstimate.id
    },
  },

  actions: {
    resetCurrentEstimate(): void {
      this.newEstimate = createEstimateStub()
    },

    async previewEstimate(params: { id: number }): Promise<unknown> {
      return estimateService.sendPreview(params.id, params)
    },

    async fetchEstimates(
      params: EstimateListParams & { estimate_number?: string },
    ): Promise<{ data: EstimateListResponse }> {
      const response = await estimateService.list(params)
      this.estimates = response.data
      this.totalEstimateCount = response.meta.estimate_total_count
      return { data: response }
    },

    async getNextNumber(
      params?: Record<string, unknown>,
      setState = false,
    ): Promise<{ data: { nextNumber: string } }> {
      const response = await estimateService.getNextNumber(params as never)
      if (setState) {
        this.newEstimate.estimate_number = response.nextNumber
      }
      return { data: response }
    },

    async fetchEstimate(id: number): Promise<{ data: { data: Estimate } }> {
      const response = await estimateService.get(id)
      this.setEstimateData(response.data)
      this.setCustomerAddresses(this.newEstimate.customer)
      return { data: response }
    },

    setEstimateData(estimate: Estimate): void {
      Object.assign(this.newEstimate, estimate)

      if (this.newEstimate.tax_per_item === 'YES') {
        this.newEstimate.items.forEach((item) => {
          if (item.taxes && !item.taxes.length) {
            item.taxes.push(createTaxStub())
          }
        })
      }

      if (this.newEstimate.discount_per_item === 'YES') {
        this.newEstimate.items.forEach((item, index) => {
          if (item.discount_type === 'fixed') {
            this.newEstimate.items[index].discount = item.discount / 100
          }
        })
      } else {
        if (this.newEstimate.discount_type === 'fixed') {
          this.newEstimate.discount = this.newEstimate.discount / 100
        }
      }
    },

    setCustomerAddresses(customer: Customer | null): void {
      if (!customer) return
      const business = (customer as Record<string, unknown>).customer_business as
        | Record<string, unknown>
        | undefined

      if (business?.billing_address) {
        ;(this.newEstimate.customer as Record<string, unknown>).billing_address =
          business.billing_address
      }
      if (business?.shipping_address) {
        ;(this.newEstimate.customer as Record<string, unknown>).shipping_address =
          business.shipping_address
      }
    },

    addSalesTaxUs(taxTypes: TaxType[]): void {
      const salesTax = createTaxStub()
      const found = this.newEstimate.taxes.find(
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

    async sendEstimate(data: SendEstimatePayload): Promise<unknown> {
      return estimateService.send(data)
    },

    async addEstimate(data: Record<string, unknown>): Promise<{ data: { data: Estimate } }> {
      const response = await estimateService.create(data as never)
      this.estimates = [...this.estimates, response.data]

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'estimates.created_message',
      })

      return { data: response }
    },

    async deleteEstimate(payload: { ids: number[] }): Promise<{ data: { success: boolean } }> {
      const response = await estimateService.delete(payload)
      const id = payload.ids[0]
      const index = this.estimates.findIndex((est) => est.id === id)
      if (index !== -1) {
        this.estimates.splice(index, 1)
      }
      return { data: response }
    },

    async deleteMultipleEstimates(): Promise<{ data: { success: boolean } }> {
      const response = await estimateService.delete({
        ids: this.selectedEstimates,
      })
      this.selectedEstimates.forEach((estId) => {
        const index = this.estimates.findIndex((est) => est.id === estId)
        if (index !== -1) {
          this.estimates.splice(index, 1)
        }
      })
      this.selectedEstimates = []
      return { data: response }
    },

    async updateEstimate(data: Record<string, unknown>): Promise<{ data: { data: Estimate } }> {
      const response = await estimateService.update(data.id as number, data as never)
      const pos = this.estimates.findIndex((est) => est.id === response.data.id)
      if (pos !== -1) {
        this.estimates[pos] = response.data
      }

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'estimates.updated_message',
      })

      return { data: response }
    },

    async cloneEstimate(data: { id: number }): Promise<{ data: { data: Estimate } }> {
      const response = await estimateService.clone(data.id)
      return { data: response }
    },

    async markAsAccepted(data: EstimateStatusPayload): Promise<unknown> {
      const response = await estimateService.changeStatus({
        ...data,
        status: 'ACCEPTED',
      })
      const pos = this.estimates.findIndex((est) => est.id === data.id)
      if (pos !== -1 && this.estimates[pos]) {
        this.estimates[pos].status = 'ACCEPTED' as Estimate['status']
      }
      return response
    },

    async markAsRejected(data: EstimateStatusPayload): Promise<unknown> {
      const response = await estimateService.changeStatus({
        ...data,
        status: 'REJECTED',
      })
      return response
    },

    async markAsSent(data: EstimateStatusPayload): Promise<unknown> {
      const response = await estimateService.changeStatus(data)
      const pos = this.estimates.findIndex((est) => est.id === data.id)
      if (pos !== -1 && this.estimates[pos]) {
        this.estimates[pos].status = 'SENT' as Estimate['status']
      }
      return response
    },

    async convertToInvoice(id: number): Promise<{ data: { data: Invoice } }> {
      const response = await estimateService.convertToInvoice(id)
      return { data: response }
    },

    async searchEstimate(queryString: string): Promise<unknown> {
      return estimateService.list(
        Object.fromEntries(new URLSearchParams(queryString)) as never,
      )
    },

    selectEstimate(data: number[]): void {
      this.selectedEstimates = data
      this.selectAllField =
        this.selectedEstimates.length === this.estimates.length
    },

    selectAllEstimates(): void {
      if (this.selectedEstimates.length === this.estimates.length) {
        this.selectedEstimates = []
        this.selectAllField = false
      } else {
        this.selectedEstimates = this.estimates.map((est) => est.id)
        this.selectAllField = true
      }
    },

    async selectCustomer(id: number): Promise<unknown> {
      const { customerService } = await import(
        '../../../api/services/customer.service'
      )
      const response = await customerService.get(id)
      this.newEstimate.customer = response.data as unknown as Customer
      this.newEstimate.customer_id = response.data.id
      if (response.data.currency) {
        this.newEstimate.currency_id = (response.data.currency as { id: number }).id
      }
      return response
    },

    async fetchEstimateTemplates(): Promise<{
      data: { estimateTemplates: EstimateTemplate[] }
    }> {
      const response = await estimateService.getTemplates()
      this.templates = response.estimateTemplates
      return { data: response }
    },

    setTemplate(name: string): void {
      this.newEstimate.template_name = name
    },

    resetSelectedCustomer(): void {
      this.newEstimate.customer = null
      this.newEstimate.customer_id = null
    },

    selectNote(data: Note): void {
      this.newEstimate.selectedNote = null
      this.newEstimate.selectedNote = data
    },

    resetSelectedNote(): void {
      this.newEstimate.selectedNote = null
    },

    addItem(): void {
      this.newEstimate.items.push(createEstimateItemStub())
    },

    updateItem(data: DocumentItem & { index: number }): void {
      Object.assign(this.newEstimate.items[data.index], { ...data })
    },

    removeItem(index: number): void {
      this.newEstimate.items.splice(index, 1)
    },

    deselectItem(index: number): void {
      this.newEstimate.items[index] = createEstimateItemStub()
    },

    async fetchEstimateInitialSettings(
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
        this.newEstimate.selectedCurrency = companyCurrency ?? companyStore.selectedCompanyCurrency!
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
        this.newEstimate.tax_per_item = companySettings.tax_per_item ?? null
        this.newEstimate.sales_tax_type = companySettings.sales_tax_type ?? null
        this.newEstimate.sales_tax_address_type =
          companySettings.sales_tax_address_type ?? null
        this.newEstimate.discount_per_item =
          companySettings.discount_per_item ?? null

        const now = new Date()
        this.newEstimate.estimate_date = formatDate(now, 'YYYY-MM-DD')

        if (companySettings.estimate_set_expiry_date_automatically === 'YES') {
          const expiryDate = new Date(now)
          expiryDate.setDate(
            expiryDate.getDate() +
              Number(companySettings.estimate_expiry_date_days ?? 7),
          )
          this.newEstimate.expiry_date = formatDate(expiryDate, 'YYYY-MM-DD')
        }
      } else if (isEdit && routeParams?.id) {
        editActions.push(this.fetchEstimate(Number(routeParams.id)))
      }

      try {
        const [, , templatesRes, nextNumRes] = await Promise.all([
          Promise.resolve(), // placeholder for items fetch
          this.resetSelectedNote(),
          this.fetchEstimateTemplates(),
          this.getNextNumber(),
          Promise.resolve(), // placeholder for tax types fetch
          ...editActions,
        ])

        if (!isEdit) {
          if (nextNumRes?.data?.nextNumber) {
            this.newEstimate.estimate_number = nextNumRes.data.nextNumber
          }

          if (this.templates.length) {
            this.setTemplate(this.templates[0].name)
            const { currentUserSettings } = useUserStore()
            if (currentUserSettings.default_estimate_template) {
              this.newEstimate.template_name =
                currentUserSettings.default_estimate_template
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
function formatDate(date: Date, _format: string): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export type EstimateStore = ReturnType<typeof useEstimateStore>
