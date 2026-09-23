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
    logout,
    forgotPassword,
    resetPassword,
  }
})
