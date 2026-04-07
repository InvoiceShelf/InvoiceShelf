import { defineStore } from 'pinia'
import { useNotificationStore } from '../../../stores/notification.store'
import { paymentService } from '../../../api/services/payment.service'
import type {
  PaymentListParams,
  PaymentListResponse,
  SendPaymentPayload,
} from '../../../api/services/payment.service'
import type {
  Payment,
  PaymentMethod,
  CreatePaymentPayload,
} from '../../../types/domain/payment'
import type { Customer } from '../../../types/domain/customer'
import type { Currency } from '../../../types/domain/currency'
import type { Note } from '../../../types/domain/note'
import type { CustomFieldValue } from '../../../types/domain/custom-field'

// ----------------------------------------------------------------
// Stub factories
// ----------------------------------------------------------------

export interface PaymentFormData {
  id: number | null
  payment_number: string
  payment_date: string
  customer_id: number | null
  customer: Customer | null
  selectedCustomer: Customer | null
  invoice_id: number | null
  amount: number
  payment_method_id: number | null
  notes: string | null
  currency: Currency | Record<string, unknown> | null
  currency_id: number | null
  exchange_rate: number | null
  maxPayableAmount: number
  selectedNote: Note | null
  customFields: CustomFieldValue[]
  fields: CustomFieldValue[]
  unique_hash?: string
}

function createPaymentStub(): PaymentFormData {
  return {
    id: null,
    payment_number: '',
    payment_date: '',
    customer_id: null,
    customer: null,
    selectedCustomer: null,
    invoice_id: null,
    amount: 0,
    payment_method_id: null,
    notes: '',
    currency: null,
    currency_id: null,
    exchange_rate: null,
    maxPayableAmount: Number.MAX_SAFE_INTEGER,
    selectedNote: null,
    customFields: [],
    fields: [],
  }
}

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

export interface PaymentState {
  payments: Payment[]
  paymentTotalCount: number
  selectAllField: boolean
  selectedPayments: number[]
  selectedNote: Note | null
  showExchangeRate: boolean
  paymentModes: PaymentMethod[]
  currentPaymentMode: { id: number | string; name: string | null }
  currentPayment: PaymentFormData
  isFetchingInitialData: boolean
}

