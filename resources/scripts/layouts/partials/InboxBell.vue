<template>
  <!-- Phones: the app bar button opens a bottom sheet -->
  <template v-if="isPhone">
    <button
      type="button"
      class="relative flex items-center justify-center w-10 h-10 rounded-lg text-chrome-fg hover:bg-chrome-hover"
      :aria-label="buttonLabel"
      @click="open = true"
    >
      <BaseIcon name="BellIcon" class="w-5.5 h-5.5" />
      <InboxCountBadge :count="inboxStore.unreadCount" />
    </button>

    <BaseSheet :show="open" :title="$t('inbox.title')" @close="open = false">
      <template #header>
        <button
          v-if="inboxStore.unreadCount > 0"
          type="button"
          class="text-sm font-medium text-primary-600 hover:text-primary-700"
          @click="markAllRead"
        >
          {{ $t('inbox.mark_all_read') }}
        </button>
      </template>
      <InboxPanelList @open="openNotice" />
      <template #footer>
        <RouterLink
          to="/admin/notifications"
          class="block text-sm font-medium text-center text-primary-600 hover:text-primary-700"
          @click="open = false"
        >
          {{ $t('inbox.view_all') }}
        </RouterLink>
      </template>
    </BaseSheet>
  </template>

  <!-- Tablet and desktop: a popover under the bell -->
  <PopoverRoot v-else v-model:open="open">
    <PopoverTrigger as-child>
      <button
        v-tooltip="{ content: $t('inbox.title') }"
        type="button"
        class="relative flex items-center justify-center transition-colors w-9 h-9 rounded-xl text-muted hover:bg-hover-strong hover:text-heading"
        :aria-label="buttonLabel"
      >
        <BaseIcon name="BellIcon" class="w-5 h-5" />
        <InboxCountBadge :count="inboxStore.unreadCount" />
      </button>
    </PopoverTrigger>
    <PopoverPortal>
      <PopoverContent
        side="bottom"
        align="end"
        :side-offset="8"
        :collision-padding="16"
        :aria-label="$t('inbox.title')"
        class="z-50 flex flex-col w-96 max-w-[calc(100vw-2rem)] max-h-[min(34rem,var(--reka-popover-content-available-height))] rounded-2xl border border-line-default bg-surface shadow-lg focus:outline-hidden"
      >
        <div class="flex items-center justify-between gap-3 px-4 pt-3.5 pb-2">
          <h2 class="text-sm font-semibold text-heading">{{ $t('inbox.title') }}</h2>
          <button
            v-if="inboxStore.unreadCount > 0"
            type="button"
            class="text-sm font-medium text-primary-600 hover:text-primary-700"
            @click="markAllRead"
          >
            {{ $t('inbox.mark_all_read') }}
          </button>
        </div>

        <div class="flex-1 min-h-0 px-1.5 overflow-y-auto overscroll-contain">
          <InboxPanelList @open="openNotice" />
        </div>

        <div class="px-4 py-2.5 border-t border-line-light">
          <RouterLink
            to="/admin/notifications"
            class="block text-sm font-medium text-center text-primary-600 hover:text-primary-700"
            @click="open = false"
          >
            {{ $t('inbox.view_all') }}
          </RouterLink>
        </div>
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDocumentVisibility, useIntervalFn } from '@vueuse/core'
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useInboxStore } from '@/scripts/stores/inbox.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { handleApiError } from '@/scripts/utils/error-handling'
import type { InboxNotice } from '@/scripts/types/domain/inbox'
import InboxCountBadge from './InboxCountBadge.vue'
import InboxPanelList from './InboxPanelList.vue'

/** How often the count is re-read while the app is on screen. */
const POLL_MS = 60_000

const inboxStore = useInboxStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const router = useRouter()
const { t } = useI18n()
const { isPhone } = useBreakpoints()
const visibility = useDocumentVisibility()

const open = ref<boolean>(false)

const buttonLabel = computed<string>(() =>
  inboxStore.unreadCount > 0
    ? t('inbox.open_panel', { count: inboxStore.unreadCount })
    : t('inbox.open_panel_none'),
)

// Polled only while the app is on screen. Coming back to it (another tab,
// or a phone app resumed from the background) reads the count at once.
useIntervalFn(() => {
  if (visibility.value === 'visible') {
    void inboxStore.refreshCount()
  }
}, POLL_MS)

watch(visibility, (state) => {
  if (state === 'visible') {
    void inboxStore.refreshCount()
  }
})

// Each company has its own notices.
watch(
  () => companyStore.selectedCompany?.id,
  () => {
    inboxStore.reset()
    open.value = false
    void inboxStore.refreshCount()
  },
  { immediate: true },
)

watch(open, (isOpen) => {
  if (isOpen && !inboxStore.recentLoaded) {
    void inboxStore.loadRecent()
  }
})

async function markAllRead(): Promise<void> {
  try {
    await inboxStore.markAllRead()
  } catch (err: unknown) {
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  }
}

async function openNotice(notice: InboxNotice): Promise<void> {
  open.value = false
  try {
    await inboxStore.markRead(notice)
  } catch {
    // Reading it matters more than marking it.
  }
  if (notice.url) {
    await router.push(notice.url)
  }
}
</script>
