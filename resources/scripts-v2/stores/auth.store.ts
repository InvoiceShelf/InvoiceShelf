import { defineStore } from 'pinia'
import { ref } from 'vue'
import { authService } from '@v2/api/services/auth.service'
import type { LoginPayload, ForgotPasswordPayload, ResetPasswordPayload } from '@v2/api/services/auth.service'
import { useNotificationStore } from './notification.store'
import { handleApiError } from '../utils/error-handling'
import * as localStore from '../utils/local-storage'

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
      await authService.login(data)

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
      await authService.logout()

      notificationStore.showNotification({
        type: 'success',
        message: 'Logged out successfully.',
      })

      localStore.remove('auth.token')
      localStore.remove('selectedCompany')

      await authService.refreshCsrfCookie().catch(() => {})
    } catch (err: unknown) {
      handleApiError(err)
      localStore.remove('auth.token')
      localStore.remove('selectedCompany')
      await authService.refreshCsrfCookie().catch(() => {})
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
