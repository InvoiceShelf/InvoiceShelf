<script setup lang="ts">
import { computed, reactive, inject } from 'vue'
import { useCompanyStore } from '@v2/stores/company.store'
import NumberCustomizer from './NumberCustomizer.vue'

interface Utils {
  mergeSettings: (target: Record<string, unknown>, source: Record<string, unknown>) => void
}

const utils = inject<Utils>('utils')!
const companyStore = useCompanyStore()

const invoiceSettings = reactive<{ invoice_email_attachment: string | null }>({
  invoice_email_attachment: null,
})

utils.mergeSettings(
  invoiceSettings as unknown as Record<string, unknown>,
  { ...companyStore.selectedCompanySettings }
)

const sendAsAttachmentField = computed<boolean>({
  get: () => invoiceSettings.invoice_email_attachment === 'YES',
  set: async (newValue: boolean) => {
    const value = newValue ? 'YES' : 'NO'

    const data = {
      settings: {
        invoice_email_attachment: value,
      },
    }

    invoiceSettings.invoice_email_attachment = value

    await companyStore.updateCompanySettings({
      data,
      message: 'general.setting_updated',
    })
  },
})
</script>

<template>
  <NumberCustomizer type="invoice" :type-store="companyStore" />

  <BaseDivider class="mt-6 mb-2" />

  <ul class="divide-y divide-line-default">
    <BaseSwitchSection
      v-model="sendAsAttachmentField"
      :title="$t('settings.customization.invoices.invoice_email_attachment')"
      :description="
        $t(
          'settings.customization.invoices.invoice_email_attachment_setting_description'
        )
      "
    />
  </ul>
</template>
