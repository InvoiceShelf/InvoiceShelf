import { client } from '../client'
import { API } from '../endpoints'

export interface RecurrencePreviewParams {
  frequency: string
  starts_at?: string
}

/** When a schedule would run: the first run and the few after it. */
export interface RecurrencePreview {
  next_invoice_at: string
  upcoming: string[]
}

/**
 * Previews shared by every recurring schedule (invoices, bills, expenses).
 * The endpoint answers with runs strictly after `starts_at`.
 */
export const recurrenceService = {
  async preview(params: RecurrencePreviewParams): Promise<RecurrencePreview> {
    const { data } = await client.get(API.RECURRING_INVOICE_FREQUENCY, { params })
    return data
  },
}
