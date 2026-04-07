<script setup lang="ts">
import { ref, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'

interface Utils {
  mergeSettings: (target: Record<string, unknown>, source: Record<string, unknown>) => void
}

const companyStore = useCompanyStore()
const utils = inject<Utils>('utils')!

const estimateMailFields = ref(['customer', 'company', 'estimate'])
const companyFields = ref(['company'])
const shippingFields = ref(['shipping', 'customer'])
const billingFields = ref(['billing', 'customer'])

const isSaving = ref(false)

const formatSettings = reactive<{
  estimate_mail_body: string | null
  estimate_company_address_format: string | null
  estimate_shipping_address_format: string | null
  estimate_billing_address_format: string | null
}>({
  estimate_mail_body: null,
  estimate_company_address_format: null,
  estimate_shipping_address_format: null,
  estimate_billing_address_format: null,
})

utils.mergeSettings(
  formatSettings as unknown as Record<string, unknown>,
  { ...companyStore.selectedCompanySettings }
)

async function submitForm() {
  isSaving.value = true

  const data = {
    settings: {
      ...formatSettings,
    },
  }

  await companyStore.updateCompanySettings({
    data,
    message: 'settings.customization.estimates.estimate_settings_updated',
  })

  isSaving.value = false

  return true
}
</script>

<template>
  <form @submit.prevent="submitForm">
    <h6 class="text-heading text-lg font-medium">
      {{ $t('settings.customization.estimates.default_formats') }}
    </h6>
    <p class="mt-1 text-sm text-muted mb-2">
      {{ $t('settings.customization.estimates.default_formats_description') }}
    </p>

    <BaseInputGroup
      :label="
        $t('settings.customization.estimates.default_estimate_email_body')
      "
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="formatSettings.estimate_mail_body"
        :fields="estimateMailFields"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('settings.customization.estimates.company_address_format')"
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="formatSettings.estimate_company_address_format"
        :fields="companyFields"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('settings.customization.estimates.shipping_address_format')"
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="formatSettings.estimate_shipping_address_format"
        :fields="shippingFields"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('settings.customization.estimates.billing_address_format')"
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="formatSettings.estimate_billing_address_format"
        :fields="billingFields"
      />
    </BaseInputGroup>

    <BaseButton
      :loading="isSaving"
      :disabled="isSaving"
      variant="primary"
      type="submit"
      class="mt-4"
    >
      <template #left="slotProps">
        <BaseIcon v-if="!isSaving" :class="slotProps.class" name="ArrowDownOnSquareIcon" />
      </template>
      {{ $t('settings.customization.save') }}
    </BaseButton>
  </form>
</template>
