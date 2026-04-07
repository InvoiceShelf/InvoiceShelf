import type { Currency } from './currency'
import type { Company } from './company'
import type { Role } from './role'
import type { Country } from './customer'

export interface Address {
  id: number
  name: string | null
  address_street_1: string | null
  address_street_2: string | null
  city: string | null
  state: string | null
  country_id: number | null
  zip: string | null
  phone: string | null
  fax: string | null
  type: AddressType
  user_id: number | null
  company_id: number | null
  customer_id: number | null
  country?: Country
  user?: User
}

export enum AddressType {
  BILLING = 'billing',
  SHIPPING = 'shipping',
}

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  role: string | null
  contact_name: string | null
  company_name: string | null
  website: string | null
  enable_portal: boolean | null
  currency_id: number | null
  facebook_id: string | null
  google_id: string | null
  github_id: string | null
  created_at: string
  updated_at: string
  avatar: string | number
  is_owner: boolean
  is_super_admin: boolean
  roles: Role[]
  formatted_created_at: string
  currency?: Currency
  companies?: Company[]
}

export interface UserSetting {
  id: number
  key: string
  value: string | null
  user_id: number
}
