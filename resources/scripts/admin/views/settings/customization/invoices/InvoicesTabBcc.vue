<template>
  <form @submit.prevent="submitForm">
    <h6 class="text-gray-900 text-lg font-medium">
      {{ $t('settings.customization.invoices.bcc_settings') }}
    </h6>
    <p class="mt-1 text-sm text-gray-500 mb-2">
      {{ $t('settings.customization.invoices.bcc_settings_description') }}
    </p>

    <BaseInputGroup
      :label="$t('settings.customization.invoices.invoice_email_bcc')"
      class="mt-4"
    >
      <BaseInput
        v-model="bccSettings.invoice_email_bcc"
        type="email"
        :placeholder="$t('settings.customization.invoices.invoice_email_bcc_placeholder')"
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

<script setup>
import { reactive, inject, ref } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const utils = inject('utils')
const companyStore = useCompanyStore()
const isSaving = ref(false)

const bccSettings = reactive({
  invoice_email_bcc: null,
})

utils.mergeSettings(bccSettings, {
  ...companyStore.selectedCompanySettings,
})

async function submitForm() {
  isSaving.value = true

  let data = {
    settings: {
      invoice_email_bcc: bccSettings.invoice_email_bcc,
    },
  }

  await companyStore.updateCompanySettings({
    data,
    message: 'general.setting_updated',
  })

  isSaving.value = false
}
</script> 