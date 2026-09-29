import { client } from '../client'
import { API } from '../endpoints'
import type { ApiResponse } from '@/scripts/types/api'
import type {
  CompanyNotificationDefault,
  InboxNotice,
  InboxPage,
  NotificationPreference,
} from '@/scripts/types/domain/inbox'

export interface InboxListParams {
  page?: number
  limit?: number
  unread?: boolean
}

export const inboxService = {
  async list(params: InboxListParams = {}): Promise<InboxPage> {
    const { data } = await client.get<InboxPage>(API.NOTIFICATIONS, {
      params: { ...params, unread: params.unread ? 1 : undefined },
    })
    return data
  },

  async unreadCount(): Promise<number> {
    const { data } = await client.get<{ unread_count: number }>(
      `${API.NOTIFICATIONS}/unread-count`,
    )
    return data.unread_count
  },

  async markRead(id: string): Promise<InboxNotice> {
    const { data } = await client.post<ApiResponse<InboxNotice>>(
      `${API.NOTIFICATIONS}/${id}/read`,
    )
    return data.data
  },

  async markUnread(id: string): Promise<InboxNotice> {
    const { data } = await client.post<ApiResponse<InboxNotice>>(
      `${API.NOTIFICATIONS}/${id}/unread`,
    )
    return data.data
  },

  async markAllRead(): Promise<void> {
    await client.post(`${API.NOTIFICATIONS}/read-all`)
  },

  async remove(id: string): Promise<void> {
    await client.delete(`${API.NOTIFICATIONS}/${id}`)
  },

  async preferences(): Promise<NotificationPreference[]> {
    const { data } = await client.get<ApiResponse<NotificationPreference[]>>(
      API.NOTIFICATION_PREFERENCES,
    )
    return data.data
  },

  /** A null channel goes back to the company's default. */
  async updatePreferences(
    preferences: Record<string, { bell?: boolean | null; mail?: boolean | null }>,
  ): Promise<NotificationPreference[]> {
    const { data } = await client.put<ApiResponse<NotificationPreference[]>>(
      API.NOTIFICATION_PREFERENCES,
      { preferences },
    )
    return data.data
  },

  async companyDefaults(): Promise<CompanyNotificationDefault[]> {
    const { data } = await client.get<ApiResponse<CompanyNotificationDefault[]>>(
      API.COMPANY_NOTIFICATION_DEFAULTS,
    )
    return data.data
  },

  async updateCompanyDefaults(
    defaults: Record<string, { enabled?: boolean; bell?: boolean; mail?: boolean }>,
  ): Promise<CompanyNotificationDefault[]> {
    const { data } = await client.put<ApiResponse<CompanyNotificationDefault[]>>(
      API.COMPANY_NOTIFICATION_DEFAULTS,
      { defaults },
    )
    return data.data
  },
}
