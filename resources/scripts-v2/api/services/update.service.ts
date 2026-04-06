import { client } from '../client'
import { API } from '../endpoints'

export interface UpdateRelease {
  version: string
  description?: string | null
  changelog?: string | null
  extensions?: Record<string, boolean>
  min_php_version?: string | null
  deleted_files?: string | string[] | null
}

export interface CheckUpdateResponse {
  success?: boolean
  release: UpdateRelease | null
  is_minor?: boolean
}

export interface UpdateDownloadResponse {
  success: boolean
  path?: string | boolean | Record<string, unknown> | null
}

export interface UpdateStepResponse {
  success: boolean
  path?: string | boolean | Record<string, unknown> | null
  error?: string | boolean
  data?: Record<string, unknown>
}

export interface FinishUpdatePayload {
  installed: string
  version: string
}

export const updateService = {
  async check(channel: 'stable' | 'insider' = 'stable'): Promise<CheckUpdateResponse> {
    const { data } = await client.get(API.CHECK_UPDATE, {
      params: { channel },
    })

    return data
  },

  async download(payload: { version: string }): Promise<UpdateDownloadResponse> {
    const { data } = await client.post(API.UPDATE_DOWNLOAD, payload)
    return data
  },

  async unzip(payload: { path: string }): Promise<UpdateStepResponse> {
    const { data } = await client.post(API.UPDATE_UNZIP, payload)
    return data
  },

  async copy(payload: { path: string }): Promise<UpdateStepResponse> {
    const { data } = await client.post(API.UPDATE_COPY, payload)
    return data
  },

  async delete(payload: { deleted_files?: string | string[] | null }): Promise<UpdateStepResponse> {
    const { data } = await client.post(API.UPDATE_DELETE, payload)
    return data
  },

  async migrate(): Promise<UpdateStepResponse> {
    const { data } = await client.post(API.UPDATE_MIGRATE)
    return data
  },

  async finish(payload: FinishUpdatePayload): Promise<UpdateStepResponse> {
    const { data } = await client.post(API.UPDATE_FINISH, payload)
    return data
  },
}
