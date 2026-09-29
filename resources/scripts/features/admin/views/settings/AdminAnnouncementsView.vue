<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { announcementService } from '@/scripts/api/services/announcement.service'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import AdminAnnouncementModal from '@/scripts/features/admin/components/settings/AdminAnnouncementModal.vue'
import type { Announcement } from '@/scripts/types/domain/announcement'

/**
 * Announcements for everyone on this install: the ones written here, and the
 * InvoiceShelf project's, which can only be hidden.
 */
const { t } = useI18n()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()

const announcements = ref<Announcement[]>([])
const isFetching = ref<boolean>(true)
const showModal = ref<boolean>(false)
const editing = ref<Announcement | null>(null)

async function load(): Promise<void> {
  try {
    announcements.value = await announcementService.list()
  } catch (err: unknown) {
    notify(err)
  } finally {
    isFetching.value = false
  }
}

function notify(err: unknown): void {
  const { message } = handleApiError(err)
  notificationStore.showNotification({ type: 'error', message: getErrorTranslationKey(message) ?? message })
}

function open(announcement: Announcement | null): void {
  editing.value = announcement
  showModal.value = true
}

function windowLabel(announcement: Announcement): string {
  const format = (value: string) => new Date(value).toLocaleString()
  if (announcement.starts_at && announcement.ends_at) {
    return t('announcements.window_between', { from: format(announcement.starts_at), until: format(announcement.ends_at) })
  }
  if (announcement.ends_at) {
    return t('announcements.window_until', { until: format(announcement.ends_at) })
  }
  if (announcement.starts_at) {
    return t('announcements.window_from', { from: format(announcement.starts_at) })
  }
  return t('announcements.window_always')
}

async function setHidden(announcement: Announcement, hidden: boolean): Promise<void> {
  try {
    const updated = await announcementService.setHidden(announcement.id, hidden)
    announcements.value = announcements.value.map((a) => (a.id === updated.id ? updated : a))
  } catch (err: unknown) {
    notify(err)
  }
}

function confirmDelete(announcement: Announcement): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('announcements.confirm_delete'),
      yesLabel: t('general.delete'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (confirmed: boolean) => {
      if (!confirmed) return
      try {
        await announcementService.remove(announcement.id)
        notificationStore.showNotification({ type: 'success', message: t('announcements.deleted') })
        await load()
      } catch (err: unknown) {
        notify(err)
      }
    })
}

const levelTone: Record<string, string> = {
  info: 'bg-alert-info-bg text-alert-info-text',
  warning: 'bg-alert-warning-bg text-alert-warning-text',
  critical: 'bg-alert-error-bg text-alert-error-text',
}

onMounted(load)
</script>

<template>
  <BaseSettingCard :title="$t('announcements.title')" :description="$t('announcements.description')">
    <template #action>
      <BaseButton variant="primary-outline" :disabled="isFetching" @click="open(null)">
        <template #left="slotProps">
          <BaseIcon name="PlusIcon" :class="slotProps.class" />
        </template>
        {{ $t('announcements.add') }}
      </BaseButton>
    </template>

    <BaseContentPlaceholders v-if="isFetching" rounded>
      <BaseContentPlaceholdersBox class="w-full h-32 mt-6" rounded />
    </BaseContentPlaceholders>

    <BaseEmptyPlaceholder
      v-else-if="!announcements.length"
      icon="MegaphoneIcon"
      :title="$t('announcements.empty_title')"
      :description="$t('announcements.empty_description')"
    />

    <ul v-else class="mt-6 border divide-y rounded-lg divide-line-light border-line-default">
      <li v-for="announcement in announcements" :key="announcement.id" class="flex flex-wrap items-center gap-4 px-4 py-3">
        <div class="flex-1 min-w-0">
          <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-heading">
            {{ announcement.title }}
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="levelTone[announcement.level]">
              {{ $t(`announcements.levels.${announcement.level}`) }}
            </span>
            <span
              v-if="announcement.source === 'feed'"
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-surface-tertiary text-muted ring-1 ring-inset ring-line-default"
            >
              {{ $t('announcements.from_feed') }}
            </span>
          </p>
          <p class="text-xs text-muted">
            {{ $t(`announcements.audiences.${announcement.audience}`) }} · {{ windowLabel(announcement) }}
          </p>
        </div>

        <BaseSwitch
          v-if="announcement.source === 'feed'"
          :model-value="!announcement.hidden"
          :label-left="$t('announcements.shown')"
          @update:model-value="setHidden(announcement, !$event)"
        />
        <template v-else>
          <BaseButton size="sm" variant="white" @click="open(announcement)">{{ $t('general.edit') }}</BaseButton>
          <BaseButton
            size="sm"
            variant="white"
            :aria-label="`${$t('general.delete')} ${announcement.title}`"
            @click="confirmDelete(announcement)"
          >
            <BaseIcon name="TrashIcon" class="w-4 h-4 text-danger" aria-hidden="true" />
          </BaseButton>
        </template>
      </li>
    </ul>
  </BaseSettingCard>

  <AdminAnnouncementModal :show="showModal" :announcement="editing" @close="showModal = false" @saved="load" />
</template>
