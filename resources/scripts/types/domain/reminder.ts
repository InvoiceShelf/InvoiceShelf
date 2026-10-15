/** A company's payment reminder settings. Offsets are days from the due date. */
export interface ReminderSettings {
  enabled: boolean
  offsets: number[]
  send_hour: number
  subject: string
  body: string
  attach_pdf: boolean
}

export type InvoiceReminderStatus = 'sent' | 'failed' | 'skipped'

export interface InvoiceReminderEntry {
  id: number
  /** Days from the due date; null when sent by hand */
  offset_days: number | null
  status: InvoiceReminderStatus
  error: string | null
  sent_by: number | null
  created_at: string | null
}

/** An invoice's reminders: whether they run, what comes next, what was sent. */
export interface InvoiceReminders {
  enabled: boolean
  remindable: boolean
  paused: boolean
  customer_paused: boolean
  has_email: boolean
  next: { date: string; offset: number } | null
  history: InvoiceReminderEntry[]
}
