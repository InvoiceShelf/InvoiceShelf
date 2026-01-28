import axios from 'axios'
import moment from 'moment'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'
import { useCompanyStore } from './company'
import { useNotificationStore } from '@/scripts/stores/notification'
import creditNoteStub from '../stub/credit-note'
import { handleError } from '@/scripts/helpers/error-handling'
import { useNotesStore } from './note'

export const useCreditNoteStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'credit-note',

    state: () => ({
      creditNotes: [],
      creditNoteTotalCount: 0,

      selectAllField: false,
      selectedCreditNotes: [],
      selectedNote: null,
      showExchangeRate: false,
      drivers: [],

      currentCreditNote: {
        ...creditNoteStub,
      },

      isFetchingInitialData: false,
    }),


    actions: {
      fetchCreditNoteInitialData(isEdit) {
        const companyStore = useCompanyStore()
        const notesStore = useNotesStore()
        const route = useRoute()

        this.isFetchingInitialData = true

        let actions = []
        if (isEdit) {
          actions = [this.fetchCreditNote(route.params.id)]
        }
        Promise.all([
          this.getNextNumber(),
          ...actions,
        ])
          .then(async ([res1, res2]) => {
            if (isEdit) {
              if (res2.data.data.invoice) {
                this.currentCreditNote.maxCreditableAmount = parseInt(
                  res2.data.data.invoice.due_amount
                )
              }
            }

            // On Create
            else if (!isEdit && res1.data) {
              await notesStore.fetchNotes()
              this.currentCreditNote.notes = notesStore.getDefaultNoteForType('Payment')?.notes
              this.currentCreditNote.credit_note_date = moment().format('YYYY-MM-DD')
              this.currentCreditNote.credit_note_number = res1.data.nextNumber
              this.currentCreditNote.currency =
                companyStore.selectedCompanyCurrency
            }

            this.isFetchingInitialData = false
          })
          .catch((err) => {
            handleError(err)
          })
      },

      fetchCreditNotes(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/credit-notes`, { params })
            .then((response) => {
              this.creditNotes = response.data.data
              this.creditNoteTotalCount = response.data.meta.credit_note_total_count
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchCreditNote(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/credit-notes/${id}`)
            .then((response) => {
              Object.assign(this.currentCreditNote, response.data.data)
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addCreditNote(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/credit-notes', data)
            .then((response) => {
              this.creditNotes.push(response.data)
              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: global.t('credit_notes.created_message'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateCreditNote(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/credit-notes/${data.id}`, data)
            .then((response) => {
              if (response.data) {
                let pos = this.creditNotes.findIndex(
                  (creditNote) => creditNote.id === response.data.data.id
                )

                this.creditNotes[pos] = data.creditNote

                const notificationStore = useNotificationStore()
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t('credit_notes.updated_message'),
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

      deleteCreditNote(id) {
        const notificationStore = useNotificationStore()

        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/credit-notes/delete`, id)
            .then((response) => {
              let index = this.creditNotes.findIndex(
                (creditNote) => creditNote.id === id
              )
              this.creditNotes.splice(index, 1)

              notificationStore.showNotification({
                type: 'success',
                message: global.t('credit_notes.deleted_message', 1),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteMultipleCreditNotes() {
        const notificationStore = useNotificationStore()
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/credit-notes/delete`, { ids: this.selectedCreditNotes })
            .then((response) => {
              this.selectedCreditNotes.forEach((creditNote) => {
                let index = this.creditNotes.findIndex(
                  (_creditNote) => _creditNote.id === creditNote.id
                )
                this.creditNotes.splice(index, 1)
              })
              notificationStore.showNotification({
                type: 'success',
                message: global.t('credit_notes.deleted_message', 2),
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

      selectCreditNote(data) {
        this.selectedCreditNotes = data
        if (this.selectedCreditNotes.length === this.creditNotes.length) {
          this.selectAllField = true
        } else {
          this.selectAllField = false
        }
      },

      selectAllCreditNotes() {
        if (this.selectedCreditNotes.length === this.creditNotes.length) {
          this.selectedCreditNotes = []
          this.selectAllField = false
        } else {
          let allCreditNoteIds = this.creditNotes.map((creditNote) => creditNote.id)
          this.selectedCreditNotes = allCreditNoteIds
          this.selectAllField = true
        }
      },

      selectNote(data) {
        this.selectedNote = null
        this.selectedNote = data
      },

      resetSelectedNote(data) {
        this.selectedNote = null
      },

      searchCreditNote(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/credit-notes`, { params })
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

      previewCreditNote(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/credit-notes/${params.id}/send/preview`, { params })
            .then((response) => {
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      sendEmail(data) {
        return new Promise((resolve, reject) => {
          axios
            .post(`/api/v1/credit-notes/${data.id}/send`, data)
            .then((response) => {
              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: global.t('credit_notes.send_payment_successfully'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      getNextNumber(params, setState = false) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/next-number?key=creditnote`, { params })
            .then((response) => {
              if (setState) {
                this.currentCreditNote.credit_note_number = response.data.nextNumber
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      resetCurrentCreditNote() {
        this.currentCreditNote = {
          ...creditNoteStub,
        }
      },
    },
  })()
}
