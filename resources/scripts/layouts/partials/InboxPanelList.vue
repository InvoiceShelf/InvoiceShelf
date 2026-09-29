<template>
  <!-- Announcements for the whole install, pinned above the notices -->
  <section v-if="inboxStore.announcements.length" class="pt-1 pb-2" :aria-label="$t('announcements.title')">
    <ul class="space-y-1.5">
      <li
        v-for="announcement in inboxStore.announcements"
        :key="announcement.id"
        class="flex items-start gap-3 px-3 py-2.5 rounded-xl"
        :class="tone(announcement.level)"
      >
        <BaseIcon
          :name="announcement.level === 'critical' ? 'ExclamationTriangleIcon' : 'MegaphoneIcon'"
          class="w-4.5 h-4.5 mt-0.5 shrink-0"
          aria-hidden="true"
        />
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold leading-5">{{ announcement.title }}</p>
          <p class="mt-0.5 text-sm leading-5 whitespace-pre-line opacity-90">{{ announcement.body }}</p>
          <a
            v-if="announcement.link_url"
            :href="announcement.link_url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-block mt-1 text-sm font-medium underline underline-offset-2"
          >
            {{ announcement.link_label || $t('announcements.open') }}
          </a>
        </div>
        <button
          type="button"
          class="inline-flex items-center justify-center w-7 h-7 -me-1 rounded-md shrink-0 hover:bg-hover"
          :aria-label="`${$t('announcements.dismiss')}: ${announcement.title}`"
          @click="inboxStore.dismissAnnouncement(announcement.id)"
        >
          <BaseIcon name="XMarkIcon" class="w-4 h-4" aria-hidden="true" />
        </button>
      </li>
    </ul>
  </section>

  <div v-if="inboxStore.loadingRecent && !inboxStore.recent.length" class="px-3 py-2 space-y-3">
    <BaseContentPlaceholders v-for="n in 3" :key="n">
      <BaseContentPlaceholdersText :lines="2" />
    </BaseContentPlaceholders>
  </div>

  <BaseEmptyPlaceholder
    v-else-if="!inboxStore.recent.length && !inboxStore.announcements.length"
    icon="BellIcon"
    compact
    :title="$t('inbox.empty_title')"
    :description="$t('inbox.empty_description')"
  />

  <ul v-else-if="inboxStore.recent.length" class="py-1 space-y-0.5">
    <li v-for="notice in inboxStore.recent" :key="notice.id">
      <InboxNoticeItem :notice="notice" @open="emit('open', $event)" />
    </li>
  </ul>
</template>

<script setup lang="ts">
import { useInboxStore } from '@/scripts/stores/inbox.store'
import InboxNoticeItem from '@/scripts/features/company/notifications/components/InboxNoticeItem.vue'
import type { InboxNotice } from '@/scripts/types/domain/inbox'
import type { AnnouncementLevel } from '@/scripts/types/domain/announcement'

const emit = defineEmits<{
  (e: 'open', notice: InboxNotice): void
}>()

const inboxStore = useInboxStore()

function tone(level: AnnouncementLevel): string {
  return {
    critical: 'bg-alert-error-bg text-alert-error-text',
    warning: 'bg-alert-warning-bg text-alert-warning-text',
    info: 'bg-alert-info-bg text-alert-info-text',
  }[level]
}
</script>
