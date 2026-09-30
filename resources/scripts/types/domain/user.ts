import type { CustomFieldValue } from './custom-field'
import type { Currency } from './currency'
import type { Company } from './company'
import type { Role, RolePreset } from './role'
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
  /** Readable titles of direct and global roles, when supplied by Administration. */
  role_labels?: string[]
  global_role_keys?: string[]
  global_roles?: RolePreset[]
  restricted_company_ids?: number[]
  restricted_companies?: Company[]
  formatted_created_at: string
  currency?: Currency
  companies?: Company[]
  /** Answers to the user's own custom fields, when they have any. */
  fields?: CustomFieldValue[]
}

export interface UserSetting {
  id: number
  key: string
  value: string | null
  user_id: number
}
