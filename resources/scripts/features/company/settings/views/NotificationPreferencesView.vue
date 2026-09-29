<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { inboxService } from '@/scripts/api/services/inbox.service'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { handleApiError } from '@/scripts/utils/error-handling'
import type { NotificationPreference } from '@/scripts/types/domain/inbox'
import NotificationTypeTable from '../components/NotificationTypeTable.vue'

type Channel = 'bell' | 'mail'

const { t } = useI18n()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()

const preferences = ref<NotificationPreference[]>([])
const isFetching = ref<boolean>(true)
const saving = ref<boolean>(false)

const columns = computed(() => [
  { key: 'bell', label: t('inbox.preferences.bell') },
  { key: 'mail', label: t('inbox.preferences.mail') },
])

onMounted(async () => {
  try {
    preferences.value = await inboxService.preferences()
  } catch (err: unknown) {
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    isFetching.value = false
  }
})

async function save(type: string, choice: { bell?: boolean | null; mail?: boolean | null }): Promise<void> {
  saving.value = true
  try {
    preferences.value = await inboxService.updatePreferences({ [type]: choice })
    notificationStore.showNotification({ type: 'success', message: 'inbox.preferences.saved' })
  } catch (err: unknown) {
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    saving.value = false
  }
}

function change(row: NotificationPreference, key: string, value: boolean): void {
  row[key as Channel] = value
  void save(row.type, { [key]: value })
}

function isCustomised(row: NotificationPreference): boolean {
  return row.customised.bell || row.customised.mail
}

function reset(row: NotificationPreference): void {
  void save(row.type, { bell: null, mail: null })
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
        class="w-full mt-4 h-14"
        rounded
      />
    </BaseContentPlaceholders>

    <NotificationTypeTable
      v-else
      :rows="preferences"
      :columns="columns"
      :busy="saving"
      :row-disabled="(row) => !row.enabled"
      @change="change"
    >
      <template #note="{ row }">
        <p v-if="!row.enabled" class="mt-1 text-xs text-muted">
          {{ $t('inbox.preferences.switched_off', { company: companyStore.selectedCompany?.name ?? '' }) }}
        </p>
        <button
          v-else-if="isCustomised(row)"
          type="button"
          class="mt-1 text-xs font-medium text-primary-600 hover:text-primary-700"
          :disabled="saving"
          @click="reset(row)"
        >
          {{ $t('inbox.preferences.reset') }}
        </button>
      </template>
    </NotificationTypeTable>
  </BaseSettingCard>
</template>
