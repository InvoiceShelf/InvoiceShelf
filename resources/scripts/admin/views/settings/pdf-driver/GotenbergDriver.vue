<template>
  <form @submit.prevent="saveConfig">
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
      <BaseInputGroup
        :label="$t('settings.pdf_generation.gotenberg_host')"
        :content-loading="isFetchingInitialData"
        required
      >
        <BaseInput
          v-model="pdfDriverStore.gotenberg.gotenberg_host"
          :content-loading="isFetchingInitialData"
          type="text"
          name="gotenberg_host"
        />
      </BaseInputGroup>
      <BaseInputGroup
        :label="$t('settings.pdf_generation.papersize')"
        :content-loading="isFetchingInitialData"
        required
      >
        <BaseInput
          v-model="pdfDriverStore.gotenberg.gotenberg_papersize"
          :content-loading="isFetchingInitialData"
          type="text"
          name="gotenberg_papersize"
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
  const { t } = useI18n();

  function onChangeDriver() {
    // validation
    //v$.value.smtpConfig.mail_driver.$touch()
    emit('on-change-driver', pdfDriverStore.pdf_driver)
  }

  async function saveConfig() {
  console.log("save")
    emit('submit-data', pdfDriverStore.gotenberg)
    return false
  }

  // Fill pdfDriverStore.gotenbergConfig with data from config prop
  onMounted(() => {
    for (const key in pdfDriverStore.gotenberg) {
      if (props.configData.hasOwnProperty(key)) {
        pdfDriverStore.gotenberg[key] = props.configData[key]
      }
    }
  })

</script>
