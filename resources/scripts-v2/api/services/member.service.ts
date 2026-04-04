import { client } from '../client'
import { API } from '../endpoints'
import type { User } from '../../types/domain/user'
import type { CompanyInvitation } from '../../types/domain/company'
import type { ApiResponse, PaginatedResponse, ListParams } from '../../types/api'

export interface MemberListParams extends ListParams {
  display_name?: string
}

export interface MemberListResponse {
  data: User[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface UpdateMemberPayload {
  name?: string
  email?: string
  phone?: string | null
  role?: string | null
  companies?: Array<{
    id: number
    role?: string
  }>
}

export interface InviteMemberPayload {
  email: string
  role?: string
}

export interface DeleteMembersPayload {
  users: number[]
}

export interface PendingInvitationsResponse {
  invitations: CompanyInvitation[]
}

export const memberService = {
  async list(params?: MemberListParams): Promise<MemberListResponse> {
    const { data } = await client.get(API.MEMBERS, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<User>> {
    const { data } = await client.get(`${API.MEMBERS}/${id}`)
    return data
  },

  async create(payload: UpdateMemberPayload): Promise<ApiResponse<User>> {
    const { data } = await client.post(API.MEMBERS, payload)
    return data
  },

  async update(id: number, payload: UpdateMemberPayload): Promise<ApiResponse<User>> {
    const { data } = await client.put(`${API.MEMBERS}/${id}`, payload)
    return data
  },

  async delete(payload: DeleteMembersPayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.MEMBERS_DELETE, payload)
    return data
  },

  // Company Invitations (send invitations)
  async fetchPendingInvitations(): Promise<PendingInvitationsResponse> {
    const { data } = await client.get(API.COMPANY_INVITATIONS)
    return data
  },

  async invite(payload: InviteMemberPayload): Promise<ApiResponse<CompanyInvitation>> {
    const { data } = await client.post(API.COMPANY_INVITATIONS, payload)
    return data
  },

  async cancelInvitation(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.COMPANY_INVITATIONS}/${id}`)
    return data
  },

  // User-scoped invitation responses
  async fetchUserPendingInvitations(): Promise<PendingInvitationsResponse> {
    const { data } = await client.get(API.INVITATIONS_PENDING)
    return data
  },

  async acceptInvitation(token: string): Promise<{ success: boolean }> {
    const { data } = await client.post(`${API.INVITATIONS}/${token}/accept`)
    return data
  },

  async declineInvitation(token: string): Promise<{ success: boolean }> {
    const { data } = await client.post(`${API.INVITATIONS}/${token}/decline`)
    return data
  },
}
