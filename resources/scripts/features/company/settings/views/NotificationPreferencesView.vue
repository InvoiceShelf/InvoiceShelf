<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { inboxService } from '@/scripts/api/services/inbox.service'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { handleApiError } from '@/scripts/utils/error-handling'
import type { InboxGroup, NotificationPreference } from '@/scripts/types/domain/inbox'

type Channel = 'bell' | 'mail'

const { t } = useI18n()
const notificationStore = useNotificationStore()

const preferences = ref<NotificationPreference[]>([])
const isFetching = ref<boolean>(true)
const saving = ref<string | null>(null)

/** The types grouped by area, in the order the server lists them. */
const groups = computed<Array<{ group: InboxGroup; items: NotificationPreference[] }>>(() => {
  const grouped = new Map<InboxGroup, NotificationPreference[]>()
  for (const preference of preferences.value) {
    grouped.set(preference.group, [...(grouped.get(preference.group) ?? []), preference])
  }
  return [...grouped.entries()].map(([group, items]) => ({ group, items }))
})

onMounted(async () => {
  try {
    preferences.value = await inboxService.preferences()
  } catch (err: unknown) {
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    isFetching.value = false
  }
})

async function change(preference: NotificationPreference, channel: Channel, value: boolean): Promise<void> {
  const previous = preference[channel]
  preference[channel] = value
  saving.value = `${preference.type}.${channel}`

  try {
    preferences.value = await inboxService.updatePreferences({ [preference.type]: { [channel]: value } })
    notificationStore.showNotification({ type: 'success', message: 'inbox.preferences.saved' })
  } catch (err: unknown) {
    preference[channel] = previous
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    saving.value = null
  }
}

function switchLabel(preference: NotificationPreference, channel: Channel): string {
  return `${t(`inbox.types.${preference.type}.label`)}: ${t(`inbox.preferences.${channel}`)}`
}
</script>

<template>
  <BaseSettingCard
    :title="$t('inbox.preferences.title')"
    :description="$t('inbox.preferences.description')"
  >
    <BaseContentPlaceholders v-if="isFetching" rounded>
      <BaseContentPlaceholdersBox
        v-for="placeholder in 3"
        :key="placeholder"
        class="w-full h-14 mt-4"
        rounded
      />
    </BaseContentPlaceholders>

    <div v-else class="border-t border-line-default">
      <div
        class="grid grid-cols-[minmax(0,1fr)_4rem_4rem] items-center gap-3 py-2 text-xs font-medium uppercase tracking-wide text-subtle"
        aria-hidden="true"
      >
        <span>{{ $t('inbox.preferences.type') }}</span>
        <span class="text-center">{{ $t('inbox.preferences.bell') }}</span>
        <span class="text-center">{{ $t('inbox.preferences.mail') }}</span>
      </div>

      <section v-for="section in groups" :key="section.group" class="border-t border-line-default">
        <h3 class="pt-4 pb-1 text-sm font-semibold text-heading">
          {{ $t(`inbox.groups.${section.group}`) }}
        </h3>

        <div
          v-for="preference in section.items"
          :key="preference.type"
          class="grid grid-cols-[minmax(0,1fr)_4rem_4rem] items-center gap-3 py-3"
        >
          <div class="min-w-0">
            <p class="text-sm font-medium text-body">
              {{ $t(`inbox.types.${preference.type}.label`) }}
            </p>
            <p class="mt-0.5 text-sm text-muted">
              {{ $t(`inbox.types.${preference.type}.description`) }}
            </p>
          </div>

          <div v-for="channel in (['bell', 'mail'] as const)" :key="channel" class="flex justify-center">
            <BaseSwitch
              :model-value="preference[channel]"
              :aria-label="switchLabel(preference, channel)"
              :disabled="saving !== null"
              @update:model-value="change(preference, channel, $event)"
            />
          </div>
        </div>
      </section>
    </div>
  </BaseSettingCard>
</template>
