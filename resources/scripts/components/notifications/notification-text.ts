import type { Notification } from './NotificationItem.vue'

type Translate = (key: string) => string

/** A notification's heading: its own title, or the word for its type */
export function notificationTitle(notification: Notification, t: Translate): string {
  return notification.title || t(`notifications.${notification.type}`)
}

/** A notification's text: its own message, or a general one for its type */
export function notificationMessage(notification: Notification, t: Translate): string {
  if (notification.message) {
    return t(notification.message)
  }

  return notification.type === 'success' ? t('general.successful') : t('general.something_went_wrong')
}
