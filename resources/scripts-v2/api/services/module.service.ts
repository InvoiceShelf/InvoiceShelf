import { client } from '../client'
import { API } from '../endpoints'
import type { ApiResponse } from '@v2/types/api'

export interface Module {
  name: string
  slug: string
  description: string
  version: string
  enabled: boolean
  installed: boolean
  [key: string]: unknown
}

export interface ModuleCheckResponse {
  error?: string
  success?: boolean
}

export interface ModuleInstallPayload {
  module: string
  version: string
  api_token?: string
}

export const moduleService = {
  async list(): Promise<ApiResponse<Module[]>> {
    const { data } = await client.get(API.MODULES)
    return data
  },

  async get(module: string): Promise<Module> {
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
