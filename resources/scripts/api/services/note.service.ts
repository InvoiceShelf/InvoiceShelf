import { client } from '../client'
import { API } from '../endpoints'
import type { Note } from '@/scripts/types/domain/note'
import type { ApiResponse, ListParams } from '@/scripts/types/api'

export interface CreateNotePayload {
  type: string
  name: string
  notes: string
  is_default?: boolean
}

export const noteService = {
  async list(params?: ListParams): Promise<ApiResponse<Note[]>> {
    const { data } = await client.get(API.NOTES, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<Note>> {
    const { data } = await client.get(`${API.NOTES}/${id}`)
    return data
  },

  async create(payload: CreateNotePayload): Promise<Note> {
    const { data } = await client.post(API.NOTES, payload)
    return data
  },

  async update(id: number, payload: Partial<CreateNotePayload>): Promise<ApiResponse<Note>> {
    const { data } = await client.put(`${API.NOTES}/${id}`, payload)
    return data
  },

  async delete(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.NOTES}/${id}`)
    return data
  },
}
