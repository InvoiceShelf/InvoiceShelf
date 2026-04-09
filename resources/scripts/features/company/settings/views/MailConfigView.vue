<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../../stores/modal.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import { companyService } from '../../../../api/services/company.service'
import { mailService } from '../../../../api/services/mail.service'
import type { CompanyMailConfig, MailConfig, MailDriver } from '@/scripts/types/mail-config'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import MailConfigurationForm from '@/scripts/features/company/settings/components/MailConfigurationForm.vue'
import MailTestModal from '@/scripts/features/company/settings/components/MailTestModal.vue'

const { t } = useI18n()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()

const isSaving = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(false)
const useCustomMailConfig = ref<boolean>(false)

const mailConfigData = ref<CompanyMailConfig | null>(null)
const mailDrivers = ref<MailDriver[]>([])

loadData()

async function loadData(): Promise<void> {
  isFetchingInitialData.value = true
  try {
    const [driversResponse, configResponse] = await Promise.all([
      mailService.getDrivers(),
      companyService.getMailConfig(),
    ])

    mailDrivers.value = driversResponse
    mailConfigData.value = configResponse
    useCustomMailConfig.value = configResponse.use_custom_mail_config === 'YES'
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

watch(useCustomMailConfig, async (newVal, oldVal) => {
  if (oldVal === undefined) return

  if (!newVal) {
    isSaving.value = true
    try {
      await companyService.saveMailConfig({
        use_custom_mail_config: 'NO',
      })

      if (mailConfigData.value) {
        mailConfigData.value.use_custom_mail_config = 'NO'
      }

      notificationStore.showNotification({
        type: 'success',
        message: 'settings.mail.company_mail_config_updated',
      })
    } catch (error: unknown) {
      const normalizedError = handleApiError(error)
      notificationStore.showNotification({
        type: 'error',
        message: getErrorTranslationKey(normalizedError.message) ?? normalizedError.message,
      })
      useCustomMailConfig.value = true
    } finally {
      isSaving.value = false
    }
  }
})

async function saveEmailConfig(value: MailConfig): Promise<void> {
  try {
    isSaving.value = true
    await companyService.saveMailConfig({
      ...value,
      use_custom_mail_config: 'YES',
    })

    if (mailConfigData.value) {
      mailConfigData.value = {
        ...mailConfigData.value,
        ...value,
        use_custom_mail_config: 'YES',
      }
    }

    notificationStore.showNotification({
      type: 'success',
      message: 'settings.mail.company_mail_config_updated',
    })
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
  <MailTestModal :store-type="'company'" />

  <BaseSettingCard
    :title="$t('settings.mail.company_mail_config')"
    :description="$t('settings.mail.company_mail_config_desc')"
  >
    <div class="mt-8">
      <BaseSwitchSection
        v-model="useCustomMailConfig"
        :title="$t('settings.mail.use_custom_mail_config')"
        :description="$t('settings.mail.use_custom_mail_config_desc')"
      />
    </div>

    <div v-if="useCustomMailConfig && mailConfigData" class="mt-8">
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

    <div
      v-if="!useCustomMailConfig"
      class="mt-4 p-4 rounded-lg bg-green-500/10 border border-green-500/20 text-sm text-status-green flex items-center"
    >
      <BaseIcon name="CheckCircleIcon" class="w-5 h-5 mr-2 shrink-0" />
      {{ $t('settings.mail.using_global_mail_config') }}
    </div>
  </BaseSettingCard>
</template>
