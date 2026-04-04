<script setup lang="ts">
import { onMounted, ref, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, email, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'

interface MailgunConfig {
  mail_driver: string
  mail_mailgun_domain: string
  mail_mailgun_secret: string
  mail_mailgun_endpoint: string
  from_mail: string
  from_name: string
}

const props = withDefaults(
  defineProps<{
    configData?: Record<string, unknown>
    isSaving?: boolean
    isFetchingInitialData?: boolean
    mailDrivers?: string[]
  }>(),
  {
    configData: () => ({}),
    isSaving: false,
    isFetchingInitialData: false,
    mailDrivers: () => [],
  }
)

const emit = defineEmits<{
  'submit-data': [config: MailgunConfig]
  'on-change-driver': [driver: string]
}>()

const { t } = useI18n()

const isShowPassword = ref<boolean>(false)

const mailgunConfig = reactive<MailgunConfig>({
  mail_driver: 'mailgun',
  mail_mailgun_domain: '',
  mail_mailgun_secret: '',
  mail_mailgun_endpoint: '',
  from_mail: '',
  from_name: '',
})

const getInputType = computed<string>(() =>
  isShowPassword.value ? 'text' : 'password'
)

const rules = computed(() => ({
  mail_driver: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  mail_mailgun_domain: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  mail_mailgun_endpoint: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  mail_mailgun_secret: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  from_mail: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  from_name: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, mailgunConfig)

onMounted(() => {
  for (const key in mailgunConfig) {
    if (Object.prototype.hasOwnProperty.call(props.configData, key)) {
      ;(mailgunConfig as Record<string, unknown>)[key] = props.configData[key]
    }
  }
})

async function saveEmailConfig(): Promise<void> {
  v$.value.$touch()
  if (!v$.value.$invalid) {
    emit('submit-data', { ...mailgunConfig })
  }
}

function onChangeDriver(): void {
  v$.value.mail_driver.$touch()
  emit('on-change-driver', mailgunConfig.mail_driver)
}
</script>

<template>
  <form @submit.prevent="saveEmailConfig">
    <BaseInputGrid>
      <BaseInputGroup
        :label="$t('settings.mail.driver')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.mail_driver.$error && v$.mail_driver.$errors[0].$message
        "
        required
      >
        <BaseMultiselect
          v-model="mailgunConfig.mail_driver"
          :content-loading="isFetchingInitialData"
          :options="mailDrivers"
          :can-deselect="false"
          :invalid="v$.mail_driver.$error"
          @update:model-value="onChangeDriver"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.mail.mailgun_domain')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.mail_mailgun_domain.$error &&
          v$.mail_mailgun_domain.$errors[0].$message
        "
        required
      >
        <BaseInput
          v-model.trim="mailgunConfig.mail_mailgun_domain"
          :content-loading="isFetchingInitialData"
          type="text"
          name="mailgun_domain"
          :invalid="v$.mail_mailgun_domain.$error"
          @input="v$.mail_mailgun_domain.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.mail.mailgun_secret')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.mail_mailgun_secret.$error &&
          v$.mail_mailgun_secret.$errors[0].$message
        "
        required
      >
        <BaseInput
          v-model.trim="mailgunConfig.mail_mailgun_secret"
          :content-loading="isFetchingInitialData"
          :type="getInputType"
          name="mailgun_secret"
          autocomplete="off"
          :invalid="v$.mail_mailgun_secret.$error"
          @input="v$.mail_mailgun_secret.$touch()"
        >
          <template #right>
            <BaseIcon
              :name="isShowPassword ? 'EyeIcon' : 'EyeSlashIcon'"
              class="mr-1 text-muted cursor-pointer"
              @click="isShowPassword = !isShowPassword"
            />
          </template>
        </BaseInput>
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.mail.mailgun_endpoint')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.mail_mailgun_endpoint.$error &&
          v$.mail_mailgun_endpoint.$errors[0].$message
        "
        required
      >
        <BaseInput
          v-model.trim="mailgunConfig.mail_mailgun_endpoint"
          :content-loading="isFetchingInitialData"
          type="text"
          name="mailgun_endpoint"
          :invalid="v$.mail_mailgun_endpoint.$error"
          @input="v$.mail_mailgun_endpoint.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.mail.from_mail')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.from_mail.$error && v$.from_mail.$errors[0].$message
        "
        required
      >
        <BaseInput
          v-model.trim="mailgunConfig.from_mail"
          :content-loading="isFetchingInitialData"
          type="text"
          name="from_mail"
          :invalid="v$.from_mail.$error"
          @input="v$.from_mail.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.mail.from_name')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.from_name.$error && v$.from_name.$errors[0].$message
        "
        required
      >
        <BaseInput
          v-model.trim="mailgunConfig.from_name"
          :content-loading="isFetchingInitialData"
          type="text"
          name="from_name"
          :invalid="v$.from_name.$error"
          @input="v$.from_name.$touch()"
        />
      </BaseInputGroup>
    </BaseInputGrid>
    <div class="flex my-10">
      <BaseButton
        :disabled="isSaving"
        :content-loading="isFetchingInitialData"
        :loading="isSaving"
        variant="primary"
        type="submit"
      >
        <template #left="slotProps">
          <BaseIcon
            v-if="!isSaving"
            name="ArrowDownOnSquareIcon"
            :class="slotProps.class"
          />
        </template>
        {{ $t('general.save') }}
      </BaseButton>
      <slot />
    </div>
  </form>
</template>
