<template>
  <BaseSettingCard
    :title="$t('settings.notification.title')"
    :description="$t('settings.notification.description')"
  >
    <form action="" @submit.prevent="onBasicSettingsSubmit">
      <div class="grid-cols-2 col-span-1 mt-14">
        <BaseInputGroup
          :error="
            vB$.notification_email.$error &&
            vB$.notification_email.$errors[0].$message
          "
          :label="$t('settings.notification.email')"
          class="my-2"
          required
        >
          <BaseInput
            v-model.trim="basicsForm.notification_email"
            :invalid="vB$.notification_email.$error"
            type="email"
            @input="vB$.notification_email.$touch()"
          />
        </BaseInputGroup>

        <BaseButton
          :disabled="isBasicsSaving"
          :loading="isBasicsSaving"
          variant="primary"
          type="submit"
          class="mt-6"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isBasicsSaving"
              :class="slotProps.class"
              name="ArrowDownOnSquareIcon"
            />
          </template>

          {{ $t('settings.notification.save') }}
        </BaseButton>
      </div>
    </form>

    <BaseDivider class="mt-6 mb-2" />

    <ul class="divide-y divide-gray-200">
      <BaseSwitchSection
        v-model="invoiceViewedField"
        :title="$t('settings.notification.invoice_viewed')"
        :description="$t('settings.notification.invoice_viewed_desc')"
      />

      <BaseSwitchSection
        v-model="estimateViewedField"
        :title="$t('settings.notification.estimate_viewed')"
        :description="$t('settings.notification.estimate_viewed_desc')"
      />

      <BaseSwitchSection
        v-model="enableInvoiceDueRemindersField"
        :title="$t('settings.notification.invoice_due')"
        :description="$t('settings.notification.invoice_due_desc')"
      >
        <BaseSwitchInnerContent>
          <form action="POST" @submit.prevent="onInvoiceDueSettingsSubmit" class="grid-cols-2 col-span-1">

            <!-- Invoice Due Frequency (days) -->
            <BaseInputGroup
              :label="$t('settings.notification.frequency.title')"
              :help-text="$t('settings.notification.frequency.help_text')"
              required
              :error="vI$.invoice_due_frequency.$error && vI$.invoice_due_frequency.$errors[0].$message"
            >
              <BaseInput
                v-model.number="invoiceDueForm.invoice_due_frequency"
                :invalid="vI$.invoice_due_frequency.$error"
                type="number"
                min="1"
                step="1"
                @input="vI$.invoice_due_frequency.$touch()"
              />
            </BaseInputGroup>

            <!-- Invoice Due Email Subject -->
            <BaseInputGroup
              :error="vI$.invoice_due_email_subject.$error && vI$.invoice_due_email_subject.$errors[0].$message"
              :label="$t('settings.notification.invoice_due_email_subject')"
              class="my-2"
              required
            >
              <BaseInput
                v-model.trim="invoiceDueForm.invoice_due_email_subject"
                :invalid="vI$.invoice_due_email_subject.$error"
                type="text"
                @input="vI$.invoice_due_email_subject.$touch()"
              />
            </BaseInputGroup>

            <!-- Invoice Due Email Body -->
            <BaseInputGroup
              :label="$t('settings.notification.invoice_due_email_body')"
              class="my-2"
            >
              <BaseCustomInput
                v-model="invoiceDueForm.invoice_due_email_body"
                :fields="invoiceMailFields"
              />
            </BaseInputGroup>

            <!-- Invoice Due BCC -->
            <BaseInputGroup
              :error="vI$.invoice_due_bcc.$error && vI$.invoice_due_bcc.$errors[0].$message"
              :label="$t('settings.notification.invoice_due_bcc')"
              class="my-2"
            >
              <BaseInput
                v-model.trim="invoiceDueForm.invoice_due_bcc"
                :invalid="vI$.invoice_due_bcc.$error"
                type="email"
                @input="vI$.invoice_due_bcc.$touch()"
              />
            </BaseInputGroup>

            <!-- Attach PDF -->
            <BaseSwitchSection
              v-model="invoiceDueForm.invoice_due_attach_pdf"
              :title="$t('settings.notification.invoice_due_attach_pdf')"
              :description="$t('settings.notification.invoice_due_attach_pdf')"
            />

            <BaseButton
              :disabled="isInvoiceDueSaving"
              :loading="isInvoiceDueSaving"
              variant="primary"
              type="submit"
              class="mt-6"
            >
              <template #left="slotProps">
                <BaseIcon
                  v-if="!isInvoiceDueSaving"
                  :class="slotProps.class"
                  name="ArrowDownOnSquareIcon"
                />
              </template>

              {{ $t('settings.notification.save') }}
            </BaseButton>

          </form>
        </BaseSwitchInnerContent>

      </BaseSwitchSection>

    </ul>

  </BaseSettingCard>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { email, helpers, required, minValue, numeric } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const companyStore = useCompanyStore()

