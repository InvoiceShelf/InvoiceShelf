<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { pdfService } from '@/scripts/api/services/pdf.service'
import type { PdfConfig, PdfDriver } from '@/scripts/api/services/pdf.service'
import AdminPdfDomDriver from '@/scripts/features/admin/components/settings/AdminPdfDomDriver.vue'
import AdminPdfGotenbergDriver from '@/scripts/features/admin/components/settings/AdminPdfGotenbergDriver.vue'

const { t } = useI18n()
const notificationStore = useNotificationStore()

const isSaving = ref(false)
const isFetchingInitialData = ref(false)
const configData = ref<Record<string, unknown> | null>(null)
const drivers = ref<PdfDriver[]>([])
const currentDriver = ref('dompdf')

loadData()

async function loadData(): Promise<void> {
  isFetchingInitialData.value = true

  try {
    const [driversResponse, configResponse] = await Promise.all([
      pdfService.getDrivers(),
      pdfService.getConfig(),
    ])

    drivers.value = driversResponse
    configData.value = configResponse
    currentDriver.value = configResponse.pdf_driver ?? 'dompdf'
  } finally {
    isFetchingInitialData.value = false
  }
}

const pdfDriver = computed(() => {
  if (currentDriver.value === 'gotenberg') {
    return AdminPdfGotenbergDriver
  }

  return AdminPdfDomDriver
})

function changeDriver(value: string): void {
  currentDriver.value = value

  if (configData.value) {
    configData.value.pdf_driver = value
  }
}

async function saveConfig(value: PdfConfig): Promise<void> {
  isSaving.value = true

  try {
    const response = await pdfService.saveConfig(value)

    if (response.success) {
      notificationStore.showNotification({
        type: 'success',
        message: t(`settings.pdf.${response.success}`),
      })

      if (configData.value) {
        configData.value = {
          ...configData.value,
          ...value,
        }
      }
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseSettingCard
    :title="$t('settings.pdf.pdf_configuration')"
    :description="$t('settings.pdf.section_description')"
  >
    <div v-if="configData" class="mt-14">
      <component
        :is="pdfDriver"
        :config-data="configData"
        :is-saving="isSaving"
        :drivers="drivers"
        :is-fetching-initial-data="isFetchingInitialData"
        @on-change-driver="changeDriver"
        @submit-data="saveConfig"
      />
    </div>
  </BaseSettingCard>
</template>
