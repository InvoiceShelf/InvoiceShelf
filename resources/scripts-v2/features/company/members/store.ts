import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { memberService } from '../../../api/services/member.service'
import { roleService } from '../../../api/services/role.service'
import type {
  MemberListParams,
  MemberListResponse,
  UpdateMemberPayload,
  InviteMemberPayload,
  DeleteMembersPayload,
} from '../../../api/services/member.service'
import { useNotificationStore } from '../../../stores/notification.store'
import { handleApiError } from '../../../utils/error-handling'
import type { User } from '../../../types/domain/user'
import type { Role } from '../../../types/domain/role'
import type { CompanyInvitation } from '../../../types/domain/company'

export interface MemberForm {
  id?: number
  name: string
  email: string
  password: string | null
  phone: string | null
  role: string | null
  companies: Array<{ id: number; role?: string }>
}

function createMemberStub(): MemberForm {
  return {
    name: '',
    email: '',
    password: null,
    phone: null,
    role: null,
    companies: [],
  }
}

export const useMemberStore = defineStore('members', () => {
  // State
  const users = ref<User[]>([])
  const totalUsers = ref<number>(0)
  const roles = ref<Role[]>([])
  const pendingInvitations = ref<CompanyInvitation[]>([])
  const currentMember = ref<MemberForm>(createMemberStub())
  const selectAllField = ref<boolean>(false)
  const selectedUsers = ref<number[]>([])

  // Getters
  const isEdit = computed<boolean>(() => !!currentMember.value.id)

  // Actions
  function resetCurrentMember(): void {
    currentMember.value = createMemberStub()
  }

  async function fetchUsers(params?: MemberListParams): Promise<MemberListResponse> {
    try {
      const response = await memberService.list(params)
      users.value = response.data
      totalUsers.value = response.meta.total
      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchUser(id: number): Promise<User> {
    try {
      const response = await memberService.get(id)
      Object.assign(currentMember.value, response.data)

      if (response.data.companies?.length) {
        response.data.companies.forEach((c, i) => {
          response.data.roles?.forEach((r) => {
            if (r.scope === c.id) {
              currentMember.value.companies[i] = {
                ...currentMember.value.companies[i],
                role: r.name,
              }
            }
          })
        })
      }

      return response.data
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchRoles(): Promise<Role[]> {
    try {
      const response = await roleService.list()
      roles.value = response.data as unknown as Role[]
      return roles.value
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function addUser(data: UpdateMemberPayload): Promise<User> {
    try {
      const response = await memberService.create(data)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'members.created_message',
      })

      return response.data
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function updateUser(data: UpdateMemberPayload & { id: number }): Promise<User> {
    try {
      const response = await memberService.update(data.id, data)

      if (response.data) {
        const pos = users.value.findIndex((user) => user.id === response.data.id)
        if (pos !== -1) {
          users.value[pos] = response.data
        }
      }

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'members.updated_message',
      })

      return response.data
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function deleteUser(payload: DeleteMembersPayload): Promise<boolean> {
    try {
      const response = await memberService.delete(payload)

      payload.users.forEach((userId) => {
        const index = users.value.findIndex((user) => user.id === userId)
        if (index !== -1) {
          users.value.splice(index, 1)
        }
      })

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'members.deleted_message',
      })

      return response.success
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function deleteMultipleUsers(): Promise<boolean> {
    try {
      const response = await memberService.delete({ users: selectedUsers.value })

      selectedUsers.value.forEach((userId) => {
        const index = users.value.findIndex((_user) => _user.id === userId)
        if (index !== -1) {
          users.value.splice(index, 1)
        }
      })

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'members.deleted_message',
      })

      return response.success
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function fetchPendingInvitations(): Promise<CompanyInvitation[]> {
    try {
      const response = await memberService.fetchPendingInvitations()
      pendingInvitations.value = response.invitations
      return response.invitations
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function inviteMember(data: InviteMemberPayload): Promise<void> {
    try {
      await memberService.invite(data)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'members.invited_message',
      })

      await fetchPendingInvitations()
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  async function cancelInvitation(id: number): Promise<void> {
    try {
      await memberService.cancelInvitation(id)

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'members.invitation_cancelled',
      })

      pendingInvitations.value = pendingInvitations.value.filter(
        (inv) => inv.id !== id
      )
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  function setSelectAllState(data: boolean): void {
    selectAllField.value = data
  }

  function selectUser(data: number[]): void {
    selectedUsers.value = data
    selectAllField.value = selectedUsers.value.length === users.value.length
  }

  function selectAllUsers(): void {
    if (selectedUsers.value.length === users.value.length) {
      selectedUsers.value = []
      selectAllField.value = false
    } else {
      selectedUsers.value = users.value.map((user) => user.id)
      selectAllField.value = true
    }
  }

  return {
    users,
    totalUsers,
    roles,
    pendingInvitations,
    currentMember,
    selectAllField,
    selectedUsers,
    isEdit,
    resetCurrentMember,
    fetchUsers,
    fetchUser,
    fetchRoles,
    addUser,
    updateUser,
    deleteUser,
    deleteMultipleUsers,
    fetchPendingInvitations,
    inviteMember,
    cancelInvitation,
    setSelectAllState,
    selectUser,
    selectAllUsers,
  }
})
