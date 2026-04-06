import { client } from '../client'
import { API } from '../endpoints'
import type { ApiResponse, ListParams, PaginatedResponse } from '@v2/types/api'

export type DiskDriverValue =
  | 'local'
  | 's3'
  | 's3compat'
  | 'doSpaces'
  | 'dropbox'

export interface Disk {
  id: number
  name: string
  type: string
  driver: DiskDriverValue
  set_as_default: boolean
  credentials: Record<string, string> | string | null
  company_id?: number | null
}

export interface DiskDriversResponse {
  drivers: Array<{
    name: string
    value: DiskDriverValue
  }>
  default: DiskDriverValue | string
}

export interface CreateDiskPayload {
  name: string
  driver: DiskDriverValue
  credentials?: Record<string, string> | string
  set_as_default?: boolean
}

export const diskService = {
  async list(params?: ListParams): Promise<PaginatedResponse<Disk>> {
    const { data } = await client.get(API.DISKS, { params })
    return data
  },

  async get(disk: DiskDriverValue): Promise<Record<string, string>> {
    const { data } = await client.get(`${API.DISKS}/${disk}`)
    return data
  },

  async create(payload: CreateDiskPayload): Promise<ApiResponse<Disk>> {
    const { data } = await client.post(API.DISKS, payload)
    return data
  },

  async update(
    id: number,
    payload: Partial<CreateDiskPayload>
  ): Promise<ApiResponse<Disk>> {
    const { data } = await client.put(`${API.DISKS}/${id}`, payload)
    return data
  },

  async delete(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.DISKS}/${id}`)
    return data
  },

  async getDrivers(): Promise<DiskDriversResponse> {
    const { data } = await client.get(API.DISK_DRIVERS)
    return data
  },
}
