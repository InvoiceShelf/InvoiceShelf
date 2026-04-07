<script setup lang="ts">
import { reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useGlobalStore } from '@/scripts/stores/global.store'

interface Utils {
  mergeSettings: (target: Record<string, unknown>, source: Record<string, unknown>) => void
}

const companyStore = useCompanyStore()
const globalStore = useGlobalStore()
const utils = inject<Utils>('utils')!

const settingsForm = reactive<{ estimate_convert_action: string | null }>({
  estimate_convert_action: null,
})

utils.mergeSettings(
  settingsForm as unknown as Record<string, unknown>,
  { ...companyStore.selectedCompanySettings }
)

const convertEstimateOptions = [
  { key: 'settings.customization.estimates.no_action', value: 'no_action' },
  { key: 'settings.customization.estimates.delete_estimate', value: 'delete_estimate' },
  { key: 'settings.customization.estimates.mark_estimate_as_accepted', value: 'mark_estimate_as_accepted' },
]

async function submitForm() {
  const data = {
    settings: {
      ...settingsForm,
    },
  }

  await companyStore.updateCompanySettings({
    data,
    message: 'settings.customization.estimates.estimate_settings_updated',
  })

  return true
}
</script>

<template>
  <h6 class="text-heading text-lg font-medium">
    {{ $t('settings.customization.estimates.convert_estimate_setting') }}
  </h6>
  <p class="mt-1 text-sm text-muted">
    {{ $t('settings.customization.estimates.convert_estimate_description') }}
  </p>

  <BaseInputGroup required>
    <BaseRadio
      v-for="option in convertEstimateOptions"
      :id="option.value"
      :key="option.value"
      v-model="settingsForm.estimate_convert_action"
      :label="$t(option.key)"
      size="sm"
      name="estimate_convert_action"
      :value="option.value"
      class="mt-2"
      @update:modelValue="submitForm"
    />
  </BaseInputGroup>
</template>
