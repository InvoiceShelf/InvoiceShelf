<template>
  <BaseWizardStep
    :title="$t('wizard.mail.mail_config')"
    :description="$t('wizard.mail.mail_config_desc')"
  >
    <form @submit.prevent="next">
      <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
        <BaseInputGroup :label="$t('wizard.mail.driver')" required>
          <BaseMultiselect
            v-model="mailDriver"
            :options="mailDriverOptions"
            label="label"
            value-prop="value"
            :can-deselect="false"
            :can-clear="false"
            @update:model-value="onChangeDriver"
          />
        </BaseInputGroup>
      </div>

      <!-- SMTP Fields -->
      <template v-if="mailDriver === 'smtp'">
        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
          <BaseInputGroup :label="$t('wizard.mail.host')" required>
            <BaseInput v-model="mailConfig.mail_host" type="text" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('wizard.mail.port')" required>
            <BaseInput v-model="mailConfig.mail_port" type="text" />
          </BaseInputGroup>
        </div>

        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
          <BaseInputGroup :label="$t('wizard.mail.username')">
            <BaseInput v-model="mailConfig.mail_username" type="text" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('wizard.mail.password')">
            <BaseInput v-model="mailConfig.mail_password" type="password" />
          </BaseInputGroup>
        </div>

        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
          <BaseInputGroup :label="$t('wizard.mail.encryption')">
            <BaseMultiselect
              v-model="mailConfig.mail_encryption"
              :options="encryptionOptions"
              :can-deselect="true"
              :placeholder="$t('wizard.mail.none')"
            />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('wizard.mail.from_mail')">
            <BaseInput v-model="mailConfig.from_mail" type="text" />
          </BaseInputGroup>
        </div>

        <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2">
          <BaseInputGroup :label="$t('wizard.mail.from_name')">
            <BaseInput v-model="mailConfig.from_name" type="text" />
          </BaseInputGroup>
        </div>
      </template>

      <!-- Basic driver info -->
      <template v-if="mailDriver === 'sendmail' || mailDriver === 'mail'">
        <p class="text-sm text-muted mb-6">
          {{ $t('wizard.mail.basic_mail_desc') }}
        </p>
      </template>

      <BaseButton :loading="isSaving" :disabled="isSaving" class="mt-4">
        <template #left="slotProps">
          <BaseIcon name="ArrowRightIcon" :class="slotProps.class" />
        </template>
        {{ $t('wizard.save_cont') }}
      </BaseButton>
    </form>
  </BaseWizardStep>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { client } from '../../../api/client'

interface MailConfig {
  mail_driver: string
  mail_host: string
  mail_port: string
  mail_username: string
  mail_password: string
  mail_encryption: string
  from_mail: string
  from_name: string
  [key: string]: string
}

interface DriverOption {
  label: string
  value: string
}

interface Emits {
  (e: 'next', step: number): void
}

const emit = defineEmits<Emits>()

const isSaving = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(false)
const mailDriver = ref<string>('smtp')

const mailDriverOptions = ref<DriverOption[]>([
  { label: 'SMTP', value: 'smtp' },
  { label: 'Mailgun', value: 'mailgun' },
  { label: 'SES', value: 'ses' },
  { label: 'Sendmail', value: 'sendmail' },
  { label: 'Mail', value: 'mail' },
])

const encryptionOptions = ref<string[]>(['tls', 'ssl'])

const mailConfig = reactive<MailConfig>({
  mail_driver: 'smtp',
  mail_host: '',
  mail_port: '587',
  mail_username: '',
  mail_password: '',
  mail_encryption: 'tls',
  from_mail: '',
  from_name: '',
})

onMounted(async () => {
  await loadData()
})

async function loadData(): Promise<void> {
  isFetchingInitialData.value = true
  try {
    const { data: configData } = await client.get('/api/v1/mail/config')
    if (configData) {
      Object.assign(mailConfig, configData)
      mailDriver.value = configData.mail_driver ?? 'smtp'
    }
  } finally {
    isFetchingInitialData.value = false
  }
}

function onChangeDriver(value: string): void {
  mailDriver.value = value
  mailConfig.mail_driver = value
}

async function next(): Promise<void> {
  isSaving.value = true
  try {
    mailConfig.mail_driver = mailDriver.value
    const { data } = await client.post('/api/v1/mail/config', mailConfig)
    if (data.success) {
      emit('next', 5)
    }
  } finally {
    isSaving.value = false
  }
}
</script>
