import http from '@/scripts/http'
import { defineStore } from 'pinia'
import { handleError } from '@/scripts/helpers/error-handling'
import { useNotificationStore } from '@/scripts/stores/notification'

export const useModuleStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc('modules', {
    state: () => ({
      currentModule: {},
      modules: null,
      enableModules: [],
    }),

    getters: {
      salesTaxUSEnabled: (state) => state.enableModules.includes('SalesTaxUS'),
    },

    actions: {
      fetchModules(params) {
        return new Promise((resolve, reject) => {
          http
            .get(`/api/v1/modules`, { params })
            .then((response) => {
              this.modules = response.data.data

              resolve(response)
            })
            .catch((err) => {
              if (err.response?.status !== 503) {
                this.modules = []
              }
              handleError(err)
              reject(err)
            })
        })
      },

      fetchModule(id) {
        return new Promise((resolve, reject) => {
          http
            .get(`/api/v1/modules/${id}`)
            .then((response) => {
              this.currentModule = response.data

              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      disableModule(module) {
        return new Promise((resolve, reject) => {
          http
            .post(`/api/v1/modules/${module}/disable`)
            .then((response) => {
              const notificationStore = useNotificationStore()
              if (response.data.success) {
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t('modules.module_disabled'),
                })
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      enableModule(module) {
        return new Promise((resolve, reject) => {
          http
            .post(`/api/v1/modules/${module}/enable`)
            .then((response) => {
              const notificationStore = useNotificationStore()
              if (response.data.success) {
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t('modules.module_enabled'),
                })
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      uninstallModule(module) {
        return new Promise((resolve, reject) => {
          http
            .post(`/api/v1/modules/${module}/uninstall`)
            .then((response) => {
              const notificationStore = useNotificationStore()
              if (response.data.success) {
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t('modules.uninstall_success'),
                })
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },
    },
  })()
}
