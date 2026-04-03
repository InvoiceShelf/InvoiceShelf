import http from '@/scripts/http'
import Ls from '@/scripts/services/ls.js'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const useAdministrationStore = defineStore('administration', {
  state: () => ({
    companies: [],
    totalCompanies: 0,
    selectedCompany: null,

    users: [],
    totalUsers: 0,
  }),

  actions: {
    fetchCompanies(params) {
      return new Promise((resolve, reject) => {
        http
          .get('/api/v1/super-admin/companies', { params })
          .then((response) => {
            this.companies = response.data.data
            this.totalCompanies = response.data.meta
              ? response.data.meta.total
              : response.data.data.length
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    fetchCompany(id) {
      return new Promise((resolve, reject) => {
        http
          .get(`/api/v1/super-admin/companies/${id}`)
          .then((response) => {
            this.selectedCompany = response.data.data
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    fetchUsers(params) {
      return new Promise((resolve, reject) => {
        http
          .get('/api/v1/super-admin/users', { params })
          .then((response) => {
            this.users = response.data.data
            this.totalUsers = response.data.meta
              ? response.data.meta.total
              : response.data.data.length
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    fetchUser(id) {
      return new Promise((resolve, reject) => {
        http
          .get(`/api/v1/super-admin/users/${id}`)
          .then((response) => {
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    updateUser(id, data) {
      return new Promise((resolve, reject) => {
        http
          .put(`/api/v1/super-admin/users/${id}`, data)
          .then((response) => {
            const { global } = window.i18n
            const notificationStore = useNotificationStore()
            notificationStore.showNotification({
              type: 'success',
              message: global.t('administration.users.updated_message'),
            })
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    impersonateUser(id) {
      return new Promise((resolve, reject) => {
        http
          .post(`/api/v1/super-admin/users/${id}/impersonate`)
          .then((response) => {
            Ls.set('admin.impersonating', 'true')
            Ls.set('auth.token', `Bearer ${response.data.token}`)

            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    stopImpersonating() {
      return new Promise((resolve, reject) => {
        http
          .post('/api/v1/super-admin/stop-impersonating')
          .then((response) => {
            Ls.remove('admin.impersonating')
            Ls.remove('auth.token')

            resolve(response)
          })
          .catch((err) => {
            Ls.remove('admin.impersonating')
            Ls.remove('auth.token')

            handleError(err)
            reject(err)
          })
      })
    },

    updateCompany(id, data) {
      return new Promise((resolve, reject) => {
        http
          .put(`/api/v1/super-admin/companies/${id}`, data)
          .then((response) => {
            const { global } = window.i18n
            const notificationStore = useNotificationStore()
            notificationStore.showNotification({
              type: 'success',
              message: global.t(
                'administration.companies.updated_message'
              ),
            })
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },
  },
})
