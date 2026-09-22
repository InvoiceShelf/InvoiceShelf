<template>
  <div
    class="
      fixed inset-x-0 top-0 z-50 flex flex-col items-center w-full px-4
      pointer-events-none md:items-end md:px-6
    "
    style="padding-top: calc(var(--app-top-inset, 0px) + 0.75rem)"
  >
    <transition-group
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
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
import { computed } from 'vue'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import NotificationItem from './NotificationItem.vue'
import type { Notification } from './NotificationItem.vue'

const notificationStore = useNotificationStore()

const notifications = computed<Notification[]>(() => {
  return notificationStore.notifications
})
</script>
