<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { numeric, helpers, requiredIf } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useCompanyStore } from '@/scripts/stores/company.store'

const { t } = useI18n()
const companyStore = useCompanyStore()

const isSaving = ref<boolean>(false)

const dueDateSettings = reactive<{
  invoice_set_due_date_automatically: string
  invoice_due_date_days: string
}>({
  invoice_set_due_date_automatically:
    companyStore.selectedCompanySettings.invoice_set_due_date_automatically ??
    'NO',
  invoice_due_date_days:
    companyStore.selectedCompanySettings.invoice_due_date_days ?? '',
})

const dueDateAutoField = computed<boolean>({
  get: () => dueDateSettings.invoice_set_due_date_automatically === 'YES',
  set: (newValue: boolean) => {
    dueDateSettings.invoice_set_due_date_automatically = newValue
      ? 'YES'
      : 'NO'
  },
})

const rules = computed(() => ({
  dueDateSettings: {
    invoice_due_date_days: {
      required: helpers.withMessage(
        t('validation.required'),
        requiredIf(dueDateAutoField.value)
      ),
      numeric: helpers.withMessage(t('validation.numbers_only'), numeric),
    },
  },
}))

const v$ = useVuelidate(rules, { dueDateSettings })

async function submitForm(): Promise<void> {
  v$.value.dueDateSettings.$touch()

  if (v$.value.dueDateSettings.$invalid) {
    return
  }

  isSaving.value = true

  const data: { settings: Record<string, string> } = {
    settings: {
      invoice_set_due_date_automatically:
        dueDateSettings.invoice_set_due_date_automatically,
    },
  }

  if (dueDateAutoField.value) {
    data.settings.invoice_due_date_days =
      dueDateSettings.invoice_due_date_days
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
      :title="$t('settings.customization.invoices.due_date')"
      :description="
        $t('settings.customization.invoices.due_date_description')
      "
    >
      <BaseSwitchSection
        v-model="dueDateAutoField"
        :title="
          $t('settings.customization.invoices.set_due_date_automatically')
        "
        :description="
          $t(
            'settings.customization.invoices.set_due_date_automatically_description'
          )
        "
      />

      <BaseInputGroup
        v-if="dueDateAutoField"
        :label="$t('settings.customization.invoices.due_date_days')"
        :error="
          v$.dueDateSettings.invoice_due_date_days.$error &&
          v$.dueDateSettings.invoice_due_date_days.$errors[0].$message
        "
        class="mt-2 mb-4"
      >
        <div class="w-full sm:w-1/2 md:w-1/4 lg:w-1/5">
          <BaseInput
            v-model="dueDateSettings.invoice_due_date_days"
            :invalid="v$.dueDateSettings.invoice_due_date_days.$error"
            type="number"
            @input="v$.dueDateSettings.invoice_due_date_days.$touch()"
          />
        </div>
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
