import { defineStore } from 'pinia'
import { ref } from 'vue'
import { authService } from '@/scripts/api/services/auth.service'
import type { LoginPayload, ForgotPasswordPayload, ResetPasswordPayload } from '@/scripts/api/services/auth.service'
import { useNotificationStore } from './notification.store'
import { handleApiError } from '../utils/error-handling'
import * as localStore from '../utils/local-storage'
import { platform } from '../platform'
import { LS_KEYS } from '../config/constants'

export interface LoginData {
  email: string
  password: string
  remember: boolean
}

export interface ForgotPasswordData {
  email: string
}

export interface ResetPasswordData {
  email: string
  password: string
  password_confirmation: string
  token: string
}

export type SsoSignInFailure = 'cancelled' | 'no_account' | 'failed'

/** Why a sign-in with SSO on a client did not end with a token. */
export class SsoSignInError extends Error {
  readonly reason: SsoSignInFailure

  constructor(reason: SsoSignInFailure) {
    super(`Sign-in with SSO: ${reason}`)
    this.reason = reason
  }
}

export const useAuthStore = defineStore('auth', () => {
  // State
  const loginData = ref<LoginData>({
    email: '',
    password: '',
    remember: false,
  })

  const forgotPasswordData = ref<ForgotPasswordData>({
    email: '',
  })

  const resetPasswordData = ref<ResetPasswordData>({
    email: '',
    password: '',
    password_confirmation: '',
    token: '',
  })

  // Actions
  async function login(data: LoginPayload): Promise<void> {
    try {
      if (__INVOICESHELF_CLIENT__) {
        // No cookie jar and no CSRF round trip in a client: trade the
        // credentials for a token named after the device holding it.
        const { token } = await authService.loginWithToken({
          username: data.email,
          password: data.password,
          device_name: await platform.deviceName(),
        })

        // Stored with the scheme, because that is what the request
        // interceptor puts in the Authorization header verbatim.
        localStore.set(LS_KEYS.AUTH_TOKEN, `Bearer ${token}`)
      } else {
        await authService.login(data)
      }

      setTimeout(() => {
        loginData.value.email = ''
        loginData.value.password = ''
      }, 1000)
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  /**
   * Sign in through the server's SSO, for a client: start a hand-off, sign in
   * in a browser the server sends back to the app's link, and trade the code
   * it brings for a token named after this device. Rejects with an
   * SsoSignInError when it ends without one.
   */
  async function loginWithSso(): Promise<void> {
    const handoff = await authService.startSsoHandoff({
      device_name: await platform.deviceName(),
    })
    const link = await platform.openForAppLink(handoff.url, handoff.app_link)

    if (link === null) {
      throw new SsoSignInError('cancelled')
    }

    const answer = new URL(link).searchParams
    const code = answer.get('code')

    if (answer.get('handoff') !== handoff.id || code === null) {
      throw new SsoSignInError(answer.get('error') === 'no_account' ? 'no_account' : 'failed')
    }

    try {
      const { token } = await authService.finishSsoHandoff(handoff.id, {
        secret: handoff.secret,
        code,
      })

      localStore.set(LS_KEYS.AUTH_TOKEN, `Bearer ${token}`)
    } catch {
      throw new SsoSignInError('failed')
    }
  }

  async function logout(): Promise<void> {
    const notificationStore = useNotificationStore()

    try {
      if (__INVOICESHELF_CLIENT__) {
        await authService.logoutWithToken()
      } else {
        await authService.logout()
      }

      notificationStore.showNotification({
        type: 'success',
        message: 'Logged out successfully.',
      })

      localStore.remove('auth.token')
      localStore.remove('selectedCompany')

      // There is no session to re-arm a CSRF token for in a client.
      if (!__INVOICESHELF_CLIENT__) {
        await authService.refreshCsrfCookie().catch(() => {})
      }
    } catch (err: unknown) {
      handleApiError(err)
      localStore.remove('auth.token')
      localStore.remove('selectedCompany')

      if (!__INVOICESHELF_CLIENT__) {
        await authService.refreshCsrfCookie().catch(() => {})
      }

      throw err
    }
  }

  async function forgotPassword(data: ForgotPasswordPayload): Promise<void> {
    try {
      await authService.forgotPassword(data)
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function resetPassword(data: ResetPasswordPayload): Promise<void> {
    try {
      await authService.resetPassword(data)
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  return {
    loginData,
    forgotPasswordData,
    resetPasswordData,
    login,
    loginWithSso,
    logout,
    forgotPassword,
    resetPassword,
  }
})
