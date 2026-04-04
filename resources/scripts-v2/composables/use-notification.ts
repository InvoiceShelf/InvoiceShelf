import { ref, readonly } from 'vue'
import type { DeepReadonly, Ref } from 'vue'
import { NOTIFICATION_TYPE } from '@v2/config/constants'
import type { NotificationType } from '@v2/config/constants'

export interface Notification {
  id: string
  type: NotificationType
  message: string
}

export interface UseNotificationReturn {
  notifications: DeepReadonly<Ref<Notification[]>>
  showSuccess: (message: string) => void
  showError: (message: string) => void
  showInfo: (message: string) => void
  showWarning: (message: string) => void
  showNotification: (type: NotificationType, message: string) => void
  hideNotification: (id: string) => void
  clearAll: () => void
}

const notifications = ref<Notification[]>([])

/**
 * Generate a unique ID for a notification.
 */
function generateId(): string {
  return (
    Math.random().toString(36) + Date.now().toString(36)
  ).substring(2)
}

/**
 * Composable for managing application-wide notifications.
 * Provides typed helpers for success, error, info, and warning notifications.
 */
export function useNotification(): UseNotificationReturn {
  function showNotification(type: NotificationType, message: string): void {
    notifications.value.push({
      id: generateId(),
      type,
      message,
    })
  }

  function showSuccess(message: string): void {
    showNotification(NOTIFICATION_TYPE.SUCCESS, message)
  }

  function showError(message: string): void {
    showNotification(NOTIFICATION_TYPE.ERROR, message)
  }

  function showInfo(message: string): void {
    showNotification(NOTIFICATION_TYPE.INFO, message)
  }

  function showWarning(message: string): void {
    showNotification(NOTIFICATION_TYPE.WARNING, message)
  }

  function hideNotification(id: string): void {
    notifications.value = notifications.value.filter(
      (notification) => notification.id !== id
    )
  }

  function clearAll(): void {
    notifications.value = []
  }

  return {
    notifications: readonly(notifications),
    showSuccess,
    showError,
    showInfo,
    showWarning,
    showNotification,
    hideNotification,
    clearAll,
  }
}
