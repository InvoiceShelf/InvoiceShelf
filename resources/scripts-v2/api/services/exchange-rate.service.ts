import { client } from '../client'
import { API } from '../endpoints'
import type { ExchangeRateProvider, Currency } from '../../types/domain/currency'
import type { ApiResponse, ListParams } from '../../types/api'

export interface CreateExchangeRateProviderPayload {
  driver: string
  key: string
  active?: boolean
  currencies?: string[]
}

export interface ExchangeRateResponse {
  exchange_rate: number
}

export interface ActiveProviderResponse {
  has_active_provider: boolean
  exchange_rate: number | null
}

export interface SupportedCurrenciesResponse {
  supportedCurrencies: string[]
}

export interface UsedCurrenciesResponse {
  activeUsedCurrencies: Currency[]
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

export interface ConfigDriversResponse {
  exchange_rate_drivers: string[]
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
  async getRate(currencyId: number): Promise<ExchangeRateResponse> {
    const { data } = await client.get(`${API.CURRENCIES}/${currencyId}/exchange-rate`)
    return data
  },

  async getActiveProvider(currencyId: number): Promise<ActiveProviderResponse> {
    const { data } = await client.get(`${API.CURRENCIES}/${currencyId}/active-provider`)
    return data
  },

  // Currency lists
  async getSupportedCurrencies(): Promise<SupportedCurrenciesResponse> {
    const { data } = await client.get(API.SUPPORTED_CURRENCIES)
    return data
  },

  async getUsedCurrencies(): Promise<UsedCurrenciesResponse> {
    const { data } = await client.get(API.USED_CURRENCIES)
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
  async getDrivers(): Promise<ConfigDriversResponse> {
    const { data } = await client.get(API.CONFIG, { params: { key: 'exchange_rate_drivers' } })
    return data
  },

  async getCurrencyConverterServers(): Promise<Record<string, unknown>> {
    const { data } = await client.get(API.CONFIG, { params: { key: 'currency_converter_servers' } })
    return data
  },
}
