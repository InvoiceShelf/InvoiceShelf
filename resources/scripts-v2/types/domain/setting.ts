/**
 * Global application setting (not company-scoped).
 * Corresponds to the `settings` table.
 */
export interface Setting {
  id: number
  option: string
  value: string | null
}

/**
 * Common company settings that are frequently accessed on the frontend.
 * These correspond to key-value pairs in the company_settings table.
 * Use this typed map when reading settings from the store, rather than
 * accessing raw CompanySetting rows.
 */
export interface CompanySettingsMap {
  currency: string
  time_zone: string
  language: string
  fiscal_year: string
  carbon_date_format: string
  carbon_time_format: string
  moment_date_format: string
  notification_email: string
  tax_per_item: 'YES' | 'NO'
  discount_per_item: 'YES' | 'NO'
  invoice_prefix: string
  invoice_auto_generate: string
  estimate_prefix: string
  estimate_auto_generate: string
  payment_prefix: string
  payment_auto_generate: string
  invoice_mail_body: string
  estimate_mail_body: string
  payment_mail_body: string
  invoice_company_address_format: string
  invoice_shipping_address_format: string
  invoice_billing_address_format: string
  estimate_company_address_format: string
  estimate_shipping_address_format: string
  estimate_billing_address_format: string
  payment_company_address_format: string
  payment_from_customer_address_format: string
  invoice_email_attachment: 'YES' | 'NO'
  estimate_email_attachment: 'YES' | 'NO'
  payment_email_attachment: 'YES' | 'NO'
  retrospective_edits: string
  invoice_set_due_date_automatically: 'YES' | 'NO'
  invoice_due_date_days: string
  estimate_set_expiry_date_automatically: 'YES' | 'NO'
  estimate_expiry_date_days: string
  estimate_convert_action: string
  invoice_use_time: 'YES' | 'NO'
  sales_tax_type: string | null
  sales_tax_address_type: string | null
  [key: string]: string | null
}
