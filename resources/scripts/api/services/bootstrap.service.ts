import { client } from '../client'
import { API } from '../endpoints'
import type { User, UserSetting } from '@/scripts/types/domain/user'
import type { Company } from '@/scripts/types/domain/company'
import type { Currency } from '@/scripts/types/domain/currency'
import type { Ability } from '@/scripts/types/domain/role'

export interface MenuItem {
  title: string
  name: string
  link: string
  icon: string
  group: string
  group_label?: string
  priority?: number
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
  user_menu?: Array<{ title: string; link: string; icon: string; priority: number; name: string }>
  admin_mode?: boolean
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
