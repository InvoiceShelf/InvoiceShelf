import axios from 'axios'
import { defineStore } from 'pinia'
import { handleError } from '@/scripts/helpers/error-handling'
import { useNotificationStore } from '@/scripts/stores/notification'

export const usePDFDriverStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'pdf-driver',

    state: () => ({
      pdfDriverConfig: null,
      pdf_driver: 'dompdf',
      pdf_drivers: [],

      dompdf: {},
      gotenberg: {
        gotenberg_host: '',
        gotenberg_margins: [],
        gotenberg_papersize: ''
      }
    }),

    actions: {
      async fetchDrivers() {
        try {
          const response = await axios.get('/api/v1/pdf/drivers')
          this.pdf_drivers = response.data
        } catch (err) {
          handleError(err)
          throw err
        }
      },
      async fetchConfig() {
        try {
          const response = await axios.get('/api/v1/pdf/config')
          this.pdfDriverConfig = response.data
          this.pdf_driver = response.data.pdf_driver
        } catch (err) {
          handleError(err)
          throw err
        }
      },
      async updateConfig(data) {
        try {
          const response = await axios.post('/api/v1/pdf/config', data)
          const notificationStore = useNotificationStore()
          if (response.data.success) {
            notificationStore.showNotification({
              type: 'success',
              message: global.t('wizard.success.' + response.data.success),
            })
          } else {
            notificationStore.showNotification({
              type: 'error',
              message: global.t('wizard.errors.' + response.data.error),
            })
          }
        } catch (err) {
          handleError(err)
          throw err
        }
      },
    },
  })()
}