let isBasicsSaving = ref(false)

let isInvoiceDueSaving = ref(false);

const { t } = useI18n()

const basicsForm = reactive({
  notify_invoice_viewed: companyStore.selectedCompanySettings.notify_invoice_viewed,
  notify_estimate_viewed: companyStore.selectedCompanySettings.notify_estimate_viewed,
  notification_email: companyStore.selectedCompanySettings.notification_email,
})

const invoiceDueForm = reactive({
  invoice_due_bcc: companyStore.selectedCompanySettings.invoice_due_bcc,
  notify_invoice_due: companyStore.selectedCompanySettings.notify_invoice_due,
  invoice_due_frequency: companyStore.selectedCompanySettings.invoice_due_frequency,
  invoice_due_email_subject: companyStore.selectedCompanySettings.invoice_due_email_subject,
  invoice_due_email_body: companyStore.selectedCompanySettings.invoice_due_email_body,
  invoice_due_attach_pdf: companyStore.selectedCompanySettings.invoice_due_attach_pdf
})

const cron_validation = (val) => {
  if (!val) return true
  if (typeof val === 'string') {
    return val.match(/((((\d+,)+\d+|(\d+(\/|-)\d+)|\d+|\*) ?){5,7})/) != null
  }

  return false
}

const rulesBasics = computed(() => {
  return {
    notification_email: {
      required: helpers.withMessage(t('validation.required'), required),
      email: helpers.withMessage(t('validation.email_incorrect'), email)
    }
  }
})

const vB$ = useVuelidate(
  rulesBasics,
  computed(() => basicsForm)
)

const rulesInvoiceDue = computed(() => {
  return {
    invoice_due_bcc: {
      email: helpers.withMessage(t('validation.email_incorrect'), email)
    },
    invoice_due_frequency: {
      required: helpers.withMessage(t('validation.required'), required),
      numeric: helpers.withMessage(t('validation.numbers_only'), numeric),
      minValue: helpers.withMessage(t('validation.number_length_minvalue'), minValue(1))
    },
    invoice_due_email_subject: {
      required: helpers.withMessage(t('validation.required'), required),
    },
  }
})

const vI$ = useVuelidate(
  rulesInvoiceDue,
  computed(() => invoiceDueForm)
)

const invoiceViewedField = computed({
  get: () => {
    return basicsForm.notify_invoice_viewed === 'YES'
  },
  set: async (newValue) => {
    const value = newValue ? 'YES' : 'NO'

    let data = {
      settings: {
        notify_invoice_viewed: value
      }
    }

    basicsForm.notify_invoice_viewed = value

    await companyStore.updateCompanySettings({
      data,
      message: 'general.setting_updated'
    })
  }
})

const estimateViewedField = computed({
  get: () => {
    return basicsForm.notify_estimate_viewed === 'YES'
  },
  set: async (newValue) => {
    const value = newValue ? 'YES' : 'NO'

    let data = {
      settings: {
        notify_estimate_viewed: value
      }
    }

    basicsForm.notify_estimate_viewed = value

    await companyStore.updateCompanySettings({
      data,
      message: 'general.setting_updated'
    })
  }
})

const enableInvoiceDueRemindersField = computed({
  get: () => {
    return invoiceDueForm.notify_invoice_due === 'YES'
  },
  set: async (newValue) => {
    invoiceDueForm.notify_invoice_due = newValue ? 'YES' : 'NO'
  }
})

async function onBasicSettingsSubmit() {
  vB$.value.$touch()
  if (vB$.value.$invalid) {
    return true
  }

  isBasicsSaving.value = true

  const data = {
    settings: {
      notification_email: basicsForm.notification_email,
    }
  }

  await companyStore.updateCompanySettings({
    data,
    message: 'settings.notification.email_save_message'
  })

  isBasicsSaving.value = false;
}

async function onInvoiceDueSettingsSubmit() {
  vI$.value.$touch()
  if (vI$.value.$invalid) {
    console.log(vI$);
    console.log(vI$.value.$errors);
    return true
  }

  isInvoiceDueSaving.value = true

  const data = {
    settings: {
      notify_invoice_due: invoiceDueForm.notify_invoice_due,
      invoice_due_bcc: invoiceDueForm.invoice_due_bcc,
      invoice_due_frequency: invoiceDueForm.invoice_due_frequency,
      invoice_due_email_subject: invoiceDueForm.invoice_due_email_subject,
      invoice_due_email_body: invoiceDueForm.invoice_due_email_body,
      invoice_due_attach_pdf: invoiceDueForm.invoice_due_attach_pdf
    }
  }

  await companyStore.updateCompanySettings({
    data,
    message: 'settings.notification.invoice_due_save_message'
  })

  isInvoiceDueSaving.value = false
}

/* Invoice Due Email Body Input */
const invoiceMailFields = ref([
  'customer',
  'customerCustom',
  'invoice',
  'invoiceCustom',
  'company'
])
</script>
