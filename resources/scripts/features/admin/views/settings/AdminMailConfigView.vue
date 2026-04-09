<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { mailService } from '@/scripts/api/services/mail.service'
import type { MailConfig, MailDriver } from '@/scripts/types/mail-config'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import MailConfigurationForm from '@/scripts/features/company/settings/components/MailConfigurationForm.vue'
import MailTestModal from '@/scripts/features/company/settings/components/MailTestModal.vue'

const { t } = useI18n()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()

const isSaving = ref(false)
const isFetchingInitialData = ref(false)
const mailConfigData = ref<MailConfig | null>(null)
const mailDrivers = ref<MailDriver[]>([])

loadData()

async function loadData(): Promise<void> {
  isFetchingInitialData.value = true

  try {
    const [driversResponse, configResponse] = await Promise.all([
      mailService.getDrivers(),
      mailService.getConfig(),
    ])

    mailDrivers.value = driversResponse
    mailConfigData.value = configResponse
  } catch (error: unknown) {
    const normalizedError = handleApiError(error)
    notificationStore.showNotification({
      type: 'error',
      message: getErrorTranslationKey(normalizedError.message) ?? normalizedError.message,
    })
  } finally {
    isFetchingInitialData.value = false
  }
}

async function saveEmailConfig(value: MailConfig): Promise<void> {
  isSaving.value = true

  try {
    const response = await mailService.saveConfig(value)

    if (response.success) {
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.mail.mail_config_updated',
      })

      if (mailConfigData.value) {
        mailConfigData.value = {
          ...mailConfigData.value,
          ...value,
        }
      }
    }
  } catch (error: unknown) {
    const normalizedError = handleApiError(error)
    notificationStore.showNotification({
      type: 'error',
      message: getErrorTranslationKey(normalizedError.message) ?? normalizedError.message,
    })
  } finally {
    isSaving.value = false
  }
}

function openMailTestModal(): void {
  modalStore.openModal({
    title: t('general.test_mail_conf'),
    componentName: 'MailTestModal',
    size: 'sm',
  })
}
</script>

<template>
  <MailTestModal store-type="global" />

  <BaseSettingCard
    :title="$t('settings.mail.mail_config')"
    :description="$t('settings.mail.mail_config_desc')"
  >
    <div v-if="mailConfigData" class="mt-14">
      <MailConfigurationForm
        :config-data="mailConfigData"
        :is-saving="isSaving"
        :mail-drivers="mailDrivers"
        :is-fetching-initial-data="isFetchingInitialData"
        @submit-data="saveEmailConfig"
      >
        <BaseButton
          variant="primary-outline"
          type="button"
          class="ml-2"
          :content-loading="isFetchingInitialData"
          @click="openMailTestModal"
        >
          {{ $t('general.test_mail_conf') }}
        </BaseButton>
      </MailConfigurationForm>
    </div>
  </BaseSettingCard>
</template>
