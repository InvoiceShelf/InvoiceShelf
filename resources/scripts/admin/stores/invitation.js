import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import http from '@/scripts/http'

export const useInvitationStore = defineStore('invitation', {
  state: () => ({
    pendingInvitations: [],
  }),

  actions: {
    setPendingInvitations(invitations) {
      this.pendingInvitations = invitations
    },

    async fetchPending() {
      const response = await http.get('/api/v1/invitations/pending')
      this.pendingInvitations = response.data.invitations
      return response
    },

    async accept(token) {
      const notificationStore = useNotificationStore()
      const globalStore = useGlobalStore()

      const response = await http.post(`/api/v1/invitations/${token}/accept`)

      notificationStore.showNotification({
        type: 'success',
        message: 'Invitation accepted!',
      })

      // Refresh bootstrap to get updated companies list
      await globalStore.bootstrap()

      return response
    },

    async decline(token) {
      const notificationStore = useNotificationStore()

      const response = await http.post(`/api/v1/invitations/${token}/decline`)

      this.pendingInvitations = this.pendingInvitations.filter(
        (inv) => inv.token !== token
      )

      notificationStore.showNotification({
        type: 'success',
        message: 'Invitation declined.',
      })

      return response
    },
  },
})
