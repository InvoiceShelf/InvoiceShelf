import { client } from '../client'
import { API } from '../endpoints'
import type { Country } from '../../types/domain/customer'
import type { Currency } from '../../types/domain/currency'

export interface DateFormat {
  display_date: string
  carbon_format_value: string
  moment_format_value: string
}

export interface TimeFormat {
  display_time: string
  carbon_format_value: string
  moment_format_value: string
}

export interface ConfigResponse {
  [key: string]: unknown
}

export interface GlobalSettingsPayload {
  settings: Record<string, string | number | boolean | null>
}

export interface NumberPlaceholdersParams {
  key: string
}

export interface NumberPlaceholder {
  description: string
  value: string
}

export const settingService = {
  // Global Settings (admin-level)
  async getGlobalSettings(): Promise<Record<string, string>> {
    const { data } = await client.get(API.SETTINGS)
    return data
  },

  async updateGlobalSettings(payload: GlobalSettingsPayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.SETTINGS, payload)
    return data
  },

  // Config
  async getConfig(params?: Record<string, string>): Promise<ConfigResponse> {
    const { data } = await client.get(API.CONFIG, { params })
    return data
  },

  // Reference Data
  async getCountries(): Promise<{ data: Country[] }> {
    const { data } = await client.get(API.COUNTRIES)
    return data
  },

  async getCurrencies(): Promise<{ data: Currency[] }> {
    const { data } = await client.get(API.CURRENCIES)
    return data
  },

  async getTimezones(): Promise<{ time_zones: string[] }> {
    const { data } = await client.get(API.TIMEZONES)
    return data
  },

  async getDateFormats(): Promise<{ date_formats: DateFormat[] }> {
    const { data } = await client.get(API.DATE_FORMATS)
    return data
  },

  async getTimeFormats(): Promise<{ time_formats: TimeFormat[] }> {
    const { data } = await client.get(API.TIME_FORMATS)
    return data
  },

  // Serial Numbers
  async getNextNumber(params: { key: string }): Promise<{ nextNumber: string }> {
    const { data } = await client.get(API.NEXT_NUMBER, { params })
    return data
  },

  async getNumberPlaceholders(params: NumberPlaceholdersParams): Promise<{ placeholders: NumberPlaceholder[] }> {
    const { data } = await client.get(API.NUMBER_PLACEHOLDERS, { params })
    return data
  },

  // Search
  async search(params: { search: string }): Promise<{
    users: { data: unknown[] }
    customers: { data: unknown[] }
  }> {
    const { data } = await client.get(API.SEARCH, { params })
    return data
  },

  async searchUsers(params: { search: string }): Promise<{ data: unknown[] }> {
    const { data } = await client.get(API.SEARCH_USERS, { params })
    return data
  },

  // App Version
  async getAppVersion(): Promise<{ version: string }> {
    const { data } = await client.get(API.APP_VERSION)
    return data
  },
}
