import axios, { type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'

const client: AxiosInstance = axios.create({
  withCredentials: true,
  headers: {
    common: {
      'X-Requested-With': 'XMLHttpRequest',
    },
  },
})

client.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const companyId = localStorage.getItem('selectedCompany')
  const authToken = localStorage.getItem('auth.token')
  const isAdminMode = localStorage.getItem('isAdminMode') === 'true'

  if (authToken) {
    config.headers.Authorization = authToken
  }

  if (companyId && !isAdminMode) {
    config.headers.company = companyId
  }

  return config
})

export { client }
