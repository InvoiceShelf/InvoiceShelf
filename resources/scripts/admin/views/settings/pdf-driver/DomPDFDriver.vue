<template>
    <BaseInputGrid>
      <BaseInputGroup
        :label="$t('settings.pdf_generation.driver')"
        required
      >
        <BaseMultiselect
          v-model="pdfDriverStore.pdf_driver"
          :content-loading="isFetchingInitialData"
          :options="drivers"
          :can-deselect="false"
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
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { usePDFDriverStore } from '@/scripts/admin/stores/pdf-driver'

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

  function onChangeDriver() {
    // validation
    //v$.value.smtpConfig.mail_driver.$touch()
    emit('on-change-driver', pdfDriverStore.pdf_driver)
  }

  onMounted(() => {
  })

</script>
