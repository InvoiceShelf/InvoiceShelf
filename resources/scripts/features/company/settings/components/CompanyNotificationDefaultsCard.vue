<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { inboxService } from '@/scripts/api/services/inbox.service'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { handleApiError } from '@/scripts/utils/error-handling'
import type { CompanyNotificationDefault } from '@/scripts/types/domain/inbox'
import NotificationTypeTable from './NotificationTypeTable.vue'

type Field = 'enabled' | 'bell' | 'mail'

const { t } = useI18n()
const notificationStore = useNotificationStore()

const defaults = ref<CompanyNotificationDefault[]>([])
const isFetching = ref<boolean>(true)
const saving = ref<boolean>(false)

const columns = computed(() => [
  { key: 'enabled', label: t('inbox.company_defaults.enabled') },
  { key: 'bell', label: t('inbox.preferences.bell') },
  { key: 'mail', label: t('inbox.preferences.mail') },
])

onMounted(async () => {
  try {
    defaults.value = await inboxService.companyDefaults()
  } catch (err: unknown) {
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    isFetching.value = false
  }
})

async function change(row: CompanyNotificationDefault, key: string, value: boolean): Promise<void> {
  const previous = row[key as Field]
  row[key as Field] = value
  saving.value = true
  try {
    defaults.value = await inboxService.updateCompanyDefaults({ [row.type]: { [key]: value } })
    notificationStore.showNotification({ type: 'success', message: 'general.setting_updated' })
  } catch (err: unknown) {
    row[key as Field] = previous
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <BaseSettingCard
    :title="$t('inbox.company_defaults.title')"
    :description="$t('inbox.company_defaults.description')"
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
      :rows="defaults"
      :columns="columns"
      :busy="saving"
      :cell-disabled="(row, key) => key !== 'enabled' && !row.enabled"
      @change="change"
    />
  </BaseSettingCard>
</template>
