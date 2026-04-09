import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'

interface InstallationResponse {
  success?: boolean | string
  error?: string | boolean
  error_message?: string
  message?: string
}

export function useInstallationFeedback() {
  const { t } = useI18n()
  const notificationStore = useNotificationStore()

  function isSuccessfulResponse(response: InstallationResponse | null | undefined): boolean {
    return Boolean(response?.success) && !response?.error && !response?.error_message
  }

  function showResponseError(response: InstallationResponse | null | undefined): void {
    const candidate =
      typeof response?.error_message === 'string' && response.error_message.trim()
        ? response.error_message
        : typeof response?.error === 'string' && response.error.trim()
          ? response.error
          : typeof response?.message === 'string' && response.message.trim()
            ? response.message
            : ''

    notificationStore.showNotification({
      type: 'error',
      message: resolveMessage(candidate),
    })
  }

  function showRequestError(error: unknown): void {
    if (error instanceof Error && !('response' in error) && error.message.trim()) {
      notificationStore.showNotification({
        type: 'error',
        message: resolveMessage(error.message),
      })

      return
    }

    const normalizedError = handleApiError(error)

    notificationStore.showNotification({
      type: 'error',
      message: resolveMessage(normalizedError.message),
    })
  }

  function resolveMessage(message: string): string {
    const normalizedMessage = message.trim()

    if (!normalizedMessage) {
      return 'validation.something_went_wrong'
    }

    const wizardErrorKey = `wizard.errors.${normalizedMessage}`

    if (t(wizardErrorKey) !== wizardErrorKey) {
      return wizardErrorKey
    }

    return getErrorTranslationKey(normalizedMessage) ?? normalizedMessage
  }

  return {
    isSuccessfulResponse,
    showRequestError,
    showResponseError,
  }
}
