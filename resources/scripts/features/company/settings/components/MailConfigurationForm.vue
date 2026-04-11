<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { email, helpers, numeric, required } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import type { MailConfig, MailDriver } from '@/scripts/types/mail-config'

interface SelectOption<TValue extends string = string> {
  label: string
  value: TValue
}

const props = withDefaults(
  defineProps<{
    configData?: Partial<MailConfig>
    isSaving?: boolean
    isFetchingInitialData?: boolean
    mailDrivers?: MailDriver[]
    translationScope?: string
    submitLabel?: string
    submitIcon?: string
  }>(),
  {
    configData: () => ({}),
    isSaving: false,
    isFetchingInitialData: false,
    mailDrivers: () => [],
    translationScope: 'settings.mail',
    submitLabel: 'general.save',
    submitIcon: 'ArrowDownOnSquareIcon',
  }
)

const emit = defineEmits<{
  'submit-data': [config: MailConfig]
  'on-change-driver': [driver: MailDriver]
}>()

const { t } = useI18n()

const visibleSecrets = reactive<Record<string, boolean>>({})
const showAdvancedFields = ref(false)

const mailConfig = reactive<MailConfig>(createDefaultMailConfig())

const fallbackDrivers: MailDriver[] = ['sendmail', 'smtp', 'mail']
const encryptionOptions: SelectOption[] = [
  { label: 'None', value: 'none' },
  { label: 'TLS', value: 'tls' },
  { label: 'SSL', value: 'ssl' },
]
const smtpSchemeOptions: SelectOption[] = [
  { label: 'SMTP', value: 'smtp' },
  { label: 'SMTPS', value: 'smtps' },
]
const mailgunSchemeOptions: SelectOption[] = [
  { label: 'HTTPS', value: 'https' },
  { label: 'API', value: 'api' },
]

const availableDrivers = computed<MailDriver[]>(() => {
  return props.mailDrivers.length ? props.mailDrivers : fallbackDrivers
})

const driverOptions = computed<SelectOption<MailDriver>[]>(() => {
  return availableDrivers.value.map((driver) => ({
    label: t(`${props.translationScope}.drivers.${driver}`),
    value: driver,
  }))
})

const currentDriver = computed<MailDriver>({
  get: () => normalizeDriver(mailConfig.mail_driver, availableDrivers.value),
  set: (driver) => {
    mailConfig.mail_driver = driver
  },
})

const hasAdvancedFields = computed<boolean>(() => {
  return getAdvancedFields(currentDriver.value).length > 0
})

const rules = computed(() => {
  const commonRules = {
    mail_driver: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    from_mail: {
      required: helpers.withMessage(t('validation.required'), required),
      email: helpers.withMessage(t('validation.email_incorrect'), email),
    },
    from_name: {
      required: helpers.withMessage(t('validation.required'), required),
    },
  }

  switch (currentDriver.value) {
    case 'smtp':
      return {
        ...commonRules,
        mail_host: {
          required: helpers.withMessage(t('validation.required'), required),
        },
        mail_port: {
          required: helpers.withMessage(t('validation.required'), required),
          numeric: helpers.withMessage(t('validation.numbers_only'), numeric),
        },
        mail_timeout: {
          numeric: helpers.withMessage(t('validation.numbers_only'), (value: string) => {
            if (!value) {
              return true
            }

            return /^\d+$/.test(value)
          }),
        },
      }
    case 'ses':
      return {
        ...commonRules,
        mail_ses_key: {
          required: helpers.withMessage(t('validation.required'), required),
        },
        mail_ses_secret: {
          required: helpers.withMessage(t('validation.required'), required),
        },
        mail_ses_region: {
          required: helpers.withMessage(t('validation.required'), required),
        },
      }
    case 'mailgun':
      return {
        ...commonRules,
        mail_mailgun_domain: {
          required: helpers.withMessage(t('validation.required'), required),
        },
        mail_mailgun_secret: {
          required: helpers.withMessage(t('validation.required'), required),
        },
        mail_mailgun_endpoint: {
          required: helpers.withMessage(t('validation.required'), required),
        },
      }
    case 'postmark':
      return {
        ...commonRules,
        mail_postmark_token: {
          required: helpers.withMessage(t('validation.required'), required),
        },
      }
    default:
      return commonRules
  }
})

