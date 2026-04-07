<script setup lang="ts">
import { computed, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { usePaymentStore } from '@/scripts/features/company/payments/store'
import NumberCustomizer from './NumberCustomizer.vue'
import PaymentsTabDefaultFormats from './PaymentsTabDefaultFormats.vue'

interface Utils {
  mergeSettings: (target: Record<string, unknown>, source: Record<string, unknown>) => void
}

const utils = inject<Utils>('utils')!
const companyStore = useCompanyStore()
const paymentStore = usePaymentStore()

const paymentSettings = reactive<{ payment_email_attachment: string | null }>({
  payment_email_attachment: null,
})

utils.mergeSettings(
  paymentSettings as unknown as Record<string, unknown>,
  { ...companyStore.selectedCompanySettings }
)

const sendAsAttachmentField = computed<boolean>({
  get: () => paymentSettings.payment_email_attachment === 'YES',
  set: async (newValue: boolean) => {
    const value = newValue ? 'YES' : 'NO'

    const data = {
      settings: {
        payment_email_attachment: value,
      },
    }

    paymentSettings.payment_email_attachment = value

    await companyStore.updateCompanySettings({
      data,
      message: 'general.setting_updated',
    })
  },
})
</script>

<template>
  <NumberCustomizer type="payment" :type-store="paymentStore" />

  <BaseDivider class="mt-6 mb-2" />
  <PaymentsTabDefaultFormats />
  <BaseDivider class="mt-6 mb-2" />

  <ul class="divide-y divide-line-default">
    <BaseSwitchSection
      v-model="sendAsAttachmentField"
      :title="$t('settings.customization.payments.payment_email_attachment')"
      :description="
        $t(
          'settings.customization.payments.payment_email_attachment_setting_description'
        )
      "
    />
  </ul>
</template>
