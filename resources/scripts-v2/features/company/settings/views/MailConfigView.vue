<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../../stores/modal.store'
import { companyService } from '../../../../api/services/company.service'
import { mailService } from '../../../../api/services/mail.service'
import type { MailDriver } from '../../../../api/services/mail.service'
import Smtp from '@/scripts/admin/views/settings/mail-driver/SmtpMailDriver.vue'
import Mailgun from '@/scripts/admin/views/settings/mail-driver/MailgunMailDriver.vue'
import Ses from '@/scripts/admin/views/settings/mail-driver/SesMailDriver.vue'
import Basic from '@/scripts/admin/views/settings/mail-driver/BasicMailDriver.vue'
import MailTestModal from '@/scripts/admin/components/modal-components/MailTestModal.vue'

const { t } = useI18n()
const modalStore = useModalStore()

const isSaving = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(false)
const useCustomMailConfig = ref<boolean>(false)

const mailConfigData = ref<Record<string, unknown> | null>(null)
const mailDrivers = ref<MailDriver[]>([])
const currentMailDriver = ref<string>('smtp')

loadData()

async function loadData(): Promise<void> {
  isFetchingInitialData.value = true
  const [driversResponse, configResponse] = await Promise.all([
    mailService.getDrivers(),
    companyService.getMailConfig(),
  ])
  mailDrivers.value = driversResponse
  mailConfigData.value = configResponse
  currentMailDriver.value = (configResponse.mail_driver as string) ?? 'smtp'
  useCustomMailConfig.value =
    (configResponse.use_custom_mail_config as string) === 'YES'
  isFetchingInitialData.value = false
}

function changeDriver(value: string): void {
  currentMailDriver.value = value
  if (mailConfigData.value) {
    mailConfigData.value.mail_driver = value
  }
}

const mailDriver = computed(() => {
  if (currentMailDriver.value === 'smtp') return Smtp
  if (currentMailDriver.value === 'mailgun') return Mailgun
  if (currentMailDriver.value === 'sendmail') return Basic
  if (currentMailDriver.value === 'ses') return Ses
  if (currentMailDriver.value === 'mail') return Basic
  return Smtp
})

watch(useCustomMailConfig, async (newVal, oldVal) => {
  if (oldVal === undefined) return

  if (!newVal) {
    isSaving.value = true
    await companyService.saveMailConfig({
      use_custom_mail_config: 'NO',
      mail_driver: '',
    })
    isSaving.value = false
  }
})

async function saveEmailConfig(value: Record<string, unknown>): Promise<void> {
  try {
    isSaving.value = true
    await companyService.saveMailConfig({
      ...value,
      use_custom_mail_config: 'YES',
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
      <component
        :is="mailDriver"
        :config-data="mailConfigData"
        :is-saving="isSaving"
        :mail-drivers="mailDrivers"
        :is-fetching-initial-data="isFetchingInitialData"
        @on-change-driver="(val: string) => changeDriver(val)"
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

    <div
      v-if="!useCustomMailConfig"
      class="mt-4 p-4 rounded-lg bg-green-500/10 border border-green-500/20 text-sm text-status-green flex items-center"
    >
      <BaseIcon name="CheckCircleIcon" class="w-5 h-5 mr-2 shrink-0" />
      {{ $t('settings.mail.using_global_mail_config') }}
    </div>
  </BaseSettingCard>
</template>