const v$ = useVuelidate(rules, mailConfig)

watch(
  () => [props.configData, props.mailDrivers] as const,
  () => {
    syncMailConfig()
  },
  { immediate: true, deep: true }
)

function createDefaultMailConfig(): MailConfig {
  return {
    mail_driver: 'sendmail',
    from_mail: '',
    from_name: '',
    mail_host: '',
    mail_port: '587',
    mail_username: '',
    mail_password: '',
    mail_encryption: 'none',
    mail_scheme: '',
    mail_url: '',
    mail_timeout: '',
    mail_local_domain: '',
    mail_sendmail_path: '/usr/sbin/sendmail -bs -i',
    mail_ses_key: '',
    mail_ses_secret: '',
    mail_ses_region: 'us-east-1',
    mail_mailgun_domain: '',
    mail_mailgun_secret: '',
    mail_mailgun_endpoint: 'api.mailgun.net',
    mail_mailgun_scheme: 'https',
    mail_postmark_token: '',
    mail_postmark_message_stream_id: '',
  }
}

function normalizeDriver(driver: MailConfig['mail_driver'], drivers: MailDriver[]): MailDriver {
  if (driver && drivers.includes(driver as MailDriver)) {
    return driver as MailDriver
  }

  return drivers[0] ?? 'smtp'
}

function syncMailConfig(): void {
  Object.assign(mailConfig, createDefaultMailConfig(), props.configData)
  mailConfig.mail_driver = normalizeDriver(mailConfig.mail_driver, availableDrivers.value)
  showAdvancedFields.value = hasAdvancedValues(currentDriver.value)
  v$.value.$reset()
}

function hasAdvancedValues(driver: MailDriver): boolean {
  const defaultMailConfig = createDefaultMailConfig()

  return getAdvancedFields(driver).some((field) => {
    const value = mailConfig[field]
    const defaultValue = defaultMailConfig[field]

    return value !== '' && value !== null && value !== undefined && value !== defaultValue
  })
}

function getAdvancedFields(driver: MailDriver): Array<keyof MailConfig> {
  switch (driver) {
    case 'smtp':
      return ['mail_scheme', 'mail_url', 'mail_timeout', 'mail_local_domain']
    case 'sendmail':
      return ['mail_sendmail_path']
    case 'mailgun':
      return ['mail_mailgun_scheme']
    case 'postmark':
      return ['mail_postmark_message_stream_id']
    default:
      return []
  }
}

function getFieldError(field: string): string | undefined {
  const validationField = v$.value[field as keyof typeof v$.value]

  if (!validationField || !('$error' in validationField) || !validationField.$error) {
    return undefined
  }

  return validationField.$errors[0]?.$message as string | undefined
}

function toggleSecret(field: string): void {
  visibleSecrets[field] = !visibleSecrets[field]
}

function getSecretInputType(field: string): string {
  return visibleSecrets[field] ? 'text' : 'password'
}

function translationKey(key: string): string {
  return `${props.translationScope}.${key}`
}

function changeDriver(value: MailDriver): void {
  currentDriver.value = value
  showAdvancedFields.value = false
  v$.value.$reset()
  emit('on-change-driver', value)
}

async function saveEmailConfig(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  emit('submit-data', { ...mailConfig, mail_driver: currentDriver.value })
}
</script>

