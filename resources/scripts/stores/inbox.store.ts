import { defineStore } from 'pinia'
import { ref } from 'vue'
import { inboxService } from '@/scripts/api/services/inbox.service'
import { announcementService } from '@/scripts/api/services/announcement.service'
import { handleApiError } from '@/scripts/utils/error-handling'
import type { InboxNotice } from '@/scripts/types/domain/inbox'
import type { ActiveAnnouncement } from '@/scripts/types/domain/announcement'

/** How many notices the bell's panel shows. */
const RECENT_LIMIT = 8

/**
 * The bell: how many notices are unread, and the latest few for its panel.
 * Named "inbox" because the notification store is the toast queue.
 *
 * Everything here is for the company being looked at (and platform notices);
 * reset() on a company switch.
 */
export const useInboxStore = defineStore('inbox', () => {
  const unreadCount = ref<number>(0)
  const recent = ref<InboxNotice[]>([])
  const recentLoaded = ref<boolean>(false)
  const loadingRecent = ref<boolean>(false)
  /** Announcements for the whole install, from bootstrap; pinned in the bell */
  const announcements = ref<ActiveAnnouncement[]>([])

  function setAnnouncements(list: ActiveAnnouncement[]): void {
    announcements.value = list
  }

  /** Put one away for this user; it stays away until it is changed. */
  async function dismissAnnouncement(id: number): Promise<void> {
    announcements.value = announcements.value.filter((a) => a.id !== id)
    try {
      await announcementService.dismiss(id)
    } catch (err: unknown) {
      handleApiError(err)
    }
  }

  /**
   * Re-read the unread count. A count that moved means new notices, so the
   * panel reloads the next time it opens.
   */
  async function refreshCount(): Promise<void> {
    try {
      const count = await inboxService.unreadCount()
      if (count !== unreadCount.value) {
        recentLoaded.value = false
      }
      unreadCount.value = count
    } catch {
      // A missed poll is not worth a toast; the next one tries again.
    }
  }

  async function loadRecent(): Promise<void> {
    loadingRecent.value = true
    try {
      const page = await inboxService.list({ limit: RECENT_LIMIT })
      recent.value = page.data
      unreadCount.value = page.meta.unread_count
      recentLoaded.value = true
    } catch (err: unknown) {
      handleApiError(err)
    } finally {
      loadingRecent.value = false
    }
  }

  /** Apply a notice's new read state to the panel and the count. */
  function applyReadState(notice: InboxNotice): void {
    const wasUnread = recent.value.find((n) => n.id === notice.id)?.read_at === null
    recent.value = recent.value.map((n) => (n.id === notice.id ? notice : n))

    if (wasUnread && notice.read_at !== null) {
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } else if (!wasUnread && notice.read_at === null) {
      unreadCount.value += 1
    }
  }

  async function markRead(notice: InboxNotice): Promise<InboxNotice> {
    if (notice.read_at !== null) {
      return notice
    }
    const updated = await inboxService.markRead(notice.id)
    if (!recent.value.some((n) => n.id === notice.id)) {
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
    applyReadState(updated)
    return updated
  }

  async function markUnread(notice: InboxNotice): Promise<InboxNotice> {
    const updated = await inboxService.markUnread(notice.id)
    if (!recent.value.some((n) => n.id === notice.id)) {
      unreadCount.value += 1
    }
    applyReadState(updated)
    return updated
  }

  async function markAllRead(): Promise<void> {
    await inboxService.markAllRead()
    const now = new Date().toISOString()
    recent.value = recent.value.map((n) => ({ ...n, read_at: n.read_at ?? now }))
    unreadCount.value = 0
  }

  async function remove(notice: InboxNotice): Promise<void> {
    await inboxService.remove(notice.id)
    recent.value = recent.value.filter((n) => n.id !== notice.id)
    if (notice.read_at === null) {
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
  }

  function reset(): void {
    unreadCount.value = 0
    recent.value = []
    recentLoaded.value = false
  }

  return {
    unreadCount,
    recent,
    announcements,
    setAnnouncements,
    dismissAnnouncement,
    recentLoaded,
    loadingRecent,
    refreshCount,
    loadRecent,
    markRead,
    markUnread,
    markAllRead,
    remove,
    reset,
  }
})
