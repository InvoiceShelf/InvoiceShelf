<script setup lang="ts">
import { reactive, onMounted, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, email, numeric, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'

interface SmtpConfig {
  mail_driver: string
  mail_host: string
  mail_port: string
  mail_username: string
  mail_password: string
  mail_encryption: string
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
  'submit-data': [config: SmtpConfig]
  'on-change-driver': [driver: string]
}>()

const { t } = useI18n()

const isShowPassword = ref<boolean>(false)
const schemes = reactive<string[]>(['smtp', 'smtps', 'none'])

const smtpConfig = reactive<SmtpConfig>({
  mail_driver: 'smtp',
  mail_host: '',
  mail_port: '',
  mail_username: '',
  mail_password: '',
  mail_encryption: '',
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
  from_mail: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  from_name: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, smtpConfig)

onMounted(() => {
  for (const key in smtpConfig) {
    if (Object.prototype.hasOwnProperty.call(props.configData, key)) {
      ;(smtpConfig as Record<string, unknown>)[key] = props.configData[key]
    }
  }
})

async function saveEmailConfig(): Promise<void> {
  v$.value.$touch()
  if (!v$.value.$invalid) {
    emit('submit-data', { ...smtpConfig })
  }
}

function onChangeDriver(): void {
  v$.value.mail_driver.$touch()
  emit('on-change-driver', smtpConfig.mail_driver)
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
          v-model="smtpConfig.mail_driver"
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
          v-model.trim="smtpConfig.mail_host"
          :content-loading="isFetchingInitialData"
          type="text"
          name="mail_host"
          :invalid="v$.mail_host.$error"
          @input="v$.mail_host.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :content-loading="isFetchingInitialData"
        :label="$t('settings.mail.username')"
      >
        <BaseInput
          v-model.trim="smtpConfig.mail_username"
          :content-loading="isFetchingInitialData"
          type="text"
          name="db_name"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :content-loading="isFetchingInitialData"
        :label="$t('settings.mail.password')"
      >
        <BaseInput
          v-model.trim="smtpConfig.mail_password"
          :content-loading="isFetchingInitialData"
          :type="getInputType"
          name="password"
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
        :label="$t('settings.mail.port')"
        :content-loading="isFetchingInitialData"
        :error="
          v$.mail_port.$error && v$.mail_port.$errors[0].$message
        "
        required
      >
        <BaseInput
          v-model.trim="smtpConfig.mail_port"
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
      >
        <BaseMultiselect
          v-model.trim="smtpConfig.mail_encryption"
          :content-loading="isFetchingInitialData"
          :options="schemes"
          :searchable="true"
          :show-labels="false"
          placeholder="Select option"
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
          v-model.trim="smtpConfig.from_mail"
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
          v-model.trim="smtpConfig.from_name"
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
        type="submit"
        variant="primary"
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
