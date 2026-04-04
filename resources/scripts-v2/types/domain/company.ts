import type { Address } from './user'
import type { Role } from './role'
import type { User } from './user'

export interface Company {
  id: number
  name: string
  vat_id: string | null
  tax_id: string | null
  logo: string | null
  logo_path: string | null
  unique_hash: string
  owner_id: number
  slug: string
  created_at: string
  updated_at: string
  address?: Address
  owner?: User
  roles: Role[]
}

export interface CompanySetting {
  id: number
  company_id: number
  option: string
  value: string | null
}

export interface CompanyInvitation {
  id: number
  company_id: number
  email: string
  token: string
  status: CompanyInvitationStatus
  expires_at: string
  created_at: string
  company?: Company
  role?: Role
  invited_by?: User
}

export enum CompanyInvitationStatus {
  PENDING = 'pending',
  ACCEPTED = 'accepted',
  DECLINED = 'declined',
  EXPIRED = 'expired',
}
