<template>
  <BaseWizardStep
    :title="$t('wizard.preferences')"
    :description="$t('wizard.preferences_desc')"
    step-container="bg-surface border border-line-default border-solid mb-8 md:w-full p-8 rounded w-full"
  >
    <form @submit.prevent="next">
      <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
        <BaseInputGroup
          :label="$t('wizard.currency')"
          :error="v$.currency.$error ? String(v$.currency.$errors[0]?.$message) : undefined"
          :content-loading="isFetchingInitialData"
          required
        >
          <BaseMultiselect
            v-model="currentPreferences.currency"
            :content-loading="isFetchingInitialData"
            :options="currencies"
            label="name"
            value-prop="id"
            :searchable="true"
            track-by="name"
            :placeholder="$t('settings.currencies.select_currency')"
            :invalid="v$.currency.$error"
            class="w-full"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.preferences.default_language')"
          :error="v$.language.$error ? String(v$.language.$errors[0]?.$message) : undefined"
          :content-loading="isFetchingInitialData"
          required
        >
          <BaseMultiselect
            v-model="currentPreferences.language"
            :content-loading="isFetchingInitialData"
            :options="languages"
            label="name"
            value-prop="code"
            :placeholder="$t('settings.preferences.select_language')"
            class="w-full"
            track-by="name"
            :searchable="true"
            :invalid="v$.language.$error"
          />
        </BaseInputGroup>
      </div>

      <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
        <BaseInputGroup
          :label="$t('wizard.date_format')"
          :error="v$.carbon_date_format.$error ? String(v$.carbon_date_format.$errors[0]?.$message) : undefined"
          :content-loading="isFetchingInitialData"
          required
        >
          <BaseMultiselect
            v-model="currentPreferences.carbon_date_format"
            :content-loading="isFetchingInitialData"
            :options="dateFormats"
            label="display_date"
            value-prop="carbon_format_value"
            :placeholder="$t('settings.preferences.select_date_format')"
            track-by="display_date"
            searchable
            :invalid="v$.carbon_date_format.$error"
            class="w-full"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('wizard.time_zone')"
          :error="v$.time_zone.$error ? String(v$.time_zone.$errors[0]?.$message) : undefined"
          :content-loading="isFetchingInitialData"
          required
        >
          <BaseMultiselect
            v-model="currentPreferences.time_zone"
            :content-loading="isFetchingInitialData"
            :options="timeZones"
            label="key"
            value-prop="value"
            :placeholder="$t('settings.preferences.select_time_zone')"
            track-by="key"
            :searchable="true"
            :invalid="v$.time_zone.$error"
          />
        </BaseInputGroup>
      </div>

      <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2">
        <BaseInputGroup
          :label="$t('wizard.fiscal_year')"
          :error="v$.fiscal_year.$error ? String(v$.fiscal_year.$errors[0]?.$message) : undefined"
          :content-loading="isFetchingInitialData"
          required
        >
          <BaseMultiselect
            v-model="currentPreferences.fiscal_year"
            :content-loading="isFetchingInitialData"
            :options="fiscalYearsList"
            label="key"
            value-prop="value"
            :placeholder="$t('settings.preferences.select_financial_year')"
            :invalid="v$.fiscal_year.$error"
            track-by="key"
            :searchable="true"
            class="w-full"
          />
        </BaseInputGroup>
      </div>

      <BaseButton
        :loading="isSaving"
        :disabled="isSaving"
        :content-loading="isFetchingInitialData"
        class="mt-4"
      >
        <template #left="slotProps">
          <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('wizard.save_cont') }}
      </BaseButton>
    </form>
  </BaseWizardStep>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { required, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { client } from '../../../api/client'
import { API } from '../../../api/endpoints'

interface PreferencesData {
  currency: number
  language: string
  carbon_date_format: string
  time_zone: string
  fiscal_year: string
}

interface KeyValueOption {
  key: string
  value: string
}

interface DateFormatOption {
  display_date: string
  carbon_format_value: string
}

interface CurrencyOption {
  id: number
  name: string
}

interface LanguageOption {
  code: string
  name: string
}

interface Emits {
  (e: 'next', step: string): void
}

const emit = defineEmits<Emits>()
const { t } = useI18n()
const router = useRouter()

const isSaving = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(false)

const currencies = ref<CurrencyOption[]>([])
const languages = ref<LanguageOption[]>([])
const dateFormats = ref<DateFormatOption[]>([])
const timeZones = ref<KeyValueOption[]>([])
const fiscalYears = ref<KeyValueOption[]>([])

const currentPreferences = reactive<PreferencesData>({
  currency: 3,
  language: 'en',
  carbon_date_format: 'd M Y',
  time_zone: 'UTC',
  fiscal_year: '1-12',
})

const fiscalYearsList = computed<KeyValueOption[]>(() => {
  return fiscalYears.value.map((item) => ({
    ...item,
    key: t(item.key),
  }))
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
  time_zone: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  fiscal_year: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, currentPreferences)

onMounted(async () => {
  isFetchingInitialData.value = true
  try {
    const [currRes, dateRes, tzRes, fyRes, langRes] = await Promise.all([
      client.get(API.CURRENCIES),
      client.get(API.DATE_FORMATS),
      client.get(API.TIMEZONES),
      client.get(`${API.CONFIG}?key=fiscal_years`),
      client.get(`${API.CONFIG}?key=languages`),
    ])
    currencies.value = currRes.data.data ?? currRes.data
    dateFormats.value = dateRes.data.data ?? dateRes.data
    timeZones.value = tzRes.data.data ?? tzRes.data
    fiscalYears.value = fyRes.data.data ?? fyRes.data ?? []
    languages.value = langRes.data.data ?? langRes.data ?? []
  } finally {
    isFetchingInitialData.value = false
  }
})

async function next(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  const confirmed = window.confirm(t('wizard.currency_set_alert'))
  if (!confirmed) return

  isSaving.value = true

  try {
    const settingsPayload = {
      settings: { ...currentPreferences },
    }

    const { data: res } = await client.post(API.COMPANY_SETTINGS, settingsPayload)

    if (res) {
      const userSettings = {
        settings: { language: currentPreferences.language },
      }
      await client.put(API.ME_SETTINGS, userSettings)

      if (res.token) {
        localStorage.setItem('auth.token', res.token)
      }

      emit('next', 'COMPLETED')
      router.push('/admin/dashboard')
    }
  } finally {
    isSaving.value = false
  }
}
</script>
