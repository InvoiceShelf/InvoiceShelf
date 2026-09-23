<template>
  <!--
    Announced through the live region in NotificationRoot, not from here.
    Hover and focus both pause the timer.
  -->
  <!-- eslint-disable-next-line vuejs-accessibility/no-static-element-interactions -->
  <div
    class="
      w-full max-w-sm mb-2.5 border rounded-xl pointer-events-auto
      md:w-96 glass-strong
    "
    @mouseenter="clearNotificationTimeOut"
    @mouseleave="setNotificationTimeOut"
    @focusin="clearNotificationTimeOut"
    @focusout="setNotificationTimeOut"
  >
    <div class="overflow-hidden rounded-xl">
      <div class="p-3.5">
        <div class="flex items-start">
          <div class="shrink-0">
            <svg
              v-if="success"
              aria-hidden="true"
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
              aria-hidden="true"
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
              aria-hidden="true"
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
              aria-hidden="true"
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
          <div class="flex-1 w-0 ms-3 text-start">
            <p
              class="text-sm font-medium leading-5 text-heading"
            >
              {{ title }}
            </p>
            <p
              class="mt-0.5 text-sm leading-5 text-muted"
            >
              {{ message }}
            </p>
          </div>
          <div class="flex shrink-0">
            <button
              type="button"
              class="p-1 -m-1 transition-colors rounded-md text-muted hover:text-heading focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
              :aria-label="$t('general.close')"
              @click="hideNotificationAction"
            >
              <svg
                aria-hidden="true"
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
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { notificationMessage, notificationTitle } from './notification-text'

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
const { t } = useI18n()

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

const title = computed<string>(() => notificationTitle(props.notification, t))

const message = computed<string>(() => notificationMessage(props.notification, t))

function hideNotificationAction(): void {
  notificationStore.hideNotification(props.notification)
}

function clearNotificationTimeOut(): void {
  if (notiTimeOut.value) {
    clearTimeout(notiTimeOut.value)
  }
}

function setNotificationTimeOut(): void {
  clearNotificationTimeOut()

  // An error stays until it is dismissed: it may need reading twice
  if (error.value && !props.notification.time) {
    return
  }

  notiTimeOut.value = setTimeout(() => {
    notificationStore.hideNotification(props.notification)
  }, props.notification.time || 5000)
}

onMounted(() => {
  setNotificationTimeOut()
})
</script>
