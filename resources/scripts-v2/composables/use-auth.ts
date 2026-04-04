import { ref, computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import * as ls from '../utils/local-storage'
import { LS_KEYS } from '@v2/config/constants'

export interface User {
  id: number
  name: string
  email: string
  avatar: string | number
  is_owner: boolean
  is_super_admin: boolean
  [key: string]: unknown
}

export interface UseAuthReturn {
  currentUser: Ref<User | null>
  isAuthenticated: ComputedRef<boolean>
  isOwner: ComputedRef<boolean>
  isSuperAdmin: ComputedRef<boolean>
  setUser: (user: User) => void
  clearUser: () => void
  login: (loginFn: () => Promise<User>) => Promise<User>
  logout: (logoutFn: () => Promise<void>) => Promise<void>
}

const currentUser = ref<User | null>(null)

/**
 * Composable for managing authentication state.
 * Provides the current user, login/logout helpers, and role-based computed properties.
 */
export function useAuth(): UseAuthReturn {
  const isAuthenticated = computed<boolean>(() => currentUser.value !== null)

  const isOwner = computed<boolean>(
    () => currentUser.value?.is_owner === true
  )

  const isSuperAdmin = computed<boolean>(
    () => currentUser.value?.is_super_admin === true
  )

  function setUser(user: User): void {
    currentUser.value = user
  }

  function clearUser(): void {
    currentUser.value = null
  }

  /**
   * Execute a login function and set the current user on success.
   *
   * @param loginFn - Async function that performs the login and returns a User
   * @returns The authenticated user
   */
  async function login(loginFn: () => Promise<User>): Promise<User> {
    const user = await loginFn()
    currentUser.value = user
    return user
  }

  /**
   * Execute a logout function and clear auth state.
   *
   * @param logoutFn - Async function that performs the logout
   */
  async function logout(logoutFn: () => Promise<void>): Promise<void> {
    await logoutFn()
    currentUser.value = null
    ls.remove(LS_KEYS.AUTH_TOKEN)
    ls.remove(LS_KEYS.SELECTED_COMPANY)
  }

  return {
    currentUser,
    isAuthenticated,
    isOwner,
    isSuperAdmin,
    setUser,
    clearUser,
    login,
    logout,
  }
}
