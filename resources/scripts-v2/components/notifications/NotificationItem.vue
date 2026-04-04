<template>
  <div
    :class="success || info ? 'bg-surface' : 'bg-alert-error-bg'"
    class="
      max-w-sm
      mb-3
      rounded-lg
      shadow-lg
      cursor-pointer
      pointer-events-auto
      w-full
      md:w-96
    "
    @click.stop="hideNotificationAction"
    @mouseenter="clearNotificationTimeOut"
    @mouseleave="setNotificationTimeOut"
  >
    <div class="overflow-hidden rounded-lg shadow-xs">
      <div class="p-4">
        <div class="flex items-start">
          <div class="shrink-0">
            <svg
              v-if="success"
              class="w-6 h-6 text-alert-success-text"
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
              class="w-6 h-6 text-status-blue"
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
              v-if="error"
              class="w-6 h-6 text-alert-error-text"
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
              :class="`text-sm leading-5 font-medium ${
                success || info ? 'text-heading' : 'text-alert-error-text'
              }`"
            >
              {{
                notification.title
                  ? notification.title
                  : success
                  ? 'Success!'
                  : 'Error'
              }}
            </p>
            <p
              :class="`mt-1 text-sm leading-5 ${
                success || info ? 'text-muted' : 'text-alert-error-text'
              }`"
            >
              {{
                notification.message
                  ? notification.message
                  : success
                  ? 'Successful'
                  : 'Something went wrong'
              }}
            </p>
          </div>
          <div class="flex shrink-0">
            <button
              :class="
                success || info
                  ? ' text-subtle focus:text-muted'
                  : 'text-alert-error-text focus:text-alert-error-text'
              "
              class="
                inline-flex
                w-5
                h-5
                transition
                duration-150
                ease-in-out
                focus:outline-hidden
              "
              @click="hideNotificationAction"
            >
              <svg
                class="w-6 h-6"
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
import { useNotificationStore } from '@v2/stores/notification.store'

export type NotificationType = 'success' | 'error' | 'info'

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
