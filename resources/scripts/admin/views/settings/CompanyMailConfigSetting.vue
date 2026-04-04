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

    <div v-if="useCustomMailConfig && companyMailStore.mailConfigData" class="mt-8">
      <component
        :is="mailDriver"
        :config-data="companyMailStore.mailConfigData"
        :is-saving="isSaving"
        :mail-drivers="companyMailStore.mail_drivers"
        :is-fetching-initial-data="isFetchingInitialData"
        @on-change-driver="(val) => changeDriver(val)"
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

    <div v-if="!useCustomMailConfig" class="mt-4 p-4 rounded-lg bg-green-500/10 border border-green-500/20 text-sm text-status-green flex items-center">
      <BaseIcon name="CheckCircleIcon" class="w-5 h-5 mr-2 shrink-0" />
      {{ $t('settings.mail.using_global_mail_config') }}
    </div>
  </BaseSettingCard>
</template>

<script setup>
import Smtp from '@/scripts/admin/views/settings/mail-driver/SmtpMailDriver.vue'
import Mailgun from '@/scripts/admin/views/settings/mail-driver/MailgunMailDriver.vue'
import Ses from '@/scripts/admin/views/settings/mail-driver/SesMailDriver.vue'
import Basic from '@/scripts/admin/views/settings/mail-driver/BasicMailDriver.vue'
import { ref, computed, watch } from 'vue'
import { useCompanyMailStore } from '@/scripts/admin/stores/company-mail'
import { useModalStore } from '@/scripts/stores/modal'
import MailTestModal from '@/scripts/admin/components/modal-components/MailTestModal.vue'
import { useI18n } from 'vue-i18n'

let isSaving = ref(false)
let isFetchingInitialData = ref(false)

const companyMailStore = useCompanyMailStore()
const modalStore = useModalStore()
const { t } = useI18n()

const useCustomMailConfig = ref(false)

loadData()

async function loadData() {
  isFetchingInitialData.value = true
  await companyMailStore.fetchMailDrivers()
  await companyMailStore.fetchMailConfig()
  useCustomMailConfig.value =
    companyMailStore.mailConfigData?.use_custom_mail_config === 'YES'
  isFetchingInitialData.value = false
}

function changeDriver(value) {
  companyMailStore.mail_driver = value
  companyMailStore.mailConfigData.mail_driver = value
}

const mailDriver = computed(() => {
  if (companyMailStore.mail_driver === 'smtp') return Smtp
  if (companyMailStore.mail_driver === 'mailgun') return Mailgun
  if (companyMailStore.mail_driver === 'sendmail') return Basic
  if (companyMailStore.mail_driver === 'ses') return Ses
  if (companyMailStore.mail_driver === 'mail') return Basic
  return Smtp
})

watch(useCustomMailConfig, async (newVal, oldVal) => {
  if (oldVal === undefined) return

  if (!newVal) {
    isSaving.value = true
    await companyMailStore.updateMailConfig({
      use_custom_mail_config: 'NO',
      mail_driver: '',
    })
    isSaving.value = false
  }
})

async function saveEmailConfig(value) {
  try {
    isSaving.value = true
    await companyMailStore.updateMailConfig({
      ...value,
      use_custom_mail_config: 'YES',
    })
    isSaving.value = false
  } catch (e) {
    isSaving.value = false
  }
}

function openMailTestModal() {
  modalStore.openModal({
    title: t('general.test_mail_conf'),
    componentName: 'MailTestModal',
    size: 'sm',
  })
}
</script>
