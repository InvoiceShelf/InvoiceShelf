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
const CSS_LENGTH = /^\d+(\.\d+)?(pt|px|pc|mm|cm|in)$/

export function cssLength(t: (key: string) => string) {
  return {
    cssLength: helpers.withMessage(t('validation.invalid_length'), (value: string) =>
      !helpers.req(value) ? true : CSS_LENGTH.test(String(value).trim())
    ),
  }
}

/** Matches the defaults in config/pdf.php, including dompdf's own 1.2cm margin. */
export function pageSetupDefaults(): PdfPageSetup {
  return {
    pdf_paper_width: '210mm',
    pdf_paper_height: '297mm',
    pdf_orientation: 'portrait',
    pdf_margin_top: '1.2cm',
    pdf_margin_right: '1.2cm',
    pdf_margin_bottom: '1.2cm',
    pdf_margin_left: '1.2cm',
  }
}

/** Pulls the page-setup keys out of the API payload, skipping anything absent. */
export function pageSetupFrom(configData: Record<string, unknown>): Partial<PdfPageSetup> {
  const setup: Record<string, string> = {}

  for (const key of PAGE_SETUP_KEYS) {
    if (typeof configData[key] === 'string' && configData[key]) {
      setup[key] = configData[key] as string
    }
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
