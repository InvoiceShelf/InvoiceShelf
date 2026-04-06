import { client } from '../client'
import { API } from '../endpoints'

export interface Backup {
  path: string
  created_at: string
  size: string
}

export interface BackupListResponse {
  backups: Backup[]
  disks: string[]
  error?: string
  error_message?: string
}

export interface CreateBackupPayload {
  option: 'full' | 'only-db' | 'only-files'
  file_disk_id: number
}

export interface DeleteBackupParams {
  disk: string
  path: string
  file_disk_id?: number
}

export const backupService = {
  async list(params: {
    disk: string
    file_disk_id?: number
  }): Promise<BackupListResponse> {
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

  async download(params: {
    disk: string
    path: string
    file_disk_id?: number
  }): Promise<Blob> {
    const { data } = await client.get(API.DOWNLOAD_BACKUP, {
      params,
      responseType: 'blob',
    })
    return data
  },
}
