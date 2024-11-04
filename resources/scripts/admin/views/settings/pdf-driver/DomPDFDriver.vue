<template>
  <form @submit.prevent="saveConfig">
    <BaseInputGrid>
      <BaseInputGroup
        :label="$t('settings.pdf.driver')"
        :error="
          v$.dompdf.pdf_driver.$error &&
          v$.dompdf.pdf_driver.$errors[0].$message
        "
        required
      >
        <BaseMultiselect
          v-model="pdfDriverStore.dompdf.pdf_driver"
          :content-loading="isFetchingInitialData"
          :options="drivers"
          :can-deselect="false"
          :invalid="v$.dompdf.pdf_driver.$error"
          @update:modelValue="onChangeDriver"
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
          <BaseIcon v-if="!isSaving" name="SaveIcon" :class="slotProps.class" />
        </template>
        {{ $t('general.save') }}
      </BaseButton>
      <slot />
    </div>
  </form>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { usePDFDriverStore } from '@/scripts/admin/stores/pdf-driver'
  import {required, email, helpers} from '@vuelidate/validators'
  import useVuelidate from '@vuelidate/core'

  const props = defineProps({
    configData: {
      type: Object,
      require: true,
      default: Object,
    },
    isSaving: {
      type: Boolean,
      require: true,
      default: false,
    },
    isFetchingInitialData: {
      type: Boolean,
      require: true,
      default: false,
    },
    drivers: {
      type: Array,
      require: true,
      default: Array,
    },
  })


  const emit = defineEmits(['submit-data', 'on-change-driver'])

  let isFetchingInitialData = ref(false)

  const pdfDriverStore = usePDFDriverStore();
  const { t } = useI18n();

  const rules = computed(() => {
    return {
      dompdf: {
        pdf_driver: {
          required: helpers.withMessage(t('validation.required'), required),
        },
      },
    }
  })

  const v$ = useVuelidate(
    rules,
    computed(() => pdfDriverStore)
  )

  function onChangeDriver() {
    // validation
    v$.value.dompdf.pdf_driver.$touch()
    emit('on-change-driver', pdfDriverStore.dompdf.pdf_driver)
  }

  async function saveConfig() {
    v$.value.dompdf.$touch()
    if (!v$.value.dompdf.$invalid) {
      emit('submit-data', pdfDriverStore.dompdf)
    }
    return false
  }

  onMounted(() => {
    for (const key in pdfDriverStore.dompdf) {
      if (props.configData.hasOwnProperty(key)) {
        pdfDriverStore.$patch((state) => {
          state.dompdf[key] = props.configData[key]
        });
      }
    }
  })

</script>
