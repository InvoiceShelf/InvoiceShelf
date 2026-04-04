<template>
  <CreditNotesTabCreditNoteNumber />

  <BaseDivider class="my-8" />

  <CreditNotesTabDefaultFormats />

  <BaseDivider class="mt-6 mb-2" />

  <ul class="divide-y divide-gray-200">
    <BaseSwitchSection
      v-model="sendAsAttachmentField"
      :title="$t('settings.customization.credit_notes.credit_note_email_attachment')"
      :description="
        $t(
          'settings.customization.credit_notes.credit_note_email_attachment_setting_description'
        )
      "
    />
  </ul>
</template>

<script setup>
import { computed, reactive, inject } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import CreditNotesTabCreditNoteNumber from './CreditNotesTabCreditNoteNumber.vue'
import CreditNotesTabDefaultFormats from './CreditNotesTabDefaultFormats.vue'

const utils = inject('utils')
const companyStore = useCompanyStore()

const creditNoteSettings = reactive({
  credit_note_email_attachment: null,
})

utils.mergeSettings(creditNoteSettings, {
  ...companyStore.selectedCompanySettings,
})

const sendAsAttachmentField = computed({
  get: () => {
    return creditNoteSettings.credit_note_email_attachment === 'YES'
  },
  set: async (newValue) => {
    const value = newValue ? 'YES' : 'NO'

    let data = {
      settings: {
        credit_note_email_attachment: value,
      },
    }

    creditNoteSettings.credit_note_email_attachment = value

    await companyStore.updateCompanySettings({
      data,
      message: 'general.setting_updated',
    })
  },
})
</script>