<template>
  <form @submit.prevent="saveEmailConfig">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <BaseInputGroup
        :label="$t(translationKey('driver'))"
        :content-loading="isFetchingInitialData"
        :error="getFieldError('mail_driver')"
        required
      >
        <BaseMultiselect
          v-model="currentDriver"
          :content-loading="isFetchingInitialData"
          :options="driverOptions"
          label="label"
          value-prop="value"
          :can-deselect="false"
          :can-clear="false"
          :invalid="v$.mail_driver.$error"
          @update:model-value="changeDriver"
        />
      </BaseInputGroup>
    </div>

    <div class="mt-8">
      <h3 class="text-sm font-semibold text-heading">
        {{ $t(translationKey('basic_settings')) }}
      </h3>
      <p
        v-if="currentDriver === 'mail' || currentDriver === 'sendmail'"
        class="mt-2 text-sm text-muted"
      >
        {{
          currentDriver === 'mail'
            ? $t(translationKey('native_mail_desc'))
            : $t(translationKey('sendmail_desc'))
        }}
      </p>

      <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        <template v-if="currentDriver === 'smtp'">
          <BaseInputGroup
            :label="$t(translationKey('host'))"
            :content-loading="isFetchingInitialData"
            :error="getFieldError('mail_host')"
            required
          >
            <BaseInput
              v-model.trim="mailConfig.mail_host"
              :content-loading="isFetchingInitialData"
              :invalid="v$.mail_host?.$error"
              type="text"
              @input="v$.mail_host?.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t(translationKey('port'))"
            :content-loading="isFetchingInitialData"
            :error="getFieldError('mail_port')"
            required
          >
            <BaseInput
              v-model.trim="mailConfig.mail_port"
              :content-loading="isFetchingInitialData"
              :invalid="v$.mail_port?.$error"
              type="text"
              @input="v$.mail_port?.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t(translationKey('username'))"
            :content-loading="isFetchingInitialData"
          >
            <BaseInput
              v-model.trim="mailConfig.mail_username"
              :content-loading="isFetchingInitialData"
              type="text"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t(translationKey('password'))"
            :content-loading="isFetchingInitialData"
          >
            <BaseInput
              v-model.trim="mailConfig.mail_password"
              :content-loading="isFetchingInitialData"
              :type="getSecretInputType('mail_password')"
              autocomplete="off"
            >
              <template #right>
                <BaseIcon
                  :name="visibleSecrets.mail_password ? 'EyeIcon' : 'EyeSlashIcon'"
                  class="mr-1 text-muted cursor-pointer"
                  @click="toggleSecret('mail_password')"
                />
              </template>
            </BaseInput>
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t(translationKey('encryption'))"
            :content-loading="isFetchingInitialData"
          >
            <BaseMultiselect
              v-model="mailConfig.mail_encryption"
              :content-loading="isFetchingInitialData"
              :options="encryptionOptions"
              label="label"
              value-prop="value"
              :can-clear="false"
              :can-deselect="false"
            />
          </BaseInputGroup>
        </template>

        <template v-if="currentDriver === 'ses'">
          <BaseInputGroup
            :label="$t(translationKey('ses_key'))"
            :content-loading="isFetchingInitialData"
            :error="getFieldError('mail_ses_key')"
            required
          >
            <BaseInput
              v-model.trim="mailConfig.mail_ses_key"
              :content-loading="isFetchingInitialData"
              :invalid="v$.mail_ses_key?.$error"
              type="text"
              @input="v$.mail_ses_key?.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t(translationKey('ses_secret'))"
            :content-loading="isFetchingInitialData"
            :error="getFieldError('mail_ses_secret')"
            required
          >
            <BaseInput
              v-model.trim="mailConfig.mail_ses_secret"
              :content-loading="isFetchingInitialData"
              :invalid="v$.mail_ses_secret?.$error"
              :type="getSecretInputType('mail_ses_secret')"
              autocomplete="off"
              @input="v$.mail_ses_secret?.$touch()"
            >
              <template #right>
                <BaseIcon
                  :name="visibleSecrets.mail_ses_secret ? 'EyeIcon' : 'EyeSlashIcon'"
                  class="mr-1 text-muted cursor-pointer"
                  @click="toggleSecret('mail_ses_secret')"
                />
              </template>
            </BaseInput>
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t(translationKey('ses_region'))"
            :content-loading="isFetchingInitialData"
            :error="getFieldError('mail_ses_region')"
            required
          >
            <BaseInput
              v-model.trim="mailConfig.mail_ses_region"
              :content-loading="isFetchingInitialData"
              :invalid="v$.mail_ses_region?.$error"
              type="text"
              @input="v$.mail_ses_region?.$touch()"
            />
          </BaseInputGroup>
        </template>

        <template v-if="currentDriver === 'mailgun'">
          <BaseInputGroup
            :label="$t(translationKey('mailgun_domain'))"
            :content-loading="isFetchingInitialData"
            :error="getFieldError('mail_mailgun_domain')"
            required
          >
            <BaseInput
              v-model.trim="mailConfig.mail_mailgun_domain"
              :content-loading="isFetchingInitialData"
              :invalid="v$.mail_mailgun_domain?.$error"
              type="text"
              @input="v$.mail_mailgun_domain?.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t(translationKey('mailgun_secret'))"
            :content-loading="isFetchingInitialData"
            :error="getFieldError('mail_mailgun_secret')"
            required
          >
            <BaseInput
              v-model.trim="mailConfig.mail_mailgun_secret"
              :content-loading="isFetchingInitialData"
              :invalid="v$.mail_mailgun_secret?.$error"
              :type="getSecretInputType('mail_mailgun_secret')"
              autocomplete="off"
              @input="v$.mail_mailgun_secret?.$touch()"
            >
              <template #right>
                <BaseIcon
                  :name="visibleSecrets.mail_mailgun_secret ? 'EyeIcon' : 'EyeSlashIcon'"
                  class="mr-1 text-muted cursor-pointer"
                  @click="toggleSecret('mail_mailgun_secret')"
                />
              </template>
            </BaseInput>
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t(translationKey('mailgun_endpoint'))"
            :content-loading="isFetchingInitialData"
            :error="getFieldError('mail_mailgun_endpoint')"
            required
          >
            <BaseInput
              v-model.trim="mailConfig.mail_mailgun_endpoint"
              :content-loading="isFetchingInitialData"
              :invalid="v$.mail_mailgun_endpoint?.$error"
              type="text"
              @input="v$.mail_mailgun_endpoint?.$touch()"
            />
          </BaseInputGroup>
        </template>

        <template v-if="currentDriver === 'postmark'">
          <BaseInputGroup
            :label="$t(translationKey('postmark_token'))"
            :content-loading="isFetchingInitialData"
            :error="getFieldError('mail_postmark_token')"
            required
          >
            <BaseInput
              v-model.trim="mailConfig.mail_postmark_token"
              :content-loading="isFetchingInitialData"
              :invalid="v$.mail_postmark_token?.$error"
              :type="getSecretInputType('mail_postmark_token')"
              autocomplete="off"
              @input="v$.mail_postmark_token?.$touch()"
            >
              <template #right>
                <BaseIcon
                  :name="visibleSecrets.mail_postmark_token ? 'EyeIcon' : 'EyeSlashIcon'"
                  class="mr-1 text-muted cursor-pointer"
                  @click="toggleSecret('mail_postmark_token')"
                />
              </template>
            </BaseInput>
          </BaseInputGroup>
        </template>

        <BaseInputGroup
          :label="$t(translationKey('from_mail'))"
          :content-loading="isFetchingInitialData"
          :error="getFieldError('from_mail')"
          required
        >
          <BaseInput
            v-model.trim="mailConfig.from_mail"
            :content-loading="isFetchingInitialData"
            :invalid="v$.from_mail.$error"
            type="text"
            @input="v$.from_mail.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t(translationKey('from_name'))"
          :content-loading="isFetchingInitialData"
          :error="getFieldError('from_name')"
          required
        >
          <BaseInput
            v-model.trim="mailConfig.from_name"
            :content-loading="isFetchingInitialData"
            :invalid="v$.from_name.$error"
            type="text"
            @input="v$.from_name.$touch()"
          />
        </BaseInputGroup>
      </div>
    </div>

    <div v-if="hasAdvancedFields" class="mt-8">
      <button
        type="button"
        class="inline-flex items-center gap-2 text-sm font-medium text-primary-600"
        @click="showAdvancedFields = !showAdvancedFields"
      >
        <BaseIcon
          :name="showAdvancedFields ? 'ChevronUpIcon' : 'ChevronDownIcon'"
          class="h-4 w-4"
        />
        {{
          showAdvancedFields
            ? $t(translationKey('hide_advanced_settings'))
            : $t(translationKey('show_advanced_settings'))
        }}
      </button>

      <div v-if="showAdvancedFields" class="mt-4 rounded-lg border border-line-default p-4">
        <h3 class="text-sm font-semibold text-heading">
          {{ $t(translationKey('advanced_settings')) }}
        </h3>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <template v-if="currentDriver === 'smtp'">
            <BaseInputGroup
              :label="$t(translationKey('scheme'))"
              :content-loading="isFetchingInitialData"
            >
              <BaseMultiselect
                v-model="mailConfig.mail_scheme"
                :content-loading="isFetchingInitialData"
                :options="smtpSchemeOptions"
                label="label"
                value-prop="value"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t(translationKey('url'))"
              :content-loading="isFetchingInitialData"
            >
              <BaseInput
                v-model.trim="mailConfig.mail_url"
                :content-loading="isFetchingInitialData"
                type="text"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t(translationKey('timeout'))"
              :content-loading="isFetchingInitialData"
              :error="getFieldError('mail_timeout')"
            >
              <BaseInput
                v-model.trim="mailConfig.mail_timeout"
                :content-loading="isFetchingInitialData"
                :invalid="v$.mail_timeout?.$error"
                type="text"
                @input="v$.mail_timeout?.$touch()"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t(translationKey('local_domain'))"
              :content-loading="isFetchingInitialData"
            >
              <BaseInput
                v-model.trim="mailConfig.mail_local_domain"
                :content-loading="isFetchingInitialData"
                type="text"
              />
            </BaseInputGroup>
          </template>

          <template v-if="currentDriver === 'sendmail'">
            <BaseInputGroup
              :label="$t(translationKey('sendmail_path'))"
              :content-loading="isFetchingInitialData"
            >
              <BaseInput
                v-model.trim="mailConfig.mail_sendmail_path"
                :content-loading="isFetchingInitialData"
                type="text"
              />
            </BaseInputGroup>
          </template>

          <template v-if="currentDriver === 'mailgun'">
            <BaseInputGroup
              :label="$t(translationKey('mailgun_scheme'))"
              :content-loading="isFetchingInitialData"
            >
              <BaseMultiselect
                v-model="mailConfig.mail_mailgun_scheme"
                :content-loading="isFetchingInitialData"
                :options="mailgunSchemeOptions"
                label="label"
                value-prop="value"
                :can-clear="false"
                :can-deselect="false"
              />
            </BaseInputGroup>
          </template>

          <template v-if="currentDriver === 'postmark'">
            <BaseInputGroup
              :label="$t(translationKey('postmark_message_stream_id'))"
              :content-loading="isFetchingInitialData"
            >
              <BaseInput
                v-model.trim="mailConfig.mail_postmark_message_stream_id"
                :content-loading="isFetchingInitialData"
                type="text"
              />
            </BaseInputGroup>
          </template>
        </div>
      </div>
    </div>

    <div class="mt-8 flex">
      <BaseButton
        :disabled="isSaving"
        :content-loading="isFetchingInitialData"
        :loading="isSaving"
        variant="primary"
        type="submit"
      >
        <template #left="slotProps">
          <BaseIcon v-if="!isSaving" :name="submitIcon" :class="slotProps.class" />
        </template>
        {{ $t(submitLabel) }}
      </BaseButton>
      <slot />
    </div>
  </form>
</template>
