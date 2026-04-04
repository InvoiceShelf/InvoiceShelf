import type { ApiError } from '@v2/types/api'

/**
 * Shape of an Axios-like error response.
 */
interface AxiosLikeError {
  response?: {
    status?: number
    statusText?: string
    data?: {
      message?: string
      error?: string | boolean
      errors?: Record<string, string[]>
    }
  }
  message?: string
}

/**
 * Normalized API error result.
 */
export interface NormalizedApiError {
  message: string
  statusCode: number | null
  validationErrors: Record<string, string[]>
  isUnauthorized: boolean
  isValidationError: boolean
  isNetworkError: boolean
}

/**
 * Known error message to translation key map.
 */
const ERROR_TRANSLATION_MAP: Record<string, string> = {
  'These credentials do not match our records.': 'errors.login_invalid_credentials',
  'invalid_key': 'errors.invalid_provider_key',
  'This feature is available on Starter plan and onwards!': 'errors.starter_plan',
  'taxes_attached': 'settings.tax_types.already_in_use',
  'expense_attached': 'settings.expense_category.already_in_use',
  'payments_attached': 'settings.payment_modes.payments_attached',
  'expenses_attached': 'settings.payment_modes.expenses_attached',
  'role_attached_to_users': 'settings.roles.already_in_use',
  'items_attached': 'settings.customization.items.already_in_use',
  'payment_attached_message': 'invoices.payment_attached_message',
  'The email has already been taken.': 'validation.email_already_taken',
  'Relation estimateItems exists.': 'items.item_attached_message',
  'Relation invoiceItems exists.': 'items.item_attached_message',
  'Relation taxes exists.': 'settings.tax_types.already_in_use',
  'Relation payments exists.': 'errors.payment_attached',
  'The estimate number has already been taken.': 'errors.estimate_number_used',
  'The payment number has already been taken.': 'errors.estimate_number_used',
  'The invoice number has already been taken.': 'errors.invoice_number_used',
  'The name has already been taken.': 'errors.name_already_taken',
  'total_invoice_amount_must_be_more_than_paid_amount': 'invoices.invalid_due_amount_message',
  'you_cannot_edit_currency': 'customers.edit_currency_not_allowed',
  'receipt_does_not_exist': 'errors.receipt_does_not_exist',
  'customer_cannot_be_changed_after_payment_is_added': 'errors.customer_cannot_be_changed_after_payment_is_added',
  'invalid_credentials': 'errors.invalid_credentials',
  'not_allowed': 'errors.not_allowed',
  'invalid_state': 'errors.invalid_state',
  'invalid_city': 'errors.invalid_city',
  'invalid_postal_code': 'errors.invalid_postal_code',
  'invalid_format': 'errors.invalid_format',
  'api_error': 'errors.api_error',
  'feature_not_enabled': 'errors.feature_not_enabled',
  'request_limit_met': 'errors.request_limit_met',
  'address_incomplete': 'errors.address_incomplete',
  'invalid_address': 'errors.invalid_address',
  'Email could not be sent to this email address.': 'errors.email_could_not_be_sent',
}

/**
 * Handle an API error and return a normalized error object.
 *
 * @param err - The error from an API call (typically an Axios error)
 * @returns A normalized error with extracted message, status, and validation errors
 */
export function handleApiError(err: unknown): NormalizedApiError {
  const axiosError = err as AxiosLikeError

  if (!axiosError.response) {
    return {
      message: 'Please check your internet connection or wait until servers are back online.',
      statusCode: null,
      validationErrors: {},
      isUnauthorized: false,
      isValidationError: false,
      isNetworkError: true,
    }
  }

  const { response } = axiosError
  const statusCode = response.status ?? null
  const isUnauthorized =
    response.statusText === 'Unauthorized' ||
    response.data?.message === ' Unauthorized.' ||
    statusCode === 401

  if (isUnauthorized) {
    const message = response.data?.message ?? 'Unauthorized'
    return {
      message,
      statusCode,
      validationErrors: {},
      isUnauthorized: true,
      isValidationError: false,
      isNetworkError: false,
    }
  }

  const validationErrors = response.data?.errors ?? {}
  const isValidationError = Object.keys(validationErrors).length > 0

  if (isValidationError) {
    const firstErrorKey = Object.keys(validationErrors)[0]
    const firstErrorMessage = validationErrors[firstErrorKey]?.[0] ?? 'Validation error'
    return {
      message: firstErrorMessage,
      statusCode,
      validationErrors,
      isUnauthorized: false,
      isValidationError: true,
      isNetworkError: false,
    }
  }

  const errorField = response.data?.error
  let message: string

  if (typeof errorField === 'string') {
    message = errorField
  } else {
    message = response.data?.message ?? 'An unexpected error occurred'
  }

  return {
    message,
    statusCode,
    validationErrors: {},
    isUnauthorized: false,
    isValidationError: false,
    isNetworkError: false,
  }
}

/**
 * Extract validation errors from an API error response.
 *
 * @param err - The error from an API call
 * @returns A record mapping field names to arrays of error messages
 */
export function extractValidationErrors(err: unknown): Record<string, string[]> {
  const axiosError = err as AxiosLikeError
  return axiosError.response?.data?.errors ?? {}
}

/**
 * Look up the translation key for a known error message.
 *
 * @param errorMessage - The raw error message
 * @returns The translation key if known, or null if not mapped
 */
export function getErrorTranslationKey(errorMessage: string): string | null {
  return ERROR_TRANSLATION_MAP[errorMessage] ?? null
}
