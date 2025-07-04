<template>
  <BaseSettingCard
    :title="$t('settings.reminders.section_title')"
    :description="$t('settings.reminders.section_description')"
  >

    <form action="" @submit.prevent="submitForm">
      <div class="grid-cols-2 col-span-1 mt-14">

        <BaseSwitchSection
          v-model="enableInvoiceDueRemindersField"
          :title="$t('settings.reminders.send_reminders_invoice_due')"
          :description="$t('settings.reminders.send_reminders_invoice_due_desc')"
        />

        <BaseInputGroup
          :error="
            v$.reminders_bcc.$error &&
            v$.reminders_bcc.$errors[0].$message
          "
          :label="$t('settings.reminders.bcc')"
          class="my-2"
          required
        >
          <BaseInput
            v-model.trim="settingsForm.reminders_bcc"
            :invalid="v$.reminders_bcc.$error"
            type="email"
            @input="v$.reminders_bcc.$touch()"
          />
        </BaseInputGroup>

        <br>
        <h6 class="text-gray-900 text-lg font-medium">
          {{ $t('settings.reminders.frequency.title') }}
        </h6>
        <p class="mt-1 text-sm text-gray-500 mb-2">
          {{ $t('settings.reminders.frequency.cron_example') }}
        </p>
        <BaseInputGroup
          :label="$t('settings.reminders.frequency.input_frequency')"
          :content-loading="isLoading"
          required
          :error="v$.reminders_invoice_due_frequency.$error && v$.reminders_invoice_due_frequency.$errors[0].$message"
        >
          <BaseInput
            v-model="settingsForm.reminders_invoice_due_frequency"
            :content-loading="isLoading"
            :invalid="v$.reminders_invoice_due_frequency.$error"
            @input="v$.reminders_invoice_due_frequency.$touch()"
          />
        </BaseInputGroup>        

        <BaseButton
          :disabled="isSaving"
          :loading="isSaving"
          variant="primary"
          type="submit"
          class="mt-6"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              :class="slotProps.class"
              name="ArrowDownOnSquareIcon"
            />
          </template>

          {{ $t('settings.notification.save') }}
        </BaseButton>
      </div>
    </form>

    <BaseDivider class="mt-6 mb-2" />
    
  </BaseSettingCard>
</template>

<script setup>
import { ref, onMounted, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, email, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const companyStore = useCompanyStore()

let isSaving = ref(false)
const { t } = useI18n()

const settingsForm = reactive({
  reminders_bcc: companyStore.selectedCompanySettings.reminders_bcc,
  reminders_invoice_due: companyStore.selectedCompanySettings.reminders_invoice_due,  
  reminders_invoice_due_frequency: companyStore.selectedCompanySettings.reminders_invoice_due_frequency
});

const cron_validation = (val) => {
  if(!val) return true;
  if(typeof val === 'string') {
    return val.match(/((((\d+,)+\d+|(\d+(\/|-)\d+)|\d+|\*) ?){5,7})/) != null;
  };
  return false;
};

const rules = computed(() => {
  return {
    reminders_bcc: {
      required: helpers.withMessage(t('validation.required'), required),
      email: helpers.withMessage(t('validation.email_incorrect'), email),
    },
    reminders_invoice_due_frequency: {
      required: helpers.withMessage(t('validation.required'), required),
      cron: helpers.withMessage(t('settings.reminders.invalid_cron'), cron_validation)
    }
  }
})

const v$ = useVuelidate(
  rules,
  computed(() => settingsForm)
)

const enableInvoiceDueRemindersField = computed({
  get: () => {
    return settingsForm.reminders_invoice_due === 'YES'
  },
  set: async (newValue) => {
    const value = newValue ? 'YES' : 'NO'

    let data = {
      settings: {
        reminders_invoice_due: value,
      },
    }

    settingsForm.reminders_invoice_due = value

    await companyStore.updateCompanySettings({
      data,
      message: 'general.setting_updated',
    })
  },
})

async function submitForm() {
  v$.value.$touch()
  if (v$.value.$invalid) {
    return true
  }

  isSaving.value = true

  const data = {
    settings: {
      reminders_invoice_due: settingsForm.reminders_invoice_due,  
      reminders_bcc: settingsForm.reminders_bcc,
      reminders_invoice_due_frequency: settingsForm.reminders_invoice_due_frequency,  
    },
  }

  await companyStore.updateCompanySettings({
    data,
    message: 'settings.reminders.settings_save_message',
  })

  isSaving.value = false
}
</script>