export const usePaymentStore = defineStore('payment', {
  state: (): PaymentState => ({
    payments: [],
    paymentTotalCount: 0,
    selectAllField: false,
    selectedPayments: [],
    selectedNote: null,
    showExchangeRate: false,
    paymentModes: [],
    currentPaymentMode: { id: '', name: null },
    currentPayment: createPaymentStub(),
    isFetchingInitialData: false,
  }),

  getters: {
    getPayment:
      (state) =>
      (id: number): Payment | undefined => {
        return state.payments.find((p) => p.id === id)
      },
  },

  actions: {
    resetCurrentPayment(): void {
      this.currentPayment = createPaymentStub()
    },

    async fetchPayments(
      params: PaymentListParams & {
        payment_method_id?: number | string
        payment_number?: string
      },
    ): Promise<{ data: PaymentListResponse }> {
      const response = await paymentService.list(params)
      this.payments = response.data
      this.paymentTotalCount = response.meta.payment_total_count
      return { data: response }
    },

    async fetchPayment(id: number): Promise<{ data: { data: Payment } }> {
      const response = await paymentService.get(id)
      Object.assign(this.currentPayment, response.data)
      return { data: response }
    },

    async addPayment(
      data: Record<string, unknown>,
    ): Promise<{ data: { data: Payment } }> {
      const response = await paymentService.create(data as never)
      this.payments.push(response.data)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'payments.created_message',
      })

      return { data: response }
    },

    async updatePayment(
      data: Record<string, unknown>,
    ): Promise<{ data: { data: Payment } }> {
      const response = await paymentService.update(
        data.id as number,
        data as never,
      )
      const pos = this.payments.findIndex((p) => p.id === response.data.id)
      if (pos !== -1) {
        this.payments[pos] = response.data
      }

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'payments.updated_message',
      })

      return { data: response }
    },

    async deletePayment(
      payload: { ids: number[] },
    ): Promise<{ data: { success: boolean } }> {
      const response = await paymentService.delete(payload)
      const id = payload.ids[0]
      const index = this.payments.findIndex((p) => p.id === id)
      if (index !== -1) {
        this.payments.splice(index, 1)
      }
      return { data: response }
    },

    async deleteMultiplePayments(): Promise<{ data: { success: boolean } }> {
      const response = await paymentService.delete({
        ids: this.selectedPayments,
      })
      this.selectedPayments.forEach((paymentId) => {
        const index = this.payments.findIndex((p) => p.id === paymentId)
        if (index !== -1) {
          this.payments.splice(index, 1)
        }
      })
      this.selectedPayments = []
      return { data: response }
    },

    async sendEmail(data: SendPaymentPayload): Promise<unknown> {
      return paymentService.send(data)
    },

    async previewPayment(params: { id: number } & Record<string, unknown>): Promise<unknown> {
      return paymentService.sendPreview(params.id, params)
    },

    async getNextNumber(
      params?: Record<string, unknown>,
      setState = false,
    ): Promise<{ data: { nextNumber: string } }> {
      const response = await paymentService.getNextNumber(params as never)
      if (setState) {
        this.currentPayment.payment_number = response.nextNumber
      }
      return { data: response }
    },

    async fetchPaymentModes(
      params?: Record<string, unknown>,
    ): Promise<{ data: { data: PaymentMethod[] } }> {
      const response = await paymentService.listMethods(params as never)
      this.paymentModes = response.data
      return { data: response }
    },

    async fetchPaymentMode(id: number): Promise<{ data: { data: PaymentMethod } }> {
      const response = await paymentService.getMethod(id)
      this.currentPaymentMode = response.data
      return { data: response }
    },

    async addPaymentMode(
      data: { name: string },
    ): Promise<{ data: { data: PaymentMethod } }> {
      const response = await paymentService.createMethod(data)
      this.paymentModes.push(response.data)
      return { data: response }
    },

    async updatePaymentMode(
      data: { id: number; name: string },
    ): Promise<{ data: { data: PaymentMethod } }> {
      const response = await paymentService.updateMethod(data.id, data)
      const pos = this.paymentModes.findIndex((m) => m.id === response.data.id)
      if (pos !== -1) {
        this.paymentModes[pos] = response.data
      }
      return { data: response }
    },

    async deletePaymentMode(id: number): Promise<{ data: { success: boolean } }> {
      const response = await paymentService.deleteMethod(id)
      const index = this.paymentModes.findIndex((m) => m.id === id)
      if (index !== -1) {
        this.paymentModes.splice(index, 1)
      }
      return { data: response }
    },

    selectPayment(data: number[]): void {
      this.selectedPayments = data
      this.selectAllField =
        this.selectedPayments.length === this.payments.length
    },

    selectAllPayments(): void {
      if (this.selectedPayments.length === this.payments.length) {
        this.selectedPayments = []
        this.selectAllField = false
      } else {
        this.selectedPayments = this.payments.map((p) => p.id)
        this.selectAllField = true
      }
    },

    setSelectAllState(data: boolean): void {
      this.selectAllField = data
    },

    selectNote(data: Note): void {
      this.selectedNote = null
      this.selectedNote = data
    },

    resetSelectedNote(): void {
      this.selectedNote = null
    },

    async fetchPaymentInitialData(
      isEdit: boolean,
      routeParams?: { id?: string },
      companyCurrency?: Currency,
    ): Promise<void> {
      this.isFetchingInitialData = true

      const editActions: Promise<unknown>[] = []
      if (isEdit && routeParams?.id) {
        editActions.push(this.fetchPayment(Number(routeParams.id)))
      }

      try {
        const [, nextNumRes, editRes] = await Promise.all([
          this.fetchPaymentModes({ limit: 'all' }),
          this.getNextNumber(),
          ...editActions,
        ])

        if (isEdit) {
          const paymentRes = editRes as { data: { data: Payment } } | undefined
          if (paymentRes?.data?.data?.invoice) {
            this.currentPayment.maxPayableAmount = parseInt(
              String(paymentRes.data.data.invoice.due_amount),
            )
          }
        } else if (!isEdit && nextNumRes) {
          const now = new Date()
          this.currentPayment.payment_date = formatDate(now)
          this.currentPayment.payment_number =
            nextNumRes.data.nextNumber
          if (companyCurrency) {
            this.currentPayment.currency = companyCurrency
          }
        }
      } catch {
        // Error handling
      } finally {
        this.isFetchingInitialData = false
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

export type PaymentStore = ReturnType<typeof usePaymentStore>
