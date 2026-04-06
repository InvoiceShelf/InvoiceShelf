<script setup lang="ts">
import { computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import type { PdfDriver } from '@v2/api/services/pdf.service'

interface GotenbergForm {
  pdf_driver: string
  gotenberg_host: string
  gotenberg_papersize: string
}

const props = withDefaults(
  defineProps<{
    configData?: Record<string, unknown>
    isSaving?: boolean
    isFetchingInitialData?: boolean
    drivers?: PdfDriver[]
  }>(),
  {
    configData: () => ({}),
    isSaving: false,
    isFetchingInitialData: false,
    drivers: () => [],
  }
)

const emit = defineEmits<{
  'submit-data': [config: GotenbergForm]
  'on-change-driver': [driver: string]
}>()

const { t } = useI18n()

const form = reactive<GotenbergForm>({
  pdf_driver: 'gotenberg',
  gotenberg_host: '',
  gotenberg_papersize: '210mm 297mm',
})

function isValidServiceUrl(value: string): boolean {
  if (!helpers.req(value)) {
    return true
  }

  try {
    const parsedUrl = new URL(value)

    return (
      (parsedUrl.protocol === 'http:' || parsedUrl.protocol === 'https:') &&
      parsedUrl.hostname.length > 0
    )
  } catch {
    return false
  }
}

const rules = computed(() => ({
  pdf_driver: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  gotenberg_host: {
    required: helpers.withMessage(t('validation.required'), required),
    validServiceUrl: helpers.withMessage(
      t('validation.invalid_url'),
      isValidServiceUrl
    ),
  },
  gotenberg_papersize: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, form)

onMounted(() => {
  if (typeof props.configData.pdf_driver === 'string') {
    form.pdf_driver = props.configData.pdf_driver
  }

  if (typeof props.configData.gotenberg_host === 'string') {
    form.gotenberg_host = props.configData.gotenberg_host
  }

  if (typeof props.configData.gotenberg_papersize === 'string') {
    form.gotenberg_papersize = props.configData.gotenberg_papersize
  }
})

function onChangeDriver(): void {
  v$.value.pdf_driver.$touch()
  emit('on-change-driver', form.pdf_driver)
}

function saveConfig(): void {
  v$.value.$touch()
  if (v$.value.$invalid) {
    return
  }

  emit('submit-data', { ...form })
}
</script>

<template>
  <form @submit.prevent="saveConfig">
    <BaseInputGrid>
      <BaseInputGroup
        :label="$t('settings.pdf.driver')"
        :error="v$.pdf_driver.$error && v$.pdf_driver.$errors[0]?.$message"
        required
      >
        <BaseMultiselect
          v-model="form.pdf_driver"
          :content-loading="isFetchingInitialData"
          :options="drivers"
          :can-deselect="false"
          :invalid="v$.pdf_driver.$error"
          @update:model-value="onChangeDriver"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.pdf.gotenberg_host')"
        :error="
          v$.gotenberg_host.$error && v$.gotenberg_host.$errors[0]?.$message
        "
        required
      >
        <BaseInput
          v-model.trim="form.gotenberg_host"
          :content-loading="isFetchingInitialData"
          :invalid="v$.gotenberg_host.$error"
          type="text"
          name="gotenberg_host"
          @input="v$.gotenberg_host.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.pdf.papersize')"
        :help-text="$t('settings.pdf.papersize_hint')"
        :error="
          v$.gotenberg_papersize.$error &&
          v$.gotenberg_papersize.$errors[0]?.$message
        "
        required
      >
        <BaseInput
          v-model.trim="form.gotenberg_papersize"
          :content-loading="isFetchingInitialData"
          :invalid="v$.gotenberg_papersize.$error"
          type="text"
          name="gotenberg_papersize"
          @input="v$.gotenberg_papersize.$touch()"
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
    </div>
  </form>
</template>
