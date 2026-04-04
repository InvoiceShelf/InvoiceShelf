import { client } from '../client'
import { API } from '../endpoints'
import type { User, UserSetting } from '@v2/types/domain/user'
import type { Company } from '@v2/types/domain/company'
import type { Currency } from '@v2/types/domain/currency'
import type { Ability } from '@v2/types/domain/role'

export interface MenuItem {
  title: string
  name: string
  route: string
  icon: string
  group: string
  ability?: string
}

export interface BootstrapResponse {
  current_user: User
  current_user_settings: Record<string, string>
  current_user_abilities: Ability[]
  companies: Company[]
  current_company: Company | null
  current_company_settings: Record<string, string>
  current_company_currency: Currency | null
  main_menu: MenuItem[]
  setting_menu: MenuItem[]
  config: Record<string, unknown>
  global_settings: Record<string, string>
  modules: string[]
  pending_invitations?: Array<{
    token: string
    company_name: string
    invited_by: string
    email: string
  }>
}

export interface CurrentCompanyResponse {
  data: Company
}

export const bootstrapService = {
  async bootstrap(adminMode?: boolean): Promise<BootstrapResponse> {
    const url = adminMode ? `${API.BOOTSTRAP}?admin_mode=1` : API.BOOTSTRAP
    const { data } = await client.get(url)
    return data
  },

  async getCurrentCompany(): Promise<CurrentCompanyResponse> {
    const { data } = await client.get(API.CURRENT_COMPANY)
    return data
  },
}
