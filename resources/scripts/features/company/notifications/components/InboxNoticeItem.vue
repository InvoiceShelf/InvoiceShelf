<template>
  <div
    class="group relative flex items-start gap-3 px-3 py-3 rounded-xl transition-colors hover:bg-hover"
  >
    <span
      class="flex items-center justify-center w-9 h-9 rounded-full shrink-0"
      :class="unread ? 'bg-primary-600/10 text-primary-600' : 'bg-surface-tertiary text-muted'"
      aria-hidden="true"
    >
      <BaseIcon :name="icon(notice)" class="w-4.5 h-4.5" />
    </span>

    <button
      type="button"
      class="flex-1 min-w-0 text-start focus:outline-hidden after:absolute after:inset-0 after:rounded-xl focus-visible:after:ring-2 focus-visible:after:ring-focus"
      @click="emit('open', notice)"
    >
      <span
        class="block text-sm leading-5"
        :class="unread ? 'font-semibold text-heading' : 'font-medium text-body'"
      >
        {{ title(notice) }}
      </span>
      <span v-if="body(notice)" class="block mt-0.5 text-sm leading-5 text-muted line-clamp-2">
        {{ body(notice) }}
      </span>
      <span class="block mt-1 text-xs text-subtle">
        <span v-if="unread" class="sr-only">{{ $t('inbox.unread') }}, </span>
        {{ arrived(notice) }}
      </span>
    </button>

    <div class="relative z-10 flex items-center gap-1 shrink-0">
      <slot name="actions" />
      <span
        v-if="unread && !$slots.actions"
        class="w-2 h-2 mt-2 rounded-full bg-primary-600"
        aria-hidden="true"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useInboxNotice } from '@/scripts/composables/use-inbox-notice'
import type { InboxNotice } from '@/scripts/types/domain/inbox'

const props = defineProps<{ notice: InboxNotice }>()

const emit = defineEmits<{
  (e: 'open', notice: InboxNotice): void
}>()

const { title, body, icon, arrived } = useInboxNotice()

const unread = computed<boolean>(() => props.notice.read_at === null)
</script>
