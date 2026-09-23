import '../css/invoiceshelf.css'
import 'v-tooltip/dist/v-tooltip.css'

import * as Vue from 'vue'
import InvoiceShelf from './InvoiceShelf'
import { reassertHostStylesAfterShellModules } from './utils/host-styles'
import { focusInvalidAfterSubmit } from './composables/use-form-field'
import type { DemoState } from './utils/demo'

// The shell's module stylesheets must not outrank the app's own utilities
reassertHostStylesAfterShellModules()

// A failed submit takes the keyboard to the first field in error
focusInvalidAfterSubmit()

// Expose Vue runtime for module scripts that import from the shim.
;(window as Record<string, unknown>).__invoiceshelf_vue = Vue

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
    demo?: DemoState
  }
}

const invoiceShelf = new InvoiceShelf()
window.InvoiceShelf = invoiceShelf
window.loadLanguage = (locale: string) => invoiceShelf.loadLanguage(locale)
