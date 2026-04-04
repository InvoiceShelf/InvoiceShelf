import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { userService } from '../api/services/user.service'
import type { UpdateProfilePayload, UserSettingsPayload } from '../api/services/user.service'
import { useNotificationStore } from './notification.store'
import { handleApiError } from '../utils/error-handling'
import type { User } from '../types/domain/user'
import type { Ability } from '../types/domain/role'
import type { ApiResponse } from '../types/api'

export interface UserForm {
  name: string
  email: string
  password: string
  confirm_password: string
  language: string
}

export const useUserStore = defineStore('user', () => {
  // State
  const currentUser = ref<User | null>(null)
  const currentAbilities = ref<Ability[]>([])
  const currentUserSettings = ref<Record<string, string>>({})

  const userForm = ref<UserForm>({
    name: '',
    email: '',
    password: '',
    confirm_password: '',
    language: '',
  })

  // Getters
  const currentAbilitiesCount = computed<number>(() => currentAbilities.value.length)

  const isOwner = computed<boolean>(() => currentUser.value?.is_owner ?? false)

  // Actions
  async function fetchCurrentUser(): Promise<ApiResponse<User>> {
    try {
      const response = await userService.getProfile()
      currentUser.value = response.data
      userForm.value = {
        name: response.data.name,
        email: response.data.email,
        password: '',
        confirm_password: '',
        language: '',
      }
      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function updateCurrentUser(data: UpdateProfilePayload): Promise<ApiResponse<User>> {
    try {
      const response = await userService.updateProfile(data)
      currentUser.value = response.data
      userForm.value = {
        name: response.data.name,
        email: response.data.email,
        password: '',
        confirm_password: '',
        language: '',
      }

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.account_settings.updated_message',
      })

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function uploadAvatar(data: FormData): Promise<ApiResponse<User>> {
    try {
      const response = await userService.uploadAvatar(data)
      if (currentUser.value) {
        currentUser.value.avatar = response.data.avatar
      }
      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchUserSettings(settings?: string[]): Promise<Record<string, string | null>> {
    try {
      const response = await userService.getSettings(settings)
      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function updateUserSettings(data: UserSettingsPayload): Promise<void> {
    try {
      await userService.updateSettings(data)

      const settings = data.settings as Record<string, string | number | boolean | null>

      if (settings.language && typeof settings.language === 'string') {
        currentUserSettings.value.language = settings.language
      }

      if (settings.default_estimate_template && typeof settings.default_estimate_template === 'string') {
        currentUserSettings.value.default_estimate_template = settings.default_estimate_template
      }

      if (settings.default_invoice_template && typeof settings.default_invoice_template === 'string') {
        currentUserSettings.value.default_invoice_template = settings.default_invoice_template
      }
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  function hasAbilities(abilities: string | string[]): boolean {
    return !!currentAbilities.value.find((ab) => {
      if (ab.name === '*') return true
      if (typeof abilities === 'string') {
        return ab.name === abilities
      }
      return !!abilities.find((p) => ab.name === p)
    })
  }

  function hasAllAbilities(abilities: string[]): boolean {
    let isAvailable = true
    currentAbilities.value.filter((ab) => {
      const hasContain = !!abilities.find((p) => ab.name === p)
      if (!hasContain) {
        isAvailable = false
      }
    })
    return isAvailable
  }

  return {
    currentUser,
    currentAbilities,
    currentUserSettings,
    userForm,
    currentAbilitiesCount,
    isOwner,
    fetchCurrentUser,
    updateCurrentUser,
    uploadAvatar,
    fetchUserSettings,
    updateUserSettings,
    hasAbilities,
    hasAllAbilities,
  }
})
