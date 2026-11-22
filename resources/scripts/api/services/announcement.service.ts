import { client } from '../client'
import { API } from '../endpoints'
import type { ApiResponse } from '@/scripts/types/api'
import type { Announcement, AnnouncementPayload } from '@/scripts/types/domain/announcement'

export const announcementService = {
  /** Put an announcement away for the signed-in user. */
  async dismiss(id: number): Promise<void> {
    await client.post(`${API.ANNOUNCEMENTS}/${id}/dismiss`)
  },

  async list(): Promise<Announcement[]> {
    const { data } = await client.get<ApiResponse<Announcement[]>>(API.SUPER_ADMIN_ANNOUNCEMENTS)
    return data.data
  },

  async create(payload: AnnouncementPayload): Promise<Announcement> {
    const { data } = await client.post<ApiResponse<Announcement>>(API.SUPER_ADMIN_ANNOUNCEMENTS, payload)
    return data.data
  },

  async update(id: number, payload: AnnouncementPayload): Promise<Announcement> {
    const { data } = await client.put<ApiResponse<Announcement>>(
      `${API.SUPER_ADMIN_ANNOUNCEMENTS}/${id}`,
      payload,
    )
    return data.data
  },

  async setHidden(id: number, hidden: boolean): Promise<Announcement> {
    const { data } = await client.patch<ApiResponse<Announcement>>(
      `${API.SUPER_ADMIN_ANNOUNCEMENTS}/${id}/visibility`,
      { hidden },
    )
    return data.data
  },

  async remove(id: number): Promise<void> {
    await client.delete(`${API.SUPER_ADMIN_ANNOUNCEMENTS}/${id}`)
  },
}
