<template>
  <div
    class="
      w-full max-w-sm mb-2.5 border rounded-xl shadow-lg cursor-pointer pointer-events-auto
      md:w-96 bg-surface border-line-light
    "
    @click.stop="hideNotificationAction"
    @mouseenter="clearNotificationTimeOut"
    @mouseleave="setNotificationTimeOut"
  >
    <div class="overflow-hidden rounded-xl">
      <div class="p-3.5">
        <div class="flex items-start">
          <div class="shrink-0">
            <svg
              v-if="success"
              class="w-5 h-5 text-status-green"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <svg
              v-if="info"
              class="w-5 h-5 text-status-blue"
              fill="currentColor"
              viewBox="0 0 20 20"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                fill-rule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                clip-rule="evenodd"
              ></path>
            </svg>
            <svg
              v-if="warning"
              class="w-5 h-5 text-status-yellow"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
              />
            </svg>
            <svg
              v-if="error"
              class="w-5 h-5 text-status-red"
              fill="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
          <div class="flex-1 w-0 ml-3 text-left">
            <p
              class="text-sm font-medium leading-5 text-heading"
            >
              {{
                notification.title
                  ? notification.title
                  : success
                  ? 'Success!'
                  : warning
                  ? 'Warning'
                  : 'Error'
              }}
            </p>
            <p
              class="mt-0.5 text-sm leading-5 text-muted"
            >
              {{
                notification.message
                  ? $t(notification.message)
                  : success
                  ? $t('general.successful')
                  : $t('general.something_went_wrong')
              }}
            </p>
          </div>
          <div class="flex shrink-0">
            <button
              class="p-1 -m-1 transition-colors rounded-md text-subtle hover:text-body focus:outline-hidden"
              @click="hideNotificationAction"
            >
              <svg
                class="w-4 h-4"
                fill="currentColor"
                viewBox="0 0 20 20"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  fill-rule="evenodd"
                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                  clip-rule="evenodd"
                ></path>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, computed, ref } from 'vue'
import { useNotificationStore } from '@/scripts/stores/notification.store'

export type NotificationType = 'success' | 'error' | 'warning' | 'info'

export interface Notification {
  id: string
  type: NotificationType
  title?: string
  message?: string
  time?: number
}

interface Props {
  notification: Notification
}

const props = defineProps<Props>()

const notificationStore = useNotificationStore()

const notiTimeOut = ref<ReturnType<typeof setTimeout> | null>(null)

const success = computed<boolean>(() => {
  return props.notification.type === 'success'
})

const error = computed<boolean>(() => {
  return props.notification.type === 'error'
})

const info = computed<boolean>(() => {
  return props.notification.type === 'info'
})

const warning = computed<boolean>(() => {
  return props.notification.type === 'warning'
})

function hideNotificationAction(): void {
  notificationStore.hideNotification(props.notification)
}

function clearNotificationTimeOut(): void {
  if (notiTimeOut.value) {
    clearTimeout(notiTimeOut.value)
  }
}

function setNotificationTimeOut(): void {
  notiTimeOut.value = setTimeout(() => {
    notificationStore.hideNotification(props.notification)
  }, props.notification.time || 5000)
}

onMounted(() => {
  setNotificationTimeOut()
})
</script>
