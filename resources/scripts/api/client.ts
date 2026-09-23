import axios, {
  type AxiosError,
  type AxiosInstance,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from 'axios'
import { API } from './endpoints'
import * as localStore from '@/scripts/utils/local-storage'
import { serverBaseUrl } from '@/scripts/config/runtime'

const client: AxiosInstance = axios.create({
  // A client is bearer-only and cross-origin: sending credentials would ask
  // the server for `Access-Control-Allow-Credentials`, which it refuses.
  withCredentials: !__INVOICESHELF_CLIENT__,
  headers: {
    common: {
      'X-Requested-With': 'XMLHttpRequest',
    },
  },
})

client.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  if (__INVOICESHELF_CLIENT__) {
    // Read per request rather than baked in at creation, so switching
    // servers does not need a new instance.
    config.baseURL = serverBaseUrl()
  }

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

// Collapses concurrent 401s into a single redirect. While a redirect
// to /login is in flight, subsequent 401s re-reject without any
// side-effects, avoiding the N parallel `router.push` thrash that
// parallel API fan-outs would otherwise produce.
let isRedirectingToLogin = false

// URLs exempt from the 401 → /login redirect. A 401 on these endpoints
// is a legit form/flow signal (bad credentials, already-stale CSRF),
// not a session expiry — consumers handle it via their own .catch().
const AUTH_EXEMPT_URLS: readonly string[] = [
  API.LOGIN,
  API.LOGOUT,
  API.CSRF_COOKIE,
]

function isAuthExemptRequest(url: string | undefined): boolean {
  if (!url) {
    return false
  }
  return AUTH_EXEMPT_URLS.some((exempt) => url.endsWith(exempt))
}

client.interceptors.response.use(
  (response: AxiosResponse) => response,
  async (error: AxiosError) => {
    const status = error.response?.status

    if (status !== 401) {
      return Promise.reject(error)
    }

    if (isAuthExemptRequest(error.config?.url)) {
      return Promise.reject(error)
    }

    if (isRedirectingToLogin) {
      return Promise.reject(error)
    }

    // Dynamic import to break the client → router → stores → client
    // circular dependency. Vite bundles this into the main chunk, so
    // it's effectively free at runtime.
    const { default: router } = await import('@/scripts/router')

    const currentRoute = router.currentRoute.value

    // Login form handles its own errors — don't self-redirect.
    if (currentRoute.name === 'login') {
      return Promise.reject(error)
    }

    // Installer uses a separate axios client, but belt-and-suspenders
    // in case any install-flow code path ends up on this one.
    if (typeof currentRoute.path === 'string' && currentRoute.path.startsWith('/installation')) {
      return Promise.reject(error)
    }

    // Customer portal has its own 401 handling in the router guard
    // (see router/guards.ts:handleCustomerPortalRoute).
    if (currentRoute.meta?.isCustomerPortal === true) {
      return Promise.reject(error)
    }

    isRedirectingToLogin = true

    // Clear stale auth state. Keep other keys (language, UI prefs).
    localStore.remove('auth.token')
    localStore.remove('selectedCompany')
    localStore.remove('isAdminMode')

    // Remember where the user was trying to go, so LoginView can
    // return them there after re-auth. The router's own path, which is
    // the only correct answer under hash history and the same answer as
    // the address bar on the web.
    const nextPath = currentRoute.fullPath

    try {
      await router.push({ name: 'login', query: { next: nextPath } })
    } finally {
      // Reset the guard on the next microtask so any still-pending
      // 401s from the same tick are swallowed but subsequent sessions
      // can redirect again.
      setTimeout(() => {
        isRedirectingToLogin = false
      }, 0)
    }

    return Promise.reject(error)
  },
)

export { client }
