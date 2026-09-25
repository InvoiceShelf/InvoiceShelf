import { defineStore } from 'pinia'
import { ref } from 'vue'
import { client } from '../../../api/client'
import { API } from '../../../api/endpoints'
import { useNotificationStore } from '../../../stores/notification.store'
import { handleApiError } from '../../../utils/error-handling'
import * as ls from '../../../utils/local-storage'
import type { Company } from '../../../types/domain/company'
import type { User } from '../../../types/domain/user'
import type { PaginatedResponse } from '../../../types/api'

export interface AdminDashboardData {
  app_version: string
  php_version: string
  database: {
    driver: string
    version: string
  }
  counts: {
    companies: number
    users: number
  }
}

export interface FetchCompaniesParams {
  search?: string
  orderByField?: string
  orderBy?: string
  page?: number
  limit?: number
}

export interface FetchUsersParams {
  display_name?: string
  email?: string
  phone?: string
  orderByField?: string
  orderBy?: string
  page?: number
}

export interface UpdateCompanyData {
  name: string
  owner_id: number
  vat_id?: string
  tax_id?: string
  address?: {
    address_street_1?: string
    address_street_2?: string
    country_id?: number | null
    state?: string
    city?: string
    phone?: string
    zip?: string
  }
}

/** A company a user belongs to, and the role (by name) they hold there. */
export interface AdminMembership {
  id: number | null
  role: string | null
}

export interface UpdateUserData {
  name: string
  email: string
  phone?: string
  password?: string
  is_super_admin?: boolean
  /** Sent to replace every membership; left out to keep them as they are. */
  companies?: Array<{ id: number; role: string }>
}

/** A role a user can be given in one company. */
export interface CompanyRoleOption {
  id: number
  name: string
  title: string
  preset: string | null
}

export const useAdminStore = defineStore('admin', () => {
  // State
  const companies = ref<Company[]>([])
  const totalCompanies = ref<number>(0)
  const selectedCompany = ref<Company | null>(null)

  const users = ref<User[]>([])
  const totalUsers = ref<number>(0)

  const dashboardData = ref<AdminDashboardData | null>(null)

  // Actions
  async function fetchDashboard(): Promise<AdminDashboardData> {
    try {
      const { data } = await client.get(API.SUPER_ADMIN_DASHBOARD)
      dashboardData.value = data
      return data
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchCompanies(params: FetchCompaniesParams): Promise<PaginatedResponse<Company>> {
    try {
      const { data } = await client.get(API.SUPER_ADMIN_COMPANIES, { params })
      companies.value = data.data
      totalCompanies.value = data.meta?.total ?? data.data.length
      return data
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchCompany(id: number | string): Promise<{ data: Company }> {
    try {
      const { data } = await client.get(`${API.SUPER_ADMIN_COMPANIES}/${id}`)
      selectedCompany.value = data.data
      return data
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function updateCompany(id: number | string, payload: UpdateCompanyData): Promise<void> {
    try {
      await client.put(`${API.SUPER_ADMIN_COMPANIES}/${id}`, payload)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'Company updated successfully.',
      })
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchUsers(params: FetchUsersParams): Promise<PaginatedResponse<User>> {
    try {
      const { data } = await client.get(API.SUPER_ADMIN_USERS, { params })
      users.value = data.data
      totalUsers.value = data.meta?.total ?? data.data.length
      return data
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchUser(id: number | string): Promise<{ data: User }> {
    try {
      const { data } = await client.get(`${API.SUPER_ADMIN_USERS}/${id}`)
      return data
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function createUser(payload: UpdateUserData): Promise<User> {
    const { data } = await client.post(API.SUPER_ADMIN_USERS, payload)

    const notificationStore = useNotificationStore()
    notificationStore.showNotification({
      type: 'success',
      message: 'administration.users.created_message',
    })

    return data.data
  }

  async function fetchCompanyRoles(companyId: number): Promise<CompanyRoleOption[]> {
    const { data } = await client.get(`${API.SUPER_ADMIN_COMPANIES}/${companyId}/roles`)
    return data.data
  }

  async function updateUser(id: number | string, payload: UpdateUserData): Promise<void> {
    try {
      await client.put(`${API.SUPER_ADMIN_USERS}/${id}`, payload)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'User updated successfully.',
      })
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function impersonateUser(id: number): Promise<void> {
    try {
      const { data } = await client.post(`${API.SUPER_ADMIN_USERS}/${id}/impersonate`)
      ls.set('admin.impersonating', 'true')
      ls.set('auth.token', `Bearer ${data.token}`)
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function stopImpersonating(): Promise<void> {
    try {
      await client.post(API.SUPER_ADMIN_STOP_IMPERSONATING)
    } catch (err: unknown) {
      handleApiError(err)
    } finally {
      ls.remove('admin.impersonating')
      ls.remove('auth.token')
    }
  }

  return {
    // State
    companies,
    totalCompanies,
    selectedCompany,
    users,
    totalUsers,
    dashboardData,
    // Actions
    fetchDashboard,
    fetchCompanies,
    fetchCompany,
    updateCompany,
    fetchUsers,
    fetchUser,
    createUser,
    updateUser,
    fetchCompanyRoles,
    impersonateUser,
    stopImpersonating,
  }
})
