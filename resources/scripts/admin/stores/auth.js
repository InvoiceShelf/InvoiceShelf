import http from '@/scripts/http'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const useAuthStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc('auth', {
    state: () => ({
      status: '',

      loginData: {
        email: '',
        password: '',
        remember: '',
      },
    }),

    actions: {
      login(data) {
        return new Promise((resolve, reject) => {
          http.get('/sanctum/csrf-cookie').then((response) => {
            if (response) {
              http
                .post('/login', data)
                .then((response) => {
                  resolve(response)

                  setTimeout(() => {
                    this.loginData.email = ''
                    this.loginData.password = ''
                  }, 1000)
                })
                .catch((err) => {
                  handleError(err)
                  reject(err)
                })
            }
          })
        })
      },

      logout() {
        return new Promise((resolve, reject) => {
          http
            .post('/auth/logout')
            .then(async (response) => {
              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: 'Logged out successfully.',
              })

              // Clear stored auth data so next login doesn't send stale tokens
              window.Ls.remove('auth.token')
              window.Ls.remove('selectedCompany')

              // Refresh CSRF token so next login works cleanly
              await http.get('/sanctum/csrf-cookie').catch(() => {})

              window.router.push('/login')
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              window.Ls.remove('auth.token')
              window.Ls.remove('selectedCompany')
              http.get('/sanctum/csrf-cookie').catch(() => {})
              window.router.push('/login')
              reject(err)
            })
        })
      },
    },
  })()
}