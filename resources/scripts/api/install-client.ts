import axios, { type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'
import { LS_KEYS } from '@/scripts/config/constants'
import * as localStore from '@/scripts/utils/local-storage'

export const INSTALL_WIZARD_HEADER = 'X-Install-Wizard'

const installClient: AxiosInstance = axios.create({
  withCredentials: true,
  headers: {
    common: {
      'X-Requested-With': 'XMLHttpRequest',
      [INSTALL_WIZARD_HEADER]: '1',
    },
  },
})

installClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const authToken = localStore.get<string>(LS_KEYS.INSTALL_AUTH_TOKEN)
  const companyId = localStore.get<string | number>(LS_KEYS.INSTALL_SELECTED_COMPANY)

  config.headers[INSTALL_WIZARD_HEADER] = '1'

  if (authToken) {
    config.headers.Authorization = authToken
  }

  if (companyId !== null && companyId !== undefined && String(companyId) !== '') {
    config.headers.company = String(companyId)
  }

  return config
})

export { installClient }
