import { client } from '../client'
import { API } from '../endpoints'
import type { ApiResponse, ListParams } from '../../types/api'

export interface Backup {
  id: number
  disk: string
  path: string
  created_at: string
  file_size: string
}

export interface CreateBackupPayload {
  option: 'full' | 'database' | 'files'
  selected_disk: string | null
}

export interface DeleteBackupParams {
  disk: string
  path?: string
  file_name?: string
}

export const backupService = {
  async list(params?: ListParams): Promise<ApiResponse<Backup[]>> {
    const { data } = await client.get(API.BACKUPS, { params })
    return data
  },

  async create(payload: CreateBackupPayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.BACKUPS, payload)
    return data
  },

  async delete(params: DeleteBackupParams): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.BACKUPS}/${params.disk}`, { params })
    return data
  },

  async download(params: { disk: string; path?: string; file_name?: string }): Promise<Blob> {
    const { data } = await client.get(API.DOWNLOAD_BACKUP, {
      params,
      responseType: 'blob',
    })
    return data
  },
}
