<template>
  <div v-if="inboxStore.loadingRecent && !inboxStore.recent.length" class="px-3 py-2 space-y-3">
    <BaseContentPlaceholders v-for="n in 3" :key="n">
      <BaseContentPlaceholdersText :lines="2" />
    </BaseContentPlaceholders>
  </div>

  <BaseEmptyPlaceholder
    v-else-if="!inboxStore.recent.length"
    icon="BellIcon"
    compact
    :title="$t('inbox.empty_title')"
    :description="$t('inbox.empty_description')"
  />

  <ul v-else class="py-1 space-y-0.5">
    <li v-for="notice in inboxStore.recent" :key="notice.id">
      <InboxNoticeItem :notice="notice" @open="emit('open', $event)" />
    </li>
  </ul>
</template>

<script setup lang="ts">
import { useInboxStore } from '@/scripts/stores/inbox.store'
import InboxNoticeItem from '@/scripts/features/company/notifications/components/InboxNoticeItem.vue'
import type { InboxNotice } from '@/scripts/types/domain/inbox'

const emit = defineEmits<{
  (e: 'open', notice: InboxNotice): void
}>()

const inboxStore = useInboxStore()
</script>
