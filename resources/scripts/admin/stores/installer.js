import axios from 'axios'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'
import { handleError } from '@/scripts/helpers/error-handling'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import addressStub from '@/scripts/admin/stub/address.js'
import installerStub from '@/scripts/admin/stub/installer'

export const useInstallerStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'installer',
    state: () => ({
      installers: [],
      totalInstallers: 0,
      selectAllField: false,
      selectedInstallers: [],
      selectedViewInstaller: {},
      isFetchingInitialSettings: false,
      isFetchingViewData: false,
      currentInstaller: {
        ...installerStub(),
      },
      editInstaller: null,
    }),

    getters: {
      isEdit: (state) => (state.currentInstaller.id ? true : false),
    },

    actions: {
      resetCurrentInstaller() {
        this.currentInstaller = {
          ...installerStub(),
        }
      },

      copyAddress() {
        this.currentInstaller.shipping = {
          ...this.currentInstaller.billing,
          type: 'shipping',
        }
      },

      fetchInstallerInitialSettings(isEdit) {
        const route = useRoute()
        const globalStore = useGlobalStore()
        const companyStore = useCompanyStore()

        this.isFetchingInitialSettings = true
        let editActions = []
        if (isEdit) {
          editActions = [this.fetchInstaller(route.params.id)]
        } else {
          this.currentInstaller.currency_id =
            companyStore.selectedCompanyCurrency.id
        }

        Promise.all([
          globalStore.fetchCurrencies(),
          globalStore.fetchCountries(),
          ...editActions,
        ])
          .then(async ([res1, res2, res3]) => {
            this.isFetchingInitialSettings = false
          })
          .catch((error) => {
            handleError(error)
          })
      },

      fetchInstallers(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/installers`, { params })
            .then((response) => {
              this.installers = response.data.data
              this.totalInstallers = response.data.meta.installer_total_count
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchViewInstaller(params) {
        return new Promise((resolve, reject) => {
          this.isFetchingViewData = true
          axios
            .get(`/api/v1/installers/${params.id}/stats`, { params })

            .then((response) => {
              this.selectedViewInstaller = {}
              Object.assign(this.selectedViewInstaller, response.data.data)
              this.setAddressStub(response.data.data)
              this.isFetchingViewData = false
              resolve(response)
            })
            .catch((err) => {
              this.isFetchingViewData = false
              handleError(err)
              reject(err)
            })
        })
      },

      fetchInstaller(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/installers/${id}`)
            .then((response) => {
              Object.assign(this.currentInstaller, response.data.data)

              this.setAddressStub(response.data.data)
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addInstaller(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/installers', data)
            .then((response) => {
              this.installers.push(response.data.data)

              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: global.t('installers.created_message'),
              })
              resolve(response)
            })

            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateInstaller(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/installers/${data.id}`, data)
            .then((response) => {
              if (response.data) {
                let pos = this.installers.findIndex(
                  (installer) => installer.id === response.data.data.id,
                )
                this.installers[pos] = data
                const notificationStore = useNotificationStore()
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t('installers.updated_message'),
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

      deleteInstaller(id) {
        const notificationStore = useNotificationStore()
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/installers/delete`, id)
            .then((response) => {
              let index = this.installers.findIndex(
                (installer) => installer.id === id,
              )
              this.installers.splice(index, 1)
              notificationStore.showNotification({
                type: 'success',
                message: global.tc('installers.deleted_message', 1),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteMultipleInstallers() {
        const notificationStore = useNotificationStore()

        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/installers/delete`, { ids: this.selectedInstallers })
            .then((response) => {
              this.selectedInstallers.forEach((installer) => {
                let index = this.installers.findIndex(
                  (_installer) => _installer.id === installer.id,
                )
                this.installers.splice(index, 1)
              })

              notificationStore.showNotification({
                type: 'success',
                message: global.tc('installers.deleted_message', 2),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      setSelectAllState(data) {
        this.selectAllField = data
      },

      selectInstaller(data) {
        this.selectedInstallers = data
        if (this.selectedInstallers.length === this.installers.length) {
          this.selectAllField = true
        } else {
          this.selectAllField = false
        }
      },

      selectAllInstallers() {
        if (this.selectedInstallers.length === this.installers.length) {
          this.selectedInstallers = []
          this.selectAllField = false
        } else {
          let allInstallerIds = this.installers.map((installer) => installer.id)
          this.selectedInstallers = allInstallerIds
          this.selectAllField = true
        }
      },

      setAddressStub(data) {
        if (!data.billing) this.currentInstaller.billing = { ...addressStub }
        if (!data.shipping) this.currentInstaller.shipping = { ...addressStub }
      },
    },
  })()
}
