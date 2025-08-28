<template>
    <BaseSettingCard
      :title="$t('settings.pdf.pdf_configuration')"
      :description="$t('settings.pdf.section_description')"
    >
    <div v-if="pdfDriverStore && pdfDriverStore.pdfDriverConfig" class="mt-14">
      <component
        :is="pdfDriver"
        :config-data="pdfDriverStore.pdfDriverConfig"
        :is-saving="isSaving"
        :drivers="pdfDriverStore.pdf_drivers"
        :is-fetching-initial-data="isFetchingInitialData"
        @on-change-driver="(val) => changeDriver(val)"
        @submit-data="saveConfig"
      >
      </component>
    </div>
    </BaseSettingCard>
</template>

<script setup>
  import GotenbergDriver from '@/scripts/admin/views/settings/pdf-driver/GotenbergDriver.vue';
  import DomPDFDriver from '@/scripts/admin/views/settings/pdf-driver/DomPDFDriver.vue';
  import { ref, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { usePDFDriverStore } from '@/scripts/admin/stores/pdf-driver'
  const emit = defineEmits(['submit-data', 'on-change-driver'])

  let isFetchingInitialData = ref(false)
  let isSaving = ref(false)

  const pdfDriverStore = usePDFDriverStore();
  const { t } = useI18n();

  function changeDriver(value) {
    pdfDriverStore.pdf_driver = value
    pdfDriverStore.pdfDriverConfig.pdf_driver = value
  }

  async function loadData() {
    isFetchingInitialData.value = true
    await Promise.all([
      pdfDriverStore.fetchDrivers(),
      pdfDriverStore.fetchConfig(),
    ])
    isFetchingInitialData.value = false
  }
  loadData();

  async function saveConfig(value) {
    try {
      isSaving.value = true
      await pdfDriverStore.updateConfig(value)
    } catch (e) {
      console.error(e)
    } finally {
      isSaving.value = false
      return false
    }
  }

  const pdfDriver = computed(() => {
    switch (pdfDriverStore.pdf_driver) {
      case 'dompdf':
        return DomPDFDriver
      case 'gotenberg':
        return GotenbergDriver
      default:
        return DomPDFDriver
    }
  })
</script>
