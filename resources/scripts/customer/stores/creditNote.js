import { handleError } from '@/scripts/customer/helpers/error-handling'
const { defineStore } = window.pinia
import axios from 'axios'

export const useCreditNoteStore = defineStore({
  id: 'customerCreditNoteStore',
  state: () => ({
    creditNotes: [],
    selectedViewCreditNote: [],
    totalCreditNotes: 0,
  }),

  actions: {
    fetchCreditNotes(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/credit-notes`, { params })
          .then((response) => {
            this.creditNotes = response.data.data
            this.totalCreditNotes = response.data.meta.creditNoteTotalCount
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    fetchViewCreditNote(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/credit-notes/${params.id}`)

          .then((response) => {
            this.selectedViewCreditNote = response.data.data
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    searchCreditNote(params, slug) {
      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${slug}/customer/credit-notes`, { params })
          .then((response) => {
            this.creditNotes = response.data
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
