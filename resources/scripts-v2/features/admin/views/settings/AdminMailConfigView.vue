<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@v2/stores/modal.store'
import { useNotificationStore } from '@v2/stores/notification.store'
import { mailService } from '@v2/api/services/mail.service'
import type { MailConfig, MailDriver } from '@v2/api/services/mail.service'
import SmtpMailDriver from '@v2/features/company/settings/components/SmtpMailDriver.vue'
import MailgunMailDriver from '@v2/features/company/settings/components/MailgunMailDriver.vue'
import SesMailDriver from '@v2/features/company/settings/components/SesMailDriver.vue'
import BasicMailDriver from '@v2/features/company/settings/components/BasicMailDriver.vue'
import MailTestModal from '@v2/features/company/settings/components/MailTestModal.vue'

const { t } = useI18n()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()

const isSaving = ref(false)
const isFetchingInitialData = ref(false)
const mailConfigData = ref<Record<string, unknown> | null>(null)
const mailDrivers = ref<MailDriver[]>([])
const currentMailDriver = ref('smtp')

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
    currentMailDriver.value = configResponse.mail_driver ?? 'smtp'
  } finally {
    isFetchingInitialData.value = false
  }
}

const mailDriver = computed(() => {
  switch (currentMailDriver.value) {
    case 'mailgun':
      return MailgunMailDriver
    case 'ses':
      return SesMailDriver
    case 'sendmail':
    case 'mail':
      return BasicMailDriver
    default:
      return SmtpMailDriver
  }
})

function changeDriver(value: string): void {
  currentMailDriver.value = value

  if (mailConfigData.value) {
    mailConfigData.value.mail_driver = value
  }
}

async function saveEmailConfig(value: MailConfig): Promise<void> {
  isSaving.value = true

  try {
    const response = await mailService.saveConfig(value)

    if (response.success) {
      notificationStore.showNotification({
        type: 'success',
        message: t(`settings.success.${response.success}`),
      })

      if (mailConfigData.value) {
        mailConfigData.value = {
          ...mailConfigData.value,
          ...value,
        }
      }
    }
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
      <component
        :is="mailDriver"
        :config-data="mailConfigData"
        :is-saving="isSaving"
        :mail-drivers="mailDrivers"
        :is-fetching-initial-data="isFetchingInitialData"
        @on-change-driver="changeDriver"
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
      </component>
    </div>
  </BaseSettingCard>
</template>
