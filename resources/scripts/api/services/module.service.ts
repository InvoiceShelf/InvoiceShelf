import { client } from '../client'
import { API } from '../endpoints'
import type { ApiResponse } from '@/scripts/types/api'
import type { Module } from '@/scripts/types/domain/module'

export interface ModuleCheckResponse {
  error?: string
  success?: boolean
  authenticated?: boolean
  premium?: boolean
}

export interface ModuleDetailMeta {
  modules: Module[]
}

export interface ModuleInstallPayload {
  slug: string
  module_name: string
  version: string
  checksum_sha256?: string | null
  path?: string
}

export interface ModuleDetailResponse {
  data: Module
  meta: ModuleDetailMeta
}

export const moduleService = {
  async list(): Promise<ApiResponse<Module[]>> {
    const { data } = await client.get(API.MODULES)
    return data
  },

  async get(module: string): Promise<ModuleDetailResponse> {
    const { data } = await client.get(`${API.MODULES}/${module}`)
    return data
  },

  async checkToken(apiToken: string): Promise<ModuleCheckResponse> {
    const { data } = await client.get(`${API.MODULES_CHECK}?api_token=${apiToken}`)
    return data
  },

  async enable(module: string): Promise<{ success: boolean }> {
    const { data } = await client.post(`${API.MODULES}/${module}/enable`)
    return data
  },

  async disable(module: string): Promise<{ success: boolean }> {
    const { data } = await client.post(`${API.MODULES}/${module}/disable`)
    return data
  },

  // Installation flow
  async download(payload: ModuleInstallPayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.MODULES_DOWNLOAD, payload)
    return data
  },

  async upload(payload: FormData): Promise<{ success: boolean }> {
    const { data } = await client.post(API.MODULES_UPLOAD, payload)
    return data
  },

  async unzip(payload: ModuleInstallPayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.MODULES_UNZIP, payload)
    return data
  },

  async copy(payload: ModuleInstallPayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.MODULES_COPY, payload)
    return data
  },

  async complete(payload: ModuleInstallPayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.MODULES_COMPLETE, payload)
    return data
  },
}
