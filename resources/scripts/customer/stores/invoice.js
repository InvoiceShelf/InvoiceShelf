import { handleError } from '@/scripts/customer/helpers/error-handling'
const { defineStore } = window.pinia
import http from '@/scripts/http'
export const useInvoiceStore = defineStore('customerInvoiceStore', {
  state: () => ({
    totalInvoices: 0,
    invoices: [],
    selectedViewInvoice: [],
  }),

  actions: {
    fetchInvoices(params, slug) {
      return new Promise((resolve, reject) => {
        http
          .get(`/api/v1/${slug}/customer/invoices`, { params })
          .then((response) => {
            this.invoices = response.data.data
            this.totalInvoices = response.data.meta.invoiceTotalCount
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    fetchViewInvoice(params, slug) {
      return new Promise((resolve, reject) => {
        http
          .get(`/api/v1/${slug}/customer/invoices/${params.id}`, {
            params,
          })

          .then((response) => {
            this.selectedViewInvoice = response.data.data
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    searchInvoice(params, slug) {
      return new Promise((resolve, reject) => {
        http
          .get(`/api/v1/${slug}/customer/invoices`, { params })
          .then((response) => {
            this.invoices = response.data
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
