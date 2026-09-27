<script setup lang="ts">
import { useProposalContext, useProposalSettings } from '@/scripts/features/company/estimates/use-proposal-context'
import { reactive, inject } from 'vue'
import { useGlobalStore } from '@/scripts/stores/global.store'

const { t, kind } = useProposalContext()
const { settings, updateSettings } = useProposalSettings()


interface Utils {
  mergeSettings: (target: Record<string, unknown>, source: Record<string, unknown>) => void
}

const globalStore = useGlobalStore()
const utils = inject<Utils>('utils')!

const settingsForm = reactive<{ estimate_convert_action: string | null }>({
  estimate_convert_action: null,
})

utils.mergeSettings(
  settingsForm as unknown as Record<string, unknown>,
  { ...settings }
)

const convertEstimateOptions = [
  { key: 'settings.customization.estimates.no_action', value: 'no_action' },
  { key: 'settings.customization.estimates.delete_estimate', value: `delete_${kind}` },
  { key: 'settings.customization.estimates.mark_estimate_as_accepted', value: `mark_${kind}_as_accepted` },
]

async function submitForm() {
  const data = {
    settings: {
      ...settingsForm,
    },
  }

  await updateSettings({
    data,
    message: 'settings.customization.estimates.estimate_settings_updated',
  })

  return true
}
</script>

<template>
  <h3 :id="`${kind}-convert-heading`" class="text-heading text-lg font-medium">
    {{ t('settings.customization.estimates.convert_estimate_options') }}
  </h3>
  <p class="mt-1 text-sm text-muted">
    {{ t('settings.customization.estimates.convert_estimate_description') }}
  </p>

  <div role="radiogroup" :aria-labelledby="`${kind}-convert-heading`" class="flex flex-col mt-1.5">
    <BaseRadio
      v-for="option in convertEstimateOptions"
      :id="option.value"
      :key="option.value"
      v-model="settingsForm.estimate_convert_action"
      :label="t(option.key)"
      size="sm"
      :name="`${kind}_convert_action`"
      :value="option.value"
      class="mt-2"
      @update:model-value="submitForm"
    />
  </div>
</template>
