<script setup lang="ts">
import { reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useGlobalStore } from '@/scripts/stores/global.store'

const { t } = useI18n()
const companyStore = useCompanyStore()
const globalStore = useGlobalStore()

const settingsForm = reactive<{ retrospective_edits: string | null }>({
  retrospective_edits:
    companyStore.selectedCompanySettings.retrospective_edits ?? null,
})

const retrospectiveEditOptions = [
  { key: 'settings.customization.invoices.allow', value: 'allow' },
  { key: 'settings.customization.invoices.disable_on_invoice_partial_paid', value: 'disable_on_invoice_partial_paid' },
  { key: 'settings.customization.invoices.disable_on_invoice_paid', value: 'disable_on_invoice_paid' },
  { key: 'settings.customization.invoices.disable_on_invoice_sent', value: 'disable_on_invoice_sent' },
]

async function submitForm(): Promise<void> {
  const data = {
    settings: {
      ...settingsForm,
    },
  }

  await companyStore.updateCompanySettings({
    data,
    message: 'settings.customization.invoices.invoice_settings_updated',
  })
}
</script>

<template>
  <BaseSettingCard
    :title="$t('settings.customization.invoices.retrospective_edits')"
    :description="
      $t('settings.customization.invoices.retrospective_edits_description')
    "
  >
    <BaseInputGroup required>
      <BaseRadio
        v-for="option in retrospectiveEditOptions"
        :id="option.value"
        :key="option.value"
        v-model="settingsForm.retrospective_edits"
        :label="$t(option.key)"
        size="sm"
        name="retrospective_edits"
        :value="option.value"
        class="mt-2"
        @update:modelValue="submitForm"
      />
    </BaseInputGroup>
  </BaseSettingCard>
</template>
