<script setup lang="ts">
import { computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import type { PdfDriver } from '@/scripts/api/services/pdf.service'

interface DomPdfForm {
  pdf_driver: string
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
  'submit-data': [config: DomPdfForm]
  'on-change-driver': [driver: string]
}>()

const { t } = useI18n()

const form = reactive<DomPdfForm>({
  pdf_driver: 'dompdf',
})

const rules = computed(() => ({
  pdf_driver: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, form)

onMounted(() => {
  if (typeof props.configData.pdf_driver === 'string') {
    form.pdf_driver = props.configData.pdf_driver
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
