<template>
  <form @submit.prevent="saveConfig">
    <BaseInputGrid>
      <BaseInputGroup :label="$t('settings.pdf.driver')" :error="
          v$.gotenberg.pdf_driver.$error &&
          v$.gotenberg.pdf_driver.$errors[0].$message
        " required>
        <BaseMultiselect v-model="pdfDriverStore.gotenberg.pdf_driver" :content-loading="isFetchingInitialData"
          :options="drivers" :can-deselect="false" @update:modelValue="onChangeDriver"
          :invalid="v$.gotenberg.pdf_driver.$error" />
      </BaseInputGroup>
      <BaseInputGroup :label="$t('settings.pdf.gotenberg_host')" :content-loading="isFetchingInitialData"
        :error="
          v$.gotenberg.gotenberg_host.$error &&
          v$.gotenberg.gotenberg_host.$errors[0].$message
        " required>
        <BaseInput v-model="pdfDriverStore.gotenberg.gotenberg_host" :content-loading="isFetchingInitialData"
          :invalid="v$.gotenberg.gotenberg_host.$error" type="text" name="gotenberg_host" />
      </BaseInputGroup>
      <BaseInputGroup :label="$t('settings.pdf.papersize')" :content-loading="isFetchingInitialData" :error="
          v$.gotenberg.gotenberg_papersize.$error &&
          v$.gotenberg.gotenberg_papersize.$errors[0].$message
        "
          :help-text="$t('settings.pdf.papersize_hint')"
        required>
        <BaseInput v-model="pdfDriverStore.gotenberg.gotenberg_papersize" :content-loading="isFetchingInitialData"
          :invalid="v$.gotenberg.gotenberg_papersize.$error" type="text" name="gotenberg_papersize" />
      </BaseInputGroup>
    </BaseInputGrid>
    <div class="flex my-10">
      <BaseButton :disabled="isSaving" :content-loading="isFetchingInitialData" :loading="isSaving" type="submit"
        variant="primary">
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
  import {ref, computed, onMounted} from 'vue'
  import {useI18n} from 'vue-i18n'
  import {usePDFDriverStore} from '@/scripts/admin/stores/pdf-driver'
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

  const pdfDriverStore = usePDFDriverStore();
  const {t} = useI18n();

  const rules = computed(() => {
    return {
      gotenberg: {
        pdf_driver: {
          required: helpers.withMessage(t('validation.required'), required),
        },
        gotenberg_host: {
          required: helpers.withMessage(t('validation.required'), required),
        },
        gotenberg_papersize: {
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
    v$.value.gotenberg.pdf_driver.$touch()
    emit('on-change-driver', pdfDriverStore.gotenberg.pdf_driver)
  }

  async function saveConfig() {
    v$.value.gotenberg.$touch()
    if (!v$.value.gotenberg.$invalid) {
      emit('submit-data', pdfDriverStore.gotenberg)
    }
    return false
  }

  // Fill pdfDriverStore.gotenbergConfig with data from config prop
  onMounted(() => {
    for (const key in pdfDriverStore.gotenberg) {
      if (props.configData.hasOwnProperty(key)) {
        pdfDriverStore.$patch((state) => {
          state.gotenberg[key] = props.configData[key]
        });
      }
    }
    if (pdfDriverStore.gotenberg.gotenberg_papersize == '')
      pdfDriverStore.$patch((state) => {
        state.gotenberg.gotenberg_papersize = '210mm 297mm';
      });
  })

</script>
