import { client } from '../client'
import { API } from '../endpoints'
import type { ApiResponse } from '@/scripts/types/api'
import type { InvoiceReminders, ReminderSettings } from '@/scripts/types/domain/reminder'

export const reminderService = {
  async settings(): Promise<ReminderSettings> {
    const { data } = await client.get<ApiResponse<ReminderSettings>>(API.PAYMENT_REMINDERS)
    return data.data
  },

  async updateSettings(settings: Partial<ReminderSettings>): Promise<ReminderSettings> {
    const { data } = await client.put<ApiResponse<ReminderSettings>>(API.PAYMENT_REMINDERS, settings)
    return data.data
  },

  async forInvoice(invoiceId: number): Promise<InvoiceReminders> {
    const { data } = await client.get<ApiResponse<InvoiceReminders>>(
      `${API.INVOICES}/${invoiceId}/reminders`,
    )
    return data.data
  },

  async sendNow(invoiceId: number): Promise<InvoiceReminders> {
    const { data } = await client.post<ApiResponse<InvoiceReminders>>(
      `${API.INVOICES}/${invoiceId}/reminders`,
    )
    return data.data
  },

  async setPaused(invoiceId: number, paused: boolean): Promise<InvoiceReminders> {
    const { data } = await client.put<ApiResponse<InvoiceReminders>>(
      `${API.INVOICES}/${invoiceId}/reminders`,
      { paused },
    )
    return data.data
  },
}
