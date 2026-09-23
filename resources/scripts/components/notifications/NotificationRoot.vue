<template>
  <!-- What the newest notification says, for screen readers -->
  <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ politeText }}</div>
  <div class="sr-only" role="alert" aria-live="assertive" aria-atomic="true">{{ assertiveText }}</div>

  <div
    class="
      fixed inset-x-0 top-0 z-50 flex flex-col items-center w-full px-4
      pointer-events-none md:items-end md:px-6
    "
    style="padding-top: calc(var(--app-top-inset, 0px) + 0.75rem)"
  >
    <transition-group
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2 rtl:sm:-translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <NotificationItem
        v-for="notification in notifications"
        :key="notification.id"
        :notification="notification"
      />
    </transition-group>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import NotificationItem from './NotificationItem.vue'
import { notificationMessage, notificationTitle } from './notification-text'
import type { Notification } from './NotificationItem.vue'

const notificationStore = useNotificationStore()
const { t } = useI18n()

const notifications = computed<Notification[]>(() => {
  return notificationStore.notifications
})

// The live regions stay mounted; each new notification is written into one
// of them, errors assertively and everything else politely
const politeText = ref<string>('')
const assertiveText = ref<string>('')
const announced = new Set<string>()

watch(notifications, async (list) => {
  const fresh = list.filter((notification) => !announced.has(notification.id))

  for (const notification of fresh) {
    announced.add(notification.id)

    const text = `${notificationTitle(notification, t)}. ${notificationMessage(notification, t)}`
    const target = notification.type === 'error' ? assertiveText : politeText

    // Clear first, so the same words twice in a row are still announced
    target.value = ''
    await nextTick()
    target.value = text
  }
}, { deep: true })
</script>
