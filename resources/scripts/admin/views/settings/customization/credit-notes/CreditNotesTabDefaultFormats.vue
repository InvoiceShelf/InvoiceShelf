<template>
  <form @submit.prevent="submitForm">
    <h6 class="text-gray-900 text-lg font-medium">
      {{ $t('settings.customization.credit_notes.default_formats') }}
    </h6>
    <p class="mt-1 text-sm text-gray-500 mb-2">
      {{ $t('settings.customization.credit_notes.default_formats_description') }}
    </p>

    <BaseInputGroup
      :label="$t('settings.customization.credit_notes.default_credit_note_email_body')"
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="formatSettings.credit_note_mail_body"
        :fields="mailFields"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('settings.customization.credit_notes.company_address_format')"
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="formatSettings.credit_note_company_address_format"
        :fields="companyFields"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="
        $t('settings.customization.credit_notes.from_customer_address_format')
      "
      class="mt-6 mb-4"
    >
      <BaseCustomInput
        v-model="formatSettings.credit_note_from_customer_address_format"
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

<script setup>
import { ref, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const companyStore = useCompanyStore()
const utils = inject('utils')

const mailFields = ref([
  'customer',
  'customerCustom',
  'company',
  'creditNote',
  'creditNoteCustom',
])

const customerAddressFields = ref([
  'billing',
  'customer',
  'customerCustom',
  'creditNoteCustom',
])

const companyFields = ref(['company', 'creditNoteCustom'])

let isSaving = ref(false)

const formatSettings = reactive({
  credit_note_mail_body: null,
  credit_note_company_address_format: null,
  credit_note_from_customer_address_format: null,
})

utils.mergeSettings(formatSettings, {
  ...companyStore.selectedCompanySettings,
})

async function submitForm() {
  isSaving.value = true

  let data = {
    settings: {
      ...formatSettings,
    },
  }

  await companyStore.updateCompanySettings({
    data,
    message: 'settings.customization.credit_notes.credit_note_settings_updated',
  })

  isSaving.value = false

  return true
}
</script>
