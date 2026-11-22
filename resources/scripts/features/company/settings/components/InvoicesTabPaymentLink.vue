<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { extractValidationErrors } from '@/scripts/utils/error-handling'

const SETTING = 'invoice_payment_link'
const QR_SETTING = 'invoice_payment_link_qr'

// Mirrors InvoicePaymentLinks::PLACEHOLDERS. Listed as code rather than in a
// translation, where vue-i18n would read the braces as its own placeholders.
const PLACEHOLDERS = ['{INVOICE_NUMBER}', '{INVOICE_DUE_AMOUNT}', '{INVOICE_CURRENCY}']

const { t } = useI18n()
const companyStore = useCompanyStore()

const isSaving = ref<boolean>(false)
const serverError = ref<string | null>(null)

const paymentLinkSettings = reactive<{
  invoice_payment_link: string
  invoice_payment_link_qr: string
}>({
  invoice_payment_link: companyStore.selectedCompanySettings[SETTING] ?? '',
  invoice_payment_link_qr: companyStore.selectedCompanySettings[QR_SETTING] ?? 'YES',
})

const qrCodeField = computed<boolean>({
  get: () => paymentLinkSettings.invoice_payment_link_qr !== 'NO',
  set: (newValue: boolean) => {
    paymentLinkSettings.invoice_payment_link_qr = newValue ? 'YES' : 'NO'
  },
})

const rules = computed(() => ({
  paymentLinkSettings: {
    invoice_payment_link: {
      webAddress: helpers.withMessage(
        t('settings.customization.invoices.payment_link_invalid'),
        (value: string) => !value || /^https?:\/\/\S+$/i.test(value.trim())
      ),
    },
  },
}))

const v$ = useVuelidate(rules, { paymentLinkSettings })

const linkError = computed<string | null>(() => {
  const field = v$.value.paymentLinkSettings.invoice_payment_link

  if (field.$error) {
    return field.$errors[0].$message as string
  }

  return serverError.value
})

async function submitForm(): Promise<void> {
  serverError.value = null
  v$.value.paymentLinkSettings.$touch()

  if (v$.value.paymentLinkSettings.$invalid) {
    return
  }

  isSaving.value = true

  try {
    await companyStore.updateCompanySettings({
      data: {
        settings: {
          [SETTING]: paymentLinkSettings.invoice_payment_link.trim(),
          [QR_SETTING]: paymentLinkSettings.invoice_payment_link_qr,
        },
      },
      message: 'settings.customization.invoices.invoice_settings_updated',
    })
  } catch (err: unknown) {
    serverError.value = extractValidationErrors(err)[`settings.${SETTING}`]?.[0] ?? null
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="submitForm">
    <BaseSettingCard
      :title="$t('settings.customization.invoices.payment_link')"
      :description="$t('settings.customization.invoices.payment_link_description')"
    >
      <BaseInputGroup
        :label="$t('settings.customization.invoices.payment_link_url')"
        :error="linkError"
        class="mb-4"
      >
        <BaseInput
          v-model="paymentLinkSettings.invoice_payment_link"
          type="url"
          placeholder="https://"
          :invalid="!!linkError"
          @input="v$.paymentLinkSettings.invoice_payment_link.$touch()"
        />
      </BaseInputGroup>

      <div class="mb-4 text-sm text-muted">
        <p class="mb-1.5">
          {{ $t('settings.customization.invoices.payment_link_placeholders') }}
        </p>
        <ul class="flex flex-wrap gap-1.5">
          <li v-for="placeholder in PLACEHOLDERS" :key="placeholder">
            <code class="rounded bg-surface-tertiary px-1.5 py-0.5 text-xs text-body">{{ placeholder }}</code>
          </li>
        </ul>
      </div>

      <BaseSwitchSection
        v-model="qrCodeField"
        :title="$t('settings.customization.invoices.payment_link_qr')"
        :description="$t('settings.customization.invoices.payment_link_qr_description')"
      />

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
