<script setup lang="ts">
import { ref, computed, watch, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useGlobalStore } from '../../../../stores/global.store'
import { useCompanyStore } from '../../../../stores/company.store'

const companyStore = useCompanyStore()
const globalStore = useGlobalStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const isDataSaving = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(false)

const settingsForm = reactive<Record<string, string>>({
  ...companyStore.selectedCompanySettings,
})

const fiscalYearsList = computed(() => {
  const config = globalStore.config as Record<string, unknown> | null
  const fiscalYears = (config?.fiscal_years ?? []) as Array<{ key: string; value: string }>
  return fiscalYears.map((item) => ({
    ...item,
    key: t(item.key),
  }))
})

watch(
  () => settingsForm.carbon_date_format,
  (val) => {
    if (val) {
      const dateFormatObject = globalStore.dateFormats.find(
        (d) => d.carbon_format_value === val
      )
      if (dateFormatObject) {
        settingsForm.moment_date_format = dateFormatObject.moment_format_value
      }
    }
  }
)

watch(
  () => settingsForm.carbon_time_format,
  (val) => {
    if (val) {
      const timeFormatObject = globalStore.timeFormats.find(
        (d) => d.carbon_format_value === val
      )
      if (timeFormatObject) {
        settingsForm.moment_time_format = timeFormatObject.moment_format_value
      }
    }
  }
)

const invoiceUseTimeField = computed<boolean>({
  get: () => settingsForm.invoice_use_time === 'YES',
  set: async (newValue: boolean) => {
    const value = newValue ? 'YES' : 'NO'
    settingsForm.invoice_use_time = value

    await companyStore.updateCompanySettings({
      data: { settings: { invoice_use_time: value } },
      message: 'general.setting_updated',
    })
  },
})

const discountPerItemField = computed<boolean>({
  get: () => settingsForm.discount_per_item === 'YES',
  set: async (newValue: boolean) => {
    const value = newValue ? 'YES' : 'NO'
    settingsForm.discount_per_item = value

    await companyStore.updateCompanySettings({
      data: { settings: { discount_per_item: value } },
      message: 'general.setting_updated',
    })
  },
})

const expirePdfField = computed<boolean>({
  get: () => settingsForm.automatically_expire_public_links === 'YES',
  set: (newValue: boolean) => {
    settingsForm.automatically_expire_public_links = newValue ? 'YES' : 'NO'
  },
})

