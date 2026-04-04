import { client } from '../client'
import { API } from '../endpoints'
import type { ApiResponse, ListParams } from '../../types/api'

export interface Disk {
  id: number
  name: string
  driver: string
  set_as_default: boolean
  credentials: Record<string, string>
  created_at: string
  updated_at: string
}

export interface DiskDriversResponse {
  drivers: string[]
  [key: string]: unknown
}

export interface CreateDiskPayload {
  name: string
  selected_driver: string
  // S3/S3-compat/DOSpaces fields
  key?: string
  secret?: string
  region?: string
  bucket?: string
  root?: string
  endpoint?: string
  // Dropbox fields
  token?: string
  app?: string
}

export const diskService = {
  async list(params?: ListParams): Promise<ApiResponse<Disk[]>> {
    const { data } = await client.get(API.DISKS, { params })
    return data
  },

  async get(disk: string): Promise<Record<string, unknown>> {
    const { data } = await client.get(`${API.DISKS}/${disk}`)
    return data
  },

  async create(payload: CreateDiskPayload): Promise<Disk> {
    const { data } = await client.post(API.DISKS, payload)
    return data
  },

  async update(id: number, payload: Partial<CreateDiskPayload>): Promise<ApiResponse<Disk>> {
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
