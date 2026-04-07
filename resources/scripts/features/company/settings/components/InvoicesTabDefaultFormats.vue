<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'

const companyStore = useCompanyStore()

const isSaving = ref<boolean>(false)

const settingsForm = reactive<{
  invoice_mail_body: string
  invoice_company_address_format: string
  invoice_shipping_address_format: string
  invoice_billing_address_format: string
}>({
  invoice_mail_body:
    companyStore.selectedCompanySettings.invoice_mail_body ?? '',
  invoice_company_address_format:
    companyStore.selectedCompanySettings.invoice_company_address_format ?? '',
  invoice_shipping_address_format:
    companyStore.selectedCompanySettings.invoice_shipping_address_format ?? '',
  invoice_billing_address_format:
    companyStore.selectedCompanySettings.invoice_billing_address_format ?? '',
})

async function submitForm(): Promise<void> {
  isSaving.value = true

  const data = {
    settings: {
      ...settingsForm,
    },
  }

  await companyStore.updateCompanySettings({
    data,
    message: 'settings.customization.invoices.invoice_settings_updated',
  })

  isSaving.value = false
}
</script>

<template>
  <form @submit.prevent="submitForm">
    <BaseSettingCard
      :title="$t('settings.customization.invoices.default_formats')"
      :description="
        $t('settings.customization.invoices.default_formats_description')
      "
    >
      <BaseInputGroup
        :label="
          $t('settings.customization.invoices.default_invoice_email_body')
        "
        class="mt-6 mb-4"
      >
        <BaseCustomInput
          v-model="settingsForm.invoice_mail_body"
          :fields="['customer', 'company', 'invoice']"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="
          $t('settings.customization.invoices.company_address_format')
        "
        class="mt-6 mb-4"
      >
        <BaseCustomInput
          v-model="settingsForm.invoice_company_address_format"
          :fields="['company']"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="
          $t('settings.customization.invoices.shipping_address_format')
        "
        class="mt-6 mb-4"
      >
        <BaseCustomInput
          v-model="settingsForm.invoice_shipping_address_format"
          :fields="['shipping', 'customer']"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="
          $t('settings.customization.invoices.billing_address_format')
        "
        class="mt-6 mb-4"
      >
        <BaseCustomInput
          v-model="settingsForm.invoice_billing_address_format"
          :fields="['billing', 'customer']"
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
          <BaseIcon
            v-if="!isSaving"
            :class="slotProps.class"
            name="ArrowDownOnSquareIcon"
          />
        </template>
        {{ $t('settings.customization.save') }}
      </BaseButton>
    </BaseSettingCard>
  </form>
</template>
