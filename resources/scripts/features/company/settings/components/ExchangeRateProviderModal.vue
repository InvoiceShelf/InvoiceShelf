<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import useVuelidate from '@vuelidate/core'
import { required, helpers } from '@vuelidate/validators'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { exchangeRateService } from '@/scripts/api/services/exchange-rate.service'
import type {
  DriverConfigField,
  ExchangeRateDriverOption,
} from '@/scripts/api/services/exchange-rate.service'
import { useDebounceFn } from '@vueuse/core'

interface ExchangeRateForm {
  id: number | null
  driver: string
  key: string | null
  active: boolean
  currencies: string[]
}

const { t } = useI18n()
const modalStore = useModalStore()
const notificationStore = useNotificationStore()

const isSaving = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(false)
const isFetchingCurrencies = ref<boolean>(false)
const isEdit = ref<boolean>(false)
const currenciesAlreadyInUsed = ref<string[]>([])
const supportedCurrencies = ref<string[]>([])
const drivers = ref<ExchangeRateDriverOption[]>([])

const currentExchangeRate = ref<ExchangeRateForm>({
  id: null,
  driver: '',
  key: null,
  active: true,
  currencies: [],
})

// Generic key/value bag for driver-specific config (driver_config JSON column).
// Populated from each driver's `config_fields` metadata; reset when driver changes.
const driverConfig = ref<Record<string, string>>({})

const modalActive = computed<boolean>(
  () =>
    modalStore.active &&
    modalStore.componentName === 'ExchangeRateProviderModal'
)

const selectedDriver = computed<ExchangeRateDriverOption | undefined>(() =>
  drivers.value.find((d) => d.value === currentExchangeRate.value.driver)
)

const driverSite = computed<string>(() => selectedDriver.value?.website ?? '')

const driverConfigFields = computed<DriverConfigField[]>(
  () => selectedDriver.value?.config_fields ?? []
)

const visibleConfigFields = computed<DriverConfigField[]>(() =>
  driverConfigFields.value.filter((field) => isFieldVisible(field))
)

const driversLists = computed(() =>
  drivers.value.map((driver) => ({
    value: driver.value,
    label: t(driver.label),
  }))
)

function isFieldVisible(field: DriverConfigField): boolean {
  if (!field.visible_when) return true
  return Object.entries(field.visible_when).every(
    ([key, value]) => driverConfig.value[key] === value
  )
}

function fieldOptions(field: DriverConfigField) {
  return (field.options ?? []).map((option) => ({
    value: option.value,
    label: t(option.label),
  }))
}

const rules = computed(() => ({
  driver: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  key: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  currencies: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, currentExchangeRate)

const fetchCurrenciesDebounced = useDebounceFn(() => {
  fetchCurrencies()
}, 500)

watch(() => currentExchangeRate.value.key, (newVal) => {
  if (newVal) fetchCurrenciesDebounced()
})

// Refetch supported currencies whenever any driver_config field changes —
// some drivers (e.g. Currency Converter) need the config to construct the API URL.
watch(driverConfig, () => {
  if (currentExchangeRate.value.key) fetchCurrenciesDebounced()
}, { deep: true })

function dismiss(): void {
  currenciesAlreadyInUsed.value = []
}

function removeUsedSelectedCurrencies(): void {
  const { currencies } = currentExchangeRate.value
  currenciesAlreadyInUsed.value.forEach((uc) => {
    const idx = currencies.indexOf(uc)
    if (idx > -1) currencies.splice(idx, 1)
  })
  currenciesAlreadyInUsed.value = []
}

function resetCurrency(): void {
  currentExchangeRate.value.key = null
  currentExchangeRate.value.currencies = []
  supportedCurrencies.value = []
  resetDriverConfig()
}

// Rebuild driver_config from the selected driver's field defaults whenever the
// driver changes. Without this, fields from a previous driver would linger.
function resetDriverConfig(): void {
  const fresh: Record<string, string> = {}
  for (const field of driverConfigFields.value) {
    if (field.default !== undefined) {
      fresh[field.key] = field.default
    }
  }
  driverConfig.value = fresh
}

function resetModalData(): void {
  supportedCurrencies.value = []
  currentExchangeRate.value = {
    id: null,
    driver: drivers.value[0]?.value ?? '',
    key: null,
    active: true,
    currencies: [],
  }
  resetDriverConfig()
  currenciesAlreadyInUsed.value = []
  isEdit.value = false
}

async function fetchInitialData(): Promise<void> {
  isFetchingInitialData.value = true

  try {
    const driversRes = await exchangeRateService.getDrivers()
    drivers.value = driversRes.exchange_rate_drivers ?? []

    if (modalStore.data && typeof modalStore.data === 'number') {
      isEdit.value = true
      const response = await exchangeRateService.getProvider(modalStore.data)
      if (response.data) {
        const provider = response.data
        currentExchangeRate.value = {
          id: provider.id,
          driver: provider.driver,
          key: provider.key,
          active: provider.active,
          currencies: provider.currencies ?? [],
        }
        // Hydrate driverConfig from the persisted JSON column. Field defaults from
        // the schema fill any missing keys.
        const persisted = (provider as { driver_config?: Record<string, string> }).driver_config ?? {}
        const merged: Record<string, string> = {}
        for (const field of driverConfigFields.value) {
          merged[field.key] = persisted[field.key] ?? field.default ?? ''
        }
        driverConfig.value = merged
      }
    } else {
      currentExchangeRate.value.driver = drivers.value[0]?.value ?? ''
      resetDriverConfig()
    }
  } finally {
    isFetchingInitialData.value = false
  }
}

async function fetchCurrencies(): Promise<void> {
  const { driver, key } = currentExchangeRate.value
  if (!driver || !key) return

  // If any visible config field is empty, hold off — the driver likely needs it
  // to talk to its API.
  for (const field of visibleConfigFields.value) {
    if (!driverConfig.value[field.key]) return
  }

  isFetchingCurrencies.value = true
  try {
    const config = buildDriverConfigPayload()
    const res = await exchangeRateService.getSupportedCurrencies({
      driver,
      key,
      driver_config: Object.keys(config).length ? config : undefined,
    })
    supportedCurrencies.value = res.supportedCurrencies ?? []
  } finally {
    isFetchingCurrencies.value = false
  }
}

// Strip values for fields that aren't currently visible (e.g. the URL field
// when the server type isn't DEDICATED) so we never persist stale config.
function buildDriverConfigPayload(): Record<string, string> {
  const payload: Record<string, string> = {}
  for (const field of visibleConfigFields.value) {
    const value = driverConfig.value[field.key]
    if (value !== undefined && value !== '') {
      payload[field.key] = value
    }
  }
  return payload
}

async function submitExchangeRate(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  const data: Record<string, unknown> = {
    ...currentExchangeRate.value,
  }

  const config = buildDriverConfigPayload()
  if (Object.keys(config).length) {
    data.driver_config = config
  }

  isSaving.value = true
  try {
    if (isEdit.value && currentExchangeRate.value.id) {
      await exchangeRateService.updateProvider(
        currentExchangeRate.value.id,
        data as Parameters<typeof exchangeRateService.updateProvider>[1]
      )
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.exchange_rate.updated_message',
      })
    } else {
      await exchangeRateService.createProvider(
        data as Parameters<typeof exchangeRateService.createProvider>[0]
      )
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.exchange_rate.created_message',
      })
    }

    if (modalStore.refreshData) {
      modalStore.refreshData()
    }
    closeExchangeRateModal()
  } finally {
    isSaving.value = false
  }
}

function closeExchangeRateModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    resetModalData()
    v$.value.$reset()
  }, 300)
}
</script>

<template>
  <BaseModal
    :show="modalActive"
    @close="closeExchangeRateModal"
    @open="fetchInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeExchangeRateModal"
        />
      </div>
    </template>

    <form @submit.prevent="submitExchangeRate">
      <div class="px-4 md:px-8 py-8 overflow-y-auto sm:p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('settings.exchange_rate.driver')"
            :content-loading="isFetchingInitialData"
            required
            :error="v$.driver.$error && v$.driver.$errors[0].$message"
            :help-text="driverSite"
          >
            <BaseMultiselect
              v-model="currentExchangeRate.driver"
              :options="driversLists"
              :content-loading="isFetchingInitialData"
              value-prop="value"
              :can-deselect="true"
              label="label"
              :searchable="true"
              :invalid="v$.driver.$error"
              track-by="label"
              @update:model-value="resetCurrency"
            />
          </BaseInputGroup>

          <!-- Driver-specific config fields rendered from metadata -->
          <BaseInputGroup
            v-for="field in visibleConfigFields"
            :key="field.key"
            :label="$t(field.label)"
            :content-loading="isFetchingInitialData"
            required
          >
            <BaseMultiselect
              v-if="field.type === 'select'"
              v-model="driverConfig[field.key]"
              :options="fieldOptions(field)"
              :content-loading="isFetchingInitialData"
              value-prop="value"
              label="label"
              :can-deselect="false"
              :searchable="true"
              track-by="label"
              @update:model-value="resetCurrency"
            />
            <BaseInput
              v-else
              v-model="driverConfig[field.key]"
              :content-loading="isFetchingInitialData"
              type="text"
              :name="field.key"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.exchange_rate.key')"
            required
            :content-loading="isFetchingInitialData"
            :error="v$.key.$error && v$.key.$errors[0].$message"
          >
            <BaseInput
              v-model="currentExchangeRate.key"
              :content-loading="isFetchingInitialData"
              type="text"
              name="key"
              :loading="isFetchingCurrencies"
              loading-position="right"
              :invalid="v$.key.$error"
            />
          </BaseInputGroup>

          <BaseInputGroup
            v-if="supportedCurrencies.length"
            :label="$t('settings.exchange_rate.currency')"
            :content-loading="isFetchingInitialData"
            :error="
              v$.currencies.$error && v$.currencies.$errors[0].$message
            "
            :help-text="$t('settings.exchange_rate.currency_help_text')"
          >
            <BaseMultiselect
              v-model="currentExchangeRate.currencies"
              :content-loading="isFetchingInitialData"
              value-prop="code"
              mode="tags"
              searchable
              :options="supportedCurrencies"
              :invalid="v$.currencies.$error"
              label="code"
              track-by="code"
              open-direction="top"
            />
          </BaseInputGroup>

          <BaseSwitch
            v-model="currentExchangeRate.active"
            class="flex"
            :label-right="$t('settings.exchange_rate.active')"
          />
        </BaseInputGrid>

        <BaseInfoAlert
          v-if="
            currenciesAlreadyInUsed.length && currentExchangeRate.active
          "
          class="mt-5"
          :title="$t('settings.exchange_rate.currency_in_used')"
          :lists="[currenciesAlreadyInUsed.toString()]"
          :actions="['Remove']"
          @hide="dismiss"
          @Remove="removeUsedSelectedCurrencies"
        />
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          class="mr-3"
          variant="primary-outline"
          type="button"
          :disabled="isSaving"
          @click="closeExchangeRateModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isSaving"
          :disabled="isSaving || isFetchingCurrencies"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              name="ArrowDownOnSquareIcon"
              :class="slotProps.class"
            />
          </template>
          {{ isEdit ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
