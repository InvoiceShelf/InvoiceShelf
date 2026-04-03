import http from '@/scripts/http'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const useCompanyMailStore = defineStore('company-mail', {
  state: () => ({
    mailConfigData: null,
    mail_driver: '',
    mail_drivers: [],
  }),

  actions: {
    fetchMailDrivers() {
      return new Promise((resolve, reject) => {
        http
          .get('/api/v1/mail/drivers')
          .then((response) => {
            if (response.data) {
              this.mail_drivers = response.data
            }
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    fetchMailConfig() {
      return new Promise((resolve, reject) => {
        http
          .get('/api/v1/company/mail/company-config')
          .then((response) => {
            if (response.data) {
              this.mailConfigData = response.data
              this.mail_driver = response.data.mail_driver || ''
            }
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    updateMailConfig(data) {
      return new Promise((resolve, reject) => {
        http
          .post('/api/v1/company/mail/company-config', data)
          .then((response) => {
            const { global } = window.i18n
            const notificationStore = useNotificationStore()
            notificationStore.showNotification({
              type: 'success',
              message: global.t('settings.mail.company_mail_config_updated'),
            })
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    sendTestMail(data) {
      return new Promise((resolve, reject) => {
        http
          .post('/api/v1/company/mail/company-test', data)
          .then((response) => {
            const { global } = window.i18n
            const notificationStore = useNotificationStore()
            if (response.data.success) {
              notificationStore.showNotification({
                type: 'success',
                message: global.t('general.send_mail_successfully'),
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
})