const rules = computed(() => ({
  currency: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  language: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  carbon_date_format: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  moment_date_format: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  carbon_time_format: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  moment_time_format: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  time_zone: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  fiscal_year: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  invoice_use_time: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => settingsForm)
)

setInitialData()

async function setInitialData(): Promise<void> {
  isFetchingInitialData.value = true
  await Promise.all([
    globalStore.fetchCurrencies(),
    globalStore.fetchDateFormats(),
    globalStore.fetchTimeFormats(),
    globalStore.fetchTimeZones(),
  ])
  isFetchingInitialData.value = false
}

async function updatePreferencesData(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true

  const data = {
    settings: { ...settingsForm } as Record<string, string>,
  }
  delete data.settings.link_expiry_days

  if (companyStore.selectedCompanySettings.language !== settingsForm.language) {
    const win = window as Record<string, unknown>
    if (typeof win.loadLanguage === 'function') {
      await (win.loadLanguage as (lang: string) => Promise<void>)(settingsForm.language)
    }
  }

  await companyStore.updateCompanySettings({
    data,
    message: 'settings.preferences.updated_message',
  })

  isSaving.value = false
}

async function submitData(): Promise<void> {
  isDataSaving.value = true

  await companyStore.updateCompanySettings({
    data: {
      settings: {
        link_expiry_days: settingsForm.link_expiry_days,
        automatically_expire_public_links:
          settingsForm.automatically_expire_public_links,
      },
    },
    message: 'settings.preferences.updated_message',
  })

  isDataSaving.value = false
}
</script>

<template>
  <form action="" class="relative" @submit.prevent="updatePreferencesData">
    <BaseSettingCard
      :title="$t('settings.menu_title.preferences')"
      :description="$t('settings.preferences.general_settings')"
    >
      <BaseInputGrid class="mt-5">
        <BaseInputGroup
          :content-loading="isFetchingInitialData"
          :label="$t('settings.preferences.currency')"
          :help-text="$t('settings.preferences.company_currency_unchangeable')"
          :error="v$.currency.$error && v$.currency.$errors[0]?.$message"
          required
        >
          <BaseMultiselect
            v-model="settingsForm.currency"
            :content-loading="isFetchingInitialData"
            :options="globalStore.currencies"
            label="name"
            value-prop="id"
            :searchable="true"
            track-by="name"
            :invalid="v$.currency.$error"
            disabled
            class="w-full"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.preferences.default_language')"
          :content-loading="isFetchingInitialData"
          :error="v$.language.$error && v$.language.$errors[0]?.$message"
          required
        >
          <BaseMultiselect
            v-model="settingsForm.language"
            :content-loading="isFetchingInitialData"
            :options="(globalStore.config as Record<string, unknown>)?.languages as Array<{ code: string; name: string }> ?? []"
            label="name"
            value-prop="code"
            class="w-full"
            track-by="name"
            :searchable="true"
            :invalid="v$.language.$error"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.preferences.time_zone')"
          :content-loading="isFetchingInitialData"
          :error="v$.time_zone.$error && v$.time_zone.$errors[0]?.$message"
          required
        >
          <BaseMultiselect
            v-model="settingsForm.time_zone"
            :content-loading="isFetchingInitialData"
            :options="globalStore.timeZones"
            label="key"
            value-prop="value"
            track-by="key"
            :searchable="true"
            :invalid="v$.time_zone.$error"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.preferences.date_format')"
          :content-loading="isFetchingInitialData"
          :error="
            v$.carbon_date_format.$error &&
            v$.carbon_date_format.$errors[0]?.$message
          "
          required
        >
          <BaseMultiselect
            v-model="settingsForm.carbon_date_format"
            :content-loading="isFetchingInitialData"
            :options="globalStore.dateFormats"
            label="display_date"
            value-prop="carbon_format_value"
            track-by="display_date"
            :searchable="true"
            :invalid="v$.carbon_date_format.$error"
            class="w-full"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :content-loading="isFetchingInitialData"
          :error="v$.fiscal_year.$error && v$.fiscal_year.$errors[0]?.$message"
          :label="$t('settings.preferences.fiscal_year')"
          required
        >
          <BaseMultiselect
            v-model="settingsForm.fiscal_year"
            :content-loading="isFetchingInitialData"
            :options="fiscalYearsList"
            label="key"
            value-prop="value"
            :invalid="v$.fiscal_year.$error"
            track-by="key"
            :searchable="true"
            class="w-full"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.preferences.time_format')"
          :content-loading="isFetchingInitialData"
          :error="
            v$.carbon_time_format.$error &&
            v$.carbon_time_format.$errors[0]?.$message
          "
          required
        >
          <BaseMultiselect
            v-model="settingsForm.carbon_time_format"
            :content-loading="isFetchingInitialData"
            :options="globalStore.timeFormats"
            label="display_time"
            value-prop="carbon_format_value"
            track-by="display_time"
            :searchable="true"
            :invalid="v$.carbon_time_format.$error"
            class="w-full"
          />
        </BaseInputGroup>
      </BaseInputGrid>

      <BaseButton
        :content-loading="isFetchingInitialData"
        :disabled="isSaving"
        :loading="isSaving"
        type="submit"
        class="mt-6"
      >
        <template #left="slotProps">
          <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('settings.company_info.save') }}
      </BaseButton>

      <BaseDivider class="mt-6 mb-2" />

      <ul>
        <form @submit.prevent="submitData">
          <BaseSwitchSection
            v-model="expirePdfField"
            :title="$t('settings.preferences.expire_public_links')"
            :description="$t('settings.preferences.expire_setting_description')"
          />

          <BaseInputGroup
            v-if="expirePdfField"
            :content-loading="isFetchingInitialData"
            :label="$t('settings.preferences.expire_public_links')"
            class="mt-2 mb-4"
          >
            <BaseInput
              v-model="settingsForm.link_expiry_days"
              :disabled="settingsForm.automatically_expire_public_links === 'NO'"
              :content-loading="isFetchingInitialData"
              type="number"
            />
          </BaseInputGroup>

          <BaseButton
            :content-loading="isFetchingInitialData"
            :disabled="isDataSaving"
            :loading="isDataSaving"
            type="submit"
            class="mt-6"
          >
            <template #left="slotProps">
              <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
            </template>
            {{ $t('general.save') }}
          </BaseButton>
        </form>

        <BaseDivider class="mt-6 mb-2" />

        <BaseSwitchSection
          v-model="invoiceUseTimeField"
          :title="$t('settings.preferences.invoice_use_time')"
          :description="$t('settings.preferences.invoice_use_time_description')"
        />

        <BaseSwitchSection
          v-model="discountPerItemField"
          :title="$t('settings.preferences.discount_per_item')"
          :description="$t('settings.preferences.discount_setting_description')"
        />
      </ul>
    </BaseSettingCard>
  </form>
</template>
