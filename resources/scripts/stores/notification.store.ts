import { defineStore } from 'pinia'
import { ref } from 'vue'

export type NotificationType = 'success' | 'error' | 'warning' | 'info'

export interface Notification {
  id: string
  type: NotificationType
  message: string
}

export interface ShowNotificationPayload {
  type: NotificationType
  message: string
}

export const useNotificationStore = defineStore('notification', () => {
  // State
  const active = ref<boolean>(false)
  const autoHide = ref<boolean>(true)
  const notifications = ref<Notification[]>([])

  // Actions
  function showNotification(notification: ShowNotificationPayload): void {
    notifications.value.push({
      ...notification,
      id: (Math.random().toString(36) + Date.now().toString(36)).substring(2),
    })
  }

  function hideNotification(data: { id: string }): void {
    notifications.value = notifications.value.filter(
      (notification) => notification.id !== data.id
    )
  }

  return {
    active,
    autoHide,
    notifications,
    showNotification,
    hideNotification,
  }
})
