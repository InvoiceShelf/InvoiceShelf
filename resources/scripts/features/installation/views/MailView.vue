<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { installClient } from '@/scripts/api/install-client'
import type { MailConfig, MailDriver } from '@/scripts/types/mail-config'
import MailConfigurationForm from '@/scripts/features/company/settings/components/MailConfigurationForm.vue'
import { useInstallationFeedback } from '../use-installation-feedback'

const router = useRouter()
const { isSuccessfulResponse, showRequestError, showResponseError } = useInstallationFeedback()

const isSaving = ref(false)
const isFetchingInitialData = ref(false)
const mailConfigData = ref<MailConfig | null>(null)
const mailDrivers = ref<MailDriver[]>([])

onMounted(async () => {
  await loadData()
})

async function loadData(): Promise<void> {
  isFetchingInitialData.value = true

  try {
    const [{ data: driversData }, { data: configData }] = await Promise.all([
      installClient.get<MailDriver[]>('/api/v1/mail/drivers'),
      installClient.get<MailConfig>('/api/v1/mail/config'),
    ])

    mailDrivers.value = driversData
    mailConfigData.value = configData
  } catch (error: unknown) {
    showRequestError(error)
  } finally {
    isFetchingInitialData.value = false
  }
}

async function saveMailConfig(value: MailConfig): Promise<void> {
  isSaving.value = true

  try {
    const { data } = await installClient.post('/api/v1/mail/config', value)

    if (!isSuccessfulResponse(data)) {
      showResponseError(data)
      return
    }

    mailConfigData.value = {
      ...value,
    }

    await router.push({ name: 'installation.account' })
  } catch (error: unknown) {
    showRequestError(error)
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseWizardStep
    :title="$t('wizard.mail.mail_config')"
    :description="$t('wizard.mail.mail_config_desc')"
  >
    <MailConfigurationForm
      v-if="mailConfigData"
      :config-data="mailConfigData"
      :is-saving="isSaving"
      :mail-drivers="mailDrivers"
      :is-fetching-initial-data="isFetchingInitialData"
      translation-scope="wizard.mail"
      submit-label="wizard.save_cont"
      submit-icon="ArrowRightIcon"
      @submit-data="saveMailConfig"
    />
  </BaseWizardStep>
</template>
