import '../css/invoiceshelf.css'
import 'v-tooltip/dist/v-tooltip.css'

import InvoiceShelf from './InvoiceShelf'

declare global {
  interface Window {
    InvoiceShelf: InvoiceShelf
    loadLanguage?: (locale: string) => Promise<void>
    customer_logo?: string
    customer_page_title?: string
    login_page_logo?: string
    login_page_heading?: string
    login_page_description?: string
    copyright_text?: string
    demo_mode?: boolean
  }
}

const invoiceShelf = new InvoiceShelf()
window.InvoiceShelf = invoiceShelf
window.loadLanguage = (locale: string) => invoiceShelf.loadLanguage(locale)
