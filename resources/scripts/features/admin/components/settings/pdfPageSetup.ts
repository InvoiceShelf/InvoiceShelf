import { helpers } from '@vuelidate/validators'
import type { PdfPageSetup } from '@/scripts/api/services/pdf.service'

/**
 * Shared bits of the page-setup form, so the dompdf and Gotenberg components
 * validate and seed the same fields the same way rather than drifting apart.
 */

export const PAGE_SETUP_KEYS = [
  'pdf_paper_width',
  'pdf_paper_height',
  'pdf_orientation',
  'pdf_margin_top',
  'pdf_margin_right',
  'pdf_margin_bottom',
  'pdf_margin_left',
] as const

/** Mirrors App\Rules\CssLength, so a bad value is caught before the round trip. */
const CSS_LENGTH = /^(0|\d+(\.\d+)?(pt|px|pc|mm|cm|in))$/

export function cssLength(t: (key: string) => string) {
  return {
    cssLength: helpers.withMessage(t('validation.invalid_length'), (value: string) =>
      !helpers.req(value) ? true : CSS_LENGTH.test(String(value).trim())
    ),
  }
}

/**
 * Matches the defaults in config/pdf.php. Margins are zero because the stock
 * templates own their own spacing, and a full-bleed header only reaches the
 * paper edge when the page margin is nothing.
 */
export function pageSetupDefaults(): PdfPageSetup {
  return {
    pdf_paper_width: '210mm',
    pdf_paper_height: '297mm',
    pdf_orientation: 'portrait',
    pdf_margin_top: '0',
    pdf_margin_right: '0',
    pdf_margin_bottom: '0',
    pdf_margin_left: '0',
    pdf_page_numbers: false,
  }
}

/** Pulls the page-setup keys out of the API payload, skipping anything absent. */
export function pageSetupFrom(configData: Record<string, unknown>): Partial<PdfPageSetup> {
  const setup: Record<string, string | boolean> = {}

  for (const key of PAGE_SETUP_KEYS) {
    if (typeof configData[key] === 'string' && configData[key]) {
      setup[key] = configData[key] as string
    }
  }

  if (typeof configData.pdf_page_numbers === 'boolean') {
    setup.pdf_page_numbers = configData.pdf_page_numbers
  }

  return setup as Partial<PdfPageSetup>
}

/** Flattens Vuelidate state into the shape AdminPdfPageSetup renders. */
export function pageSetupErrors(
  v$: Record<string, { $error?: boolean; $errors?: { $message: unknown }[] }>
): Record<string, string | false> {
  const errors: Record<string, string | false> = {}

  for (const key of PAGE_SETUP_KEYS) {
    const field = v$[key]
    errors[key] = field?.$error ? String(field.$errors?.[0]?.$message ?? '') : false
  }

  return errors
}
