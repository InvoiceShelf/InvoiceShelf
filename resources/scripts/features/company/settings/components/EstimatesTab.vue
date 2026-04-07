<script setup lang="ts">
import { computed, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useEstimateStore } from '@/scripts/features/company/estimates/store'
import NumberCustomizer from './NumberCustomizer.vue'
import EstimatesTabExpiryDate from './EstimatesTabExpiryDate.vue'
import EstimatesTabConvertEstimate from './EstimatesTabConvertEstimate.vue'
import EstimatesTabDefaultFormats from './EstimatesTabDefaultFormats.vue'

interface Utils {
  mergeSettings: (target: Record<string, unknown>, source: Record<string, unknown>) => void
}

const utils = inject<Utils>('utils')!
const companyStore = useCompanyStore()
const estimateStore = useEstimateStore()

const estimateSettings = reactive<{ estimate_email_attachment: string | null }>({
  estimate_email_attachment: null,
})

utils.mergeSettings(
  estimateSettings as unknown as Record<string, unknown>,
  { ...companyStore.selectedCompanySettings }
)

const sendAsAttachmentField = computed<boolean>({
  get: () => estimateSettings.estimate_email_attachment === 'YES',
  set: async (newValue: boolean) => {
    const value = newValue ? 'YES' : 'NO'

    const data = {
      settings: {
        estimate_email_attachment: value,
      },
    }

    estimateSettings.estimate_email_attachment = value

    await companyStore.updateCompanySettings({
      data,
      message: 'general.setting_updated',
    })
  },
})
</script>

<template>
  <NumberCustomizer type="estimate" :type-store="estimateStore" />

  <BaseDivider class="mt-6 mb-2" />
  <EstimatesTabExpiryDate />
  <BaseDivider class="mt-6 mb-2" />
  <EstimatesTabConvertEstimate />
  <BaseDivider class="mt-6 mb-2" />
  <EstimatesTabDefaultFormats />
  <BaseDivider class="mt-6 mb-2" />

  <ul class="divide-y divide-line-default">
    <BaseSwitchSection
      v-model="sendAsAttachmentField"
      :title="$t('settings.customization.estimates.estimate_email_attachment')"
      :description="
        $t(
          'settings.customization.estimates.estimate_email_attachment_setting_description'
        )
      "
    />
  </ul>
</template>
