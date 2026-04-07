<script setup lang="ts">
import { ref, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'

interface Utils {
  mergeSettings: (target: Record<string, unknown>, source: Record<string, unknown>) => void
}

const companyStore = useCompanyStore()
const utils = inject<Utils>('utils')!

const mailFields = ref(['customer', 'company', 'payment'])
const companyFields = ref(['company'])
const customerAddressFields = ref(['billing', 'customer'])

const isSaving = ref(false)

const formatSettings = reactive<{
  payment_mail_body: string | null
  payment_company_address_format: string | null
  payment_from_customer_address_format: string | null
}>({
  payment_mail_body: null,
  payment_company_address_format: null,
  payment_from_customer_address_format: null,
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
    message: 'settings.customization.payments.payment_settings_updated',
  })

  isSaving.value = false

  return true
}
</script>

<template>
  <form @submit.prevent="submitForm">
    <h6 class="text-heading text-lg font-medium">
      {{ $t('settings.customization.payments.default_formats') }}
    </h6>
    <p class="mt-1 text-sm text-muted mb-2">
      {{ $t('settings.customization.payments.default_formats_description') }}
    </p>

    <BaseInputGroup
      :label="$t('settings.customization.payments.default_payment_email_body')"
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="formatSettings.payment_mail_body"
        :fields="mailFields"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('settings.customization.payments.company_address_format')"
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="formatSettings.payment_company_address_format"
        :fields="companyFields"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="
        $t('settings.customization.payments.from_customer_address_format')
      "
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="formatSettings.payment_from_customer_address_format"
        :fields="customerAddressFields"
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
