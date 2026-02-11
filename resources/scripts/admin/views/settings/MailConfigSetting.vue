<template>
  <MailTestModal />

  <BaseSettingCard
    :title="$t('settings.mail.mail_config')"
    :description="$t('settings.mail.mail_config_desc')"
  >
    <div class="mb-4" v-if="companyStore.selectedCompany">
      <label class="flex items-center">
        <input type="checkbox" v-model="useCompanySettingsField" class="form-checkbox" />
        <span class="ml-2">{{ $t('settings.mail.use_company_settings') }}</span>
        <BaseIcon
          name="InformationCircleIcon"
          class="w-4 h-4 ml-2 text-gray-400"
          :title="$t('settings.mail.use_company_settings_help')"
        />
      </label>
      <p class="mt-2 text-sm text-gray-500">
        {{ useCompanySettingsField ? $t('settings.mail.active_mode_company') : $t('settings.mail.active_mode_global') }}
      </p>
    </div>
    <div v-if="mailDriverStore && mailDriverStore.mailConfigData" class="mt-14">
      <component
        :is="mailDriver"
        :config-data="mailDriverStore.mailConfigData"
        :is-saving="isSaving"
        :mail-drivers="mailDriverStore.mail_drivers"
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
  </BaseSettingCard>
</template>

<script setup>
import Smtp from '@/scripts/admin/views/settings/mail-driver/SmtpMailDriver.vue'
import Mailgun from '@/scripts/admin/views/settings/mail-driver/MailgunMailDriver.vue'
import Ses from '@/scripts/admin/views/settings/mail-driver/SesMailDriver.vue'
import Basic from '@/scripts/admin/views/settings/mail-driver/BasicMailDriver.vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { ref, computed, watch, onMounted } from 'vue'
import { useMailDriverStore } from '@/scripts/admin/stores/mail-driver'
import { useModalStore } from '@/scripts/stores/modal'
import MailTestModal from '@/scripts/admin/components/modal-components/MailTestModal.vue'
import { useI18n } from 'vue-i18n'

let isSaving = ref(false)
let isFetchingInitialData = ref(false)
const mailDriverStore = useMailDriverStore()
const companyStore = useCompanyStore()
const modalStore = useModalStore()
const { t } = useI18n()

onMounted(async () => {
  await loadCompanyToggle()
  await loadData()
})

watch(
  () => companyStore.selectedCompany?.id,
  async () => {
    await loadCompanyToggle()
    await loadData()
  },
  { flush: 'post' }
)

function changeDriver(value) {
  mailDriverStore.mail_driver = value
  mailDriverStore.mailConfigData.mail_driver = value
}

async function loadData() {
  isFetchingInitialData.value = true
  await mailDriverStore.fetchMailDrivers()
  if (mailDriverStore.use_company_settings) {
    await mailDriverStore.fetchCompanyMailConfig()
  } else {
    await mailDriverStore.fetchMailConfig()
  }
  isFetchingInitialData.value = false
}

async function loadCompanyToggle() {
  if (!companyStore.selectedCompany) {
    mailDriverStore.use_company_settings = false
    return
  }

  const response = await companyStore.fetchCompanySettings([
    'use_company_mail_settings',
  ])

  const settingValue = response.data.use_company_mail_settings
  mailDriverStore.use_company_settings = settingValue === 'YES'
}

const useCompanySettingsField = computed({
  get: () => {
    return mailDriverStore.use_company_settings
  },
  set: async (newValue) => {
    const value = newValue ? 'YES' : 'NO'

    mailDriverStore.use_company_settings = newValue

    await companyStore.updateCompanySettings({
      data: {
        settings: {
          use_company_mail_settings: value,
        },
      },
      message: 'general.setting_updated',
    })

    await loadData()
  },
})

const mailDriver = computed(() => {
  if (mailDriverStore.mail_driver == 'smtp') return Smtp
  if (mailDriverStore.mail_driver == 'mailgun') return Mailgun
  if (mailDriverStore.mail_driver == 'sendmail') return Basic
  if (mailDriverStore.mail_driver == 'ses') return Ses
  if (mailDriverStore.mail_driver == 'mail') return Basic
  return Smtp
})

async function saveEmailConfig(value) {
  try {
    isSaving.value = true
    if (mailDriverStore.use_company_settings) {
      await mailDriverStore.updateCompanyMailConfig(value)
    } else {
      await mailDriverStore.updateMailConfig(value)
    }
    isSaving.value = false
    return true
  } catch (e) {
    console.error(e)
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
