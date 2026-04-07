<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import useVuelidate from '@vuelidate/core'
import { required, helpers, requiredIf, url } from '@vuelidate/validators'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { exchangeRateService } from '@/scripts/api/services/exchange-rate.service'
import { useDebounceFn } from '@vueuse/core'

interface DriverOption {
  key: string
  value: string
}

interface ServerOption {
  value: string
}

interface ExchangeRateForm {
  id: number | null
  driver: string
  key: string | null
  active: boolean
  currencies: string[]
}

interface CurrencyConverterForm {
  type: string
  url: string
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
const serverOptions = ref<ServerOption[]>([])
const drivers = ref<DriverOption[]>([])

const currentExchangeRate = ref<ExchangeRateForm>({
  id: null,
  driver: 'currency_converter',
  key: null,
  active: true,
  currencies: [],
})

const currencyConverter = ref<CurrencyConverterForm>({
  type: '',
  url: '',
})

const modalActive = computed<boolean>(
  () =>
    modalStore.active &&
    modalStore.componentName === 'ExchangeRateProviderModal'
)

const isCurrencyConverter = computed<boolean>(
  () => currentExchangeRate.value.driver === 'currency_converter'
)

const isDedicatedServer = computed<boolean>(
  () => currencyConverter.value.type === 'DEDICATED'
)

const driverSite = computed<string>(() => {
  switch (currentExchangeRate.value.driver) {
    case 'currency_converter':
      return 'https://www.currencyconverterapi.com'
    case 'currency_freak':
      return 'https://currencyfreaks.com'
    case 'currency_layer':
      return 'https://currencylayer.com'
    case 'open_exchange_rate':
      return 'https://openexchangerates.org'
    default:
      return ''
  }
})

const driversLists = computed(() =>
  drivers.value.map((item) => ({
    ...item,
    key: t(item.key),
  }))
)

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
  converterType: {
    required: helpers.withMessage(
      t('validation.required'),
      requiredIf(isCurrencyConverter)
    ),
  },
  converterUrl: {
    required: helpers.withMessage(
      t('validation.required'),
      requiredIf(isDedicatedServer)
    ),
    url: helpers.withMessage(t('validation.invalid_url'), url),
  },
}))

const v$ = useVuelidate(rules, currentExchangeRate)

watch(isCurrencyConverter, (newVal) => {
  if (newVal) {
    fetchServers()
  }
}, { immediate: true })

const fetchCurrenciesDebounced = useDebounceFn(() => {
  fetchCurrencies()
}, 500)

watch(() => currentExchangeRate.value.key, (newVal) => {
  if (newVal) fetchCurrenciesDebounced()
})

watch(() => currencyConverter.value.type, (newVal) => {
  if (newVal) fetchCurrenciesDebounced()
})

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
}

function resetModalData(): void {
  supportedCurrencies.value = []
  currentExchangeRate.value = {
    id: null,
    driver: 'currency_converter',
    key: null,
    active: true,
    currencies: [],
  }
  currencyConverter.value = { type: '', url: '' }
  currenciesAlreadyInUsed.value = []
  isEdit.value = false
}

async function fetchInitialData(): Promise<void> {
  isFetchingInitialData.value = true

  const driversRes = await exchangeRateService.getDrivers()
  if (driversRes.exchange_rate_drivers) {
    drivers.value = (driversRes.exchange_rate_drivers as unknown as DriverOption[])
  }

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
    }
  } else {
    currentExchangeRate.value.driver = 'currency_converter'
  }

  isFetchingInitialData.value = false
}

async function fetchServers(): Promise<void> {
  const res = await exchangeRateService.getCurrencyConverterServers()
  serverOptions.value = (res as Record<string, ServerOption[]>).currency_converter_servers ?? []
  currencyConverter.value.type = 'FREE'
}

async function fetchCurrencies(): Promise<void> {
  const { driver, key } = currentExchangeRate.value
  if (!driver || !key) return

  if (isCurrencyConverter.value && !currencyConverter.value.type) return

  isFetchingCurrencies.value = true
  try {
    const driverConfig: Record<string, string> = {}
    if (currencyConverter.value.type) {
      driverConfig.type = currencyConverter.value.type
    }
    if (currencyConverter.value.url) {
      driverConfig.url = currencyConverter.value.url
    }
    const res = await exchangeRateService.getSupportedCurrencies({
      driver,
      key,
      driver_config: Object.keys(driverConfig).length ? driverConfig : undefined,
    })
    supportedCurrencies.value = res.supportedCurrencies ?? []
  } finally {
    isFetchingCurrencies.value = false
  }
}

async function submitExchangeRate(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  const data: Record<string, unknown> = {
    ...currentExchangeRate.value,
  }

  if (isCurrencyConverter.value) {
    data.driver_config = { ...currencyConverter.value }
    if (!isDedicatedServer.value) {
      (data.driver_config as CurrencyConverterForm).url = ''
    }
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
              label="key"
              :searchable="true"
              :invalid="v$.driver.$error"
              track-by="key"
              @update:model-value="resetCurrency"
            />
          </BaseInputGroup>

          <BaseInputGroup
            v-if="isCurrencyConverter"
            required
            :label="$t('settings.exchange_rate.server')"
            :content-loading="isFetchingInitialData"
          >
            <BaseMultiselect
              v-model="currencyConverter.type"
              :content-loading="isFetchingInitialData"
              value-prop="value"
              searchable
              :options="serverOptions"
              label="value"
              track-by="value"
              @update:model-value="resetCurrency"
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

          <BaseInputGroup
            v-if="isDedicatedServer"
            :label="$t('settings.exchange_rate.url')"
            :content-loading="isFetchingInitialData"
          >
            <BaseInput
              v-model="currencyConverter.url"
              :content-loading="isFetchingInitialData"
              type="url"
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
