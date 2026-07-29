<script setup lang="ts">
import { computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import type { DomPdfConfig, PdfDriver } from '@/scripts/api/services/pdf.service'
import AdminPdfPageSetup from '@/scripts/features/admin/components/settings/AdminPdfPageSetup.vue'
import {
  cssLength,
  pageSetupDefaults,
  pageSetupErrors,
  pageSetupFrom,
} from '@/scripts/features/admin/components/settings/pdfPageSetup'

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
  'submit-data': [config: DomPdfConfig]
  'on-change-driver': [driver: string]
}>()

const { t } = useI18n()

const form = reactive<DomPdfConfig>({
  pdf_driver: 'dompdf',
  ...pageSetupDefaults(),
})

const rules = computed(() => ({
  pdf_driver: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  pdf_paper_width: cssLength(t),
  pdf_paper_height: cssLength(t),
  pdf_margin_top: cssLength(t),
  pdf_margin_right: cssLength(t),
  pdf_margin_bottom: cssLength(t),
  pdf_margin_left: cssLength(t),
}))

const v$ = useVuelidate(rules, form)

const pageErrors = computed(() => pageSetupErrors(v$.value))

onMounted(() => {
  if (typeof props.configData.pdf_driver === 'string') {
    form.pdf_driver = props.configData.pdf_driver
  }

  Object.assign(form, pageSetupFrom(props.configData))
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

    <AdminPdfPageSetup
      v-model="form"
      class="mt-6"
      :is-fetching-initial-data="isFetchingInitialData"
      :errors="pageErrors"
    />

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
