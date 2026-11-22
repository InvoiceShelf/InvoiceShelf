<template>
  <div
    v-if="current"
    class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 px-4 py-2 text-sm border-b shrink-0 border-line-light"
    :class="tone"
    :role="current.level === 'critical' ? 'alert' : 'status'"
  >
    <span class="flex items-center min-w-0 gap-2">
      <BaseIcon :name="current.level === 'critical' ? 'ExclamationTriangleIcon' : 'MegaphoneIcon'" class="w-4 h-4 shrink-0" aria-hidden="true" />
      <span class="font-semibold">{{ current.title }}</span>
      <span class="hidden font-normal sm:inline">{{ current.body }}</span>
    </span>

    <a
      v-if="current.link_url"
      :href="current.link_url"
      target="_blank"
      rel="noopener noreferrer"
      class="px-2.5 py-1 text-xs font-semibold rounded-md bg-surface text-heading border border-line-default hover:bg-hover"
    >
      {{ current.link_label || $t('announcements.open') }}
    </a>

    <span v-if="banners.length > 1" class="text-xs font-normal opacity-80">
      {{ $t('announcements.position', { current: 1, total: banners.length }) }}
    </span>

    <button
      type="button"
      class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-hover"
      :aria-label="$t('announcements.dismiss')"
      @click="inboxStore.dismissAnnouncement(current.id)"
    >
      <BaseIcon name="XMarkIcon" class="w-4 h-4" aria-hidden="true" />
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useInboxStore } from '@/scripts/stores/inbox.store'

/**
 * Warning and critical announcements, one at a time across the top of the
 * app until each is dismissed. Info ones only show in the bell.
 */
const inboxStore = useInboxStore()

const banners = computed(() => inboxStore.announcements.filter((a) => a.level !== 'info'))

const current = computed(() => banners.value[0] ?? null)

const tone = computed<string>(() =>
  current.value?.level === 'critical'
    ? 'bg-alert-error-bg text-alert-error-text'
    : 'bg-alert-warning-bg text-alert-warning-text',
)
</script>
