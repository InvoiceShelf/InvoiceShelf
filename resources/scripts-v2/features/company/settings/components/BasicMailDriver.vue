<script setup lang="ts">
import { onMounted, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, email, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'

interface BasicMailConfig {
  mail_driver: string
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
  'submit-data': [config: BasicMailConfig]
  'on-change-driver': [driver: string]
}>()

const { t } = useI18n()

const basicMailConfig = reactive<BasicMailConfig>({
  mail_driver: 'sendmail',
  from_mail: '',
  from_name: '',
})

const rules = computed(() => ({
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
}))

const v$ = useVuelidate(rules, basicMailConfig)

onMounted(() => {
  for (const key in basicMailConfig) {
    if (Object.prototype.hasOwnProperty.call(props.configData, key)) {
      ;(basicMailConfig as Record<string, unknown>)[key] = props.configData[key]
    }
  }
})

async function saveEmailConfig(): Promise<void> {
  v$.value.$touch()
  if (!v$.value.$invalid) {
    emit('submit-data', { ...basicMailConfig })
  }
}

function onChangeDriver(): void {
  v$.value.mail_driver.$touch()
  emit('on-change-driver', basicMailConfig.mail_driver)
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
          v-model="basicMailConfig.mail_driver"
          :content-loading="isFetchingInitialData"
          :options="mailDrivers"
          :can-deselect="false"
          :invalid="v$.mail_driver.$error"
          @update:model-value="onChangeDriver"
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
          v-model.trim="basicMailConfig.from_mail"
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
          v-model.trim="basicMailConfig.from_name"
          :content-loading="isFetchingInitialData"
          type="text"
          name="name"
          :invalid="v$.from_name.$error"
          @input="v$.from_name.$touch()"
        />
      </BaseInputGroup>
    </BaseInputGrid>
    <div class="flex mt-8">
      <BaseButton
        :content-loading="isFetchingInitialData"
        :disabled="isSaving"
        :loading="isSaving"
        variant="primary"
        type="submit"
      >
        <template #left="slotProps">
          <BaseIcon
            v-if="!isSaving"
            :class="slotProps.class"
            name="ArrowDownOnSquareIcon"
          />
        </template>
        {{ $t('general.save') }}
      </BaseButton>
      <slot />
    </div>
  </form>
</template>
