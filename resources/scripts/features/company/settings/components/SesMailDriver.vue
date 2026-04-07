<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, email, numeric, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'

interface SesConfig {
  mail_driver: string
  mail_host: string
  mail_port: string
  mail_encryption: string
  mail_ses_key: string
  mail_ses_secret: string
  mail_ses_region: string
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
  'submit-data': [config: SesConfig]
  'on-change-driver': [driver: string]
}>()

const { t } = useI18n()

const isShowPassword = ref<boolean>(false)
const encryptions = reactive<string[]>(['tls', 'ssl', 'starttls'])

const sesConfig = reactive<SesConfig>({
  mail_driver: 'ses',
  mail_host: '',
  mail_port: '',
  mail_encryption: '',
  mail_ses_key: '',
  mail_ses_secret: '',
  mail_ses_region: '',
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
  mail_host: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  mail_port: {
    required: helpers.withMessage(t('validation.required'), required),
    numeric: helpers.withMessage(t('validation.numbers_only'), numeric),
  },
  mail_ses_key: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  mail_ses_secret: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  mail_ses_region: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  mail_encryption: {
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

const v$ = useVuelidate(rules, sesConfig)

onMounted(() => {
  for (const key in sesConfig) {
    if (Object.prototype.hasOwnProperty.call(props.configData, key)) {
      ;(sesConfig as Record<string, unknown>)[key] = props.configData[key]
    }
  }
})

async function saveEmailConfig(): Promise<void> {
  v$.value.$touch()
  if (!v$.value.$invalid) {
    emit('submit-data', { ...sesConfig })
  }
}

function onChangeDriver(): void {
  v$.value.mail_driver.$touch()
  emit('on-change-driver', sesConfig.mail_driver)
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
          v-model="sesConfig.mail_driver"
          :content-loading="isFetchingInitialData"
          :options="mailDrivers"
          :can-deselect="false"
          :invalid="v$.mail_driver.$error"
          @update:model-value="onChangeDriver"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.mail.host')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.mail_host.$error && v$.mail_host.$errors[0].$message
        "
        required
      >
        <BaseInput
          v-model.trim="sesConfig.mail_host"
          :content-loading="isFetchingInitialData"
          type="text"
          name="mail_host"
          :invalid="v$.mail_host.$error"
          @input="v$.mail_host.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.mail.port')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.mail_port.$error && v$.mail_port.$errors[0].$message
        "
        required
      >
        <BaseInput
          v-model.trim="sesConfig.mail_port"
          :content-loading="isFetchingInitialData"
          type="text"
          name="mail_port"
          :invalid="v$.mail_port.$error"
          @input="v$.mail_port.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.mail.encryption')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.mail_encryption.$error &&
          v$.mail_encryption.$errors[0].$message
        "
        required
      >
        <BaseMultiselect
          v-model.trim="sesConfig.mail_encryption"
          :content-loading="isFetchingInitialData"
          :options="encryptions"
          :invalid="v$.mail_encryption.$error"
          placeholder="Select option"
          @input="v$.mail_encryption.$touch()"
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
          v-model.trim="sesConfig.from_mail"
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
          v-model.trim="sesConfig.from_name"
          :content-loading="isFetchingInitialData"
          type="text"
          name="name"
          :invalid="v$.from_name.$error"
          @input="v$.from_name.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.mail.ses_key')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.mail_ses_key.$error && v$.mail_ses_key.$errors[0].$message
        "
        required
      >
        <BaseInput
          v-model.trim="sesConfig.mail_ses_key"
          :content-loading="isFetchingInitialData"
          type="text"
          name="mail_ses_key"
          :invalid="v$.mail_ses_key.$error"
          @input="v$.mail_ses_key.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.mail.ses_secret')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.mail_ses_secret.$error &&
          v$.mail_ses_secret.$errors[0].$message
        "
        required
      >
        <BaseInput
          v-model.trim="sesConfig.mail_ses_secret"
          :content-loading="isFetchingInitialData"
          :type="getInputType"
          name="mail_ses_secret"
          autocomplete="off"
          :invalid="v$.mail_ses_secret.$error"
          @input="v$.mail_ses_secret.$touch()"
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
        :label="$t('settings.mail.ses_region')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.mail_ses_region.$error &&
          v$.mail_ses_region.$errors[0].$message
        "
        required
      >
        <BaseInput
          v-model.trim="sesConfig.mail_ses_region"
          :content-loading="isFetchingInitialData"
          type="text"
          name="mail_ses_region"
          :invalid="v$.mail_ses_region.$error"
          @input="v$.mail_ses_region.$touch()"
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
