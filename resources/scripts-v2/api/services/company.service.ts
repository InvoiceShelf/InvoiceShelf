import { client } from '../client'
import { API } from '../endpoints'
import type { Company } from '../../types/domain/company'
import type { ApiResponse } from '../../types/api'

export interface UpdateCompanyPayload {
  name: string
  vat_id?: string | null
  tax_id?: string | null
  phone?: string | null
  address?: {
    address_street_1?: string | null
    address_street_2?: string | null
    city?: string | null
    state?: string | null
    country_id?: number | null
    zip?: string | null
    phone?: string | null
  }
}

export interface CompanySettingsPayload {
  settings: Record<string, string | number | boolean | null>
}

export interface CreateCompanyPayload {
  name: string
  currency?: number
  address?: Record<string, unknown>
}

export const companyService = {
  async update(payload: UpdateCompanyPayload): Promise<ApiResponse<Company>> {
    const { data } = await client.put(API.COMPANY, payload)
    return data
  },

  async uploadLogo(payload: FormData): Promise<ApiResponse<Company>> {
    const { data } = await client.post(API.COMPANY_UPLOAD_LOGO, payload)
    return data
  },

  async getSettings(settings?: string[]): Promise<Record<string, string>> {
    const { data } = await client.get(API.COMPANY_SETTINGS, {
      params: { settings },
    })
    return data
  },

  async updateSettings(payload: CompanySettingsPayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.COMPANY_SETTINGS, payload)
    return data
  },

  async hasTransactions(): Promise<{ has_transactions: boolean }> {
    const { data } = await client.get(API.COMPANY_HAS_TRANSACTIONS)
    return data
  },

  async create(payload: CreateCompanyPayload): Promise<ApiResponse<Company>> {
    const { data } = await client.post(API.COMPANIES, payload)
    return data
  },

  async listUserCompanies(): Promise<ApiResponse<Company[]>> {
    const { data } = await client.get(API.COMPANIES)
    return data
  },

  async delete(payload: { id: number }): Promise<{ success: boolean }> {
    const { data } = await client.post(API.COMPANIES_DELETE, payload)
    return data
  },

  async transferOwnership(userId: number): Promise<{ success: boolean }> {
    const { data } = await client.post(`${API.TRANSFER_OWNERSHIP}/${userId}`)
    return data
  },

  // Company Mail Configuration
  async getMailDefaultConfig(): Promise<Record<string, unknown>> {
    const { data } = await client.get(API.COMPANY_MAIL_DEFAULT_CONFIG)
    return data
  },

  async getMailConfig(): Promise<Record<string, unknown>> {
    const { data } = await client.get(API.COMPANY_MAIL_CONFIG)
    return data
  },

  async saveMailConfig(payload: Record<string, unknown>): Promise<{ success: boolean }> {
    const { data } = await client.post(API.COMPANY_MAIL_CONFIG, payload)
    return data
  },

  async testMailConfig(payload: Record<string, unknown>): Promise<{ success: boolean }> {
    const { data } = await client.post(API.COMPANY_MAIL_TEST, payload)
    return data
  },
}
