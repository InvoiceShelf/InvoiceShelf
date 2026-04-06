import { client } from '../client'
import { API } from '../endpoints'
import type { ExchangeRateProvider, Currency } from '@v2/types/domain/currency'
import type { ApiResponse, ListParams } from '@v2/types/api'

export interface CreateExchangeRateProviderPayload {
  driver: string
  key: string
  active?: boolean
  currencies?: string[]
  driver_config?: Record<string, string>
}

// Normalized response types (what callers receive)
export interface ExchangeRateResponse {
  exchangeRate: number | null
}

export interface ActiveProviderResponse {
  hasActiveProvider: boolean
}

export interface SupportedCurrenciesResponse {
  supportedCurrencies: string[]
}

export interface UsedCurrenciesResponse {
  activeUsedCurrencies: string[]
  allUsedCurrencies: string[]
}

export interface BulkCurrenciesResponse {
  currencies: Array<Currency & { exchange_rate: number | null }>
}

export interface BulkUpdatePayload {
  currencies: Array<{
    id: number
    exchange_rate: number
  }>
}

export interface ConfigOption {
  key: string
  value: string
}

export interface ConfigDriversResponse {
  exchange_rate_drivers: ConfigOption[]
}

export interface ConfigServersResponse {
  currency_converter_servers: ConfigOption[]
}

export interface SupportedCurrenciesParams {
  driver: string
  key: string
  driver_config?: Record<string, string>
}

export const exchangeRateService = {
  // Providers CRUD
  async listProviders(params?: ListParams): Promise<ApiResponse<ExchangeRateProvider[]>> {
    const { data } = await client.get(API.EXCHANGE_RATE_PROVIDERS, { params })
    return data
  },

  async getProvider(id: number): Promise<ApiResponse<ExchangeRateProvider>> {
    const { data } = await client.get(`${API.EXCHANGE_RATE_PROVIDERS}/${id}`)
    return data
  },

  async createProvider(payload: CreateExchangeRateProviderPayload): Promise<ApiResponse<ExchangeRateProvider>> {
    const { data } = await client.post(API.EXCHANGE_RATE_PROVIDERS, payload)
    return data
  },

  async updateProvider(
    id: number,
    payload: Partial<CreateExchangeRateProviderPayload>,
  ): Promise<ApiResponse<ExchangeRateProvider>> {
    const { data } = await client.put(`${API.EXCHANGE_RATE_PROVIDERS}/${id}`, payload)
    return data
  },

  async deleteProvider(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.EXCHANGE_RATE_PROVIDERS}/${id}`)
    return data
  },

  // Exchange Rates
  // Backend returns { exchangeRate: [number] } or { error: string }
  async getRate(currencyId: number): Promise<ExchangeRateResponse> {
    const { data } = await client.get(`${API.CURRENCIES}/${currencyId}/exchange-rate`)
    const raw = data as Record<string, unknown>

    if (raw.exchangeRate && Array.isArray(raw.exchangeRate)) {
      return { exchangeRate: Number(raw.exchangeRate[0]) ?? null }
    }

    return { exchangeRate: null }
  },

  // Backend returns { success: true, message: "provider_active" } or { error: "no_active_provider" }
  async getActiveProvider(currencyId: number): Promise<ActiveProviderResponse> {
    const { data } = await client.get(`${API.CURRENCIES}/${currencyId}/active-provider`)
    const raw = data as Record<string, unknown>

    return { hasActiveProvider: raw.success === true }
  },

  // Currency lists
  async getSupportedCurrencies(params: SupportedCurrenciesParams): Promise<SupportedCurrenciesResponse> {
    const { data } = await client.get(API.SUPPORTED_CURRENCIES, { params })
    return data
  },

  // Backend returns { activeUsedCurrencies: string[], allUsedCurrencies: string[] }
  async getUsedCurrencies(params?: { provider_id?: number }): Promise<UsedCurrenciesResponse> {
    const { data } = await client.get(API.USED_CURRENCIES, { params })
    return data
  },

  async getBulkCurrencies(): Promise<BulkCurrenciesResponse> {
    const { data } = await client.get(API.CURRENCIES_USED)
    return data
  },

  async bulkUpdateExchangeRate(payload: BulkUpdatePayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.CURRENCIES_BULK_UPDATE, payload)
    return data
  },

  // Config
  // Backend returns { exchange_rate_drivers: Array<{ key, value }> }
  async getDrivers(): Promise<ConfigDriversResponse> {
    const { data } = await client.get(API.CONFIG, { params: { key: 'exchange_rate_drivers' } })
    return data
  },

  // Backend returns { currency_converter_servers: Array<{ key, value }> }
  async getCurrencyConverterServers(): Promise<ConfigServersResponse> {
    const { data } = await client.get(API.CONFIG, { params: { key: 'currency_converter_servers' } })
    return data
  },
}
