import axios from 'axios'
import { defineStore } from 'pinia'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const useDashboardStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'dashboard',

    state: () => ({
      stats: {
        totalAmountDue: 0,
        totalCustomerCount: 0,
        totalInvoiceCount: 0,
        totalEstimateCount: 0,
      },

      chartData: {
        months: [],
        invoiceTotals: [],
        expenseTotals: [],
        receiptTotals: [],
        netIncomeTotals: [],
      },

      statusDistribution: {
        paid: 0,
        pending: 0,
        overdue: 0,
      },

      totalSales: null,
      totalReceipts: null,
      totalExpenses: null,
      totalNetIncome: null,

      recentDueInvoices: [],
      recentEstimates: [],

      isDashboardDataLoaded: false,
      isLoading: false,
    }),

    getters: {},

    actions: {
      /**
       * Load dashboard data with optional parameters
       * @param {Object} params - Query parameters for the API call
       * @returns {Promise}
       */
      loadData(params = {}) {
        this.isLoading = true

        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/dashboard`, { params })
            .then((response) => {
              // Stats
              this.stats.totalAmountDue = response.data.total_amount_due
              this.stats.totalCustomerCount = response.data.total_customer_count
              this.stats.totalInvoiceCount = response.data.total_invoice_count
              this.stats.totalEstimateCount = response.data.total_estimate_count

              // Dashboard Chart
              if (this.chartData && response.data.chart_data) {
                this.chartData.months = response.data.chart_data.months
                this.chartData.invoiceTotals =
                  response.data.chart_data.invoice_totals
                this.chartData.expenseTotals =
                  response.data.chart_data.expense_totals
                this.chartData.receiptTotals =
                  response.data.chart_data.receipt_totals
                this.chartData.netIncomeTotals =
                  response.data.chart_data.net_income_totals
              }

              if (response.data.status_distribution) {
                this.statusDistribution = response.data.status_distribution
              }

              // Dashboard Chart Labels
              this.totalSales = response.data.total_sales
              this.totalReceipts = response.data.total_receipts
              this.totalExpenses = response.data.total_expenses
              this.totalNetIncome = response.data.total_net_income

              // Dashboard Table Data
              this.recentDueInvoices = response.data.recent_due_invoices
              this.recentEstimates = response.data.recent_estimates

              this.isDashboardDataLoaded = true
              this.isLoading = false

              resolve(response)
            })
            .catch((err) => {
              this.isLoading = false
              handleError(err)
              reject(err)
            })
        })
      },

      /**
       * Initialize the dashboard store
       * @returns {Promise}
       */
      initialize() {
        return this.loadData()
      },

      /**
       * Load dashboard data with unified date filtering
       * @param {Object} dateFilterParams - Date filter parameters from unified date filter
       * @returns {Promise}
       */
      loadDataWithDateFilter(dateFilterParams = {}) {
        const params = { ...dateFilterParams }
        return this.loadData(params)
      },

      /**
       * Refresh all dashboard components with current date filter
       * @param {Object} dateFilterParams - Date filter parameters
       * @returns {Promise}
       */
      refreshWithDateFilter(dateFilterParams = {}) {
        this.isDashboardDataLoaded = false
        return this.loadDataWithDateFilter(dateFilterParams)
      },

      /**
       * Export dashboard snapshot as PDF
       * @param {Object} chartImages - Chart images in Base64 format
       * @param {Object} tableData - Table data for recent invoices
       * @param {Array} selectedSections - Array of selected sections to include
       * @returns {Promise}
       */
      exportDashboardSnapshot(chartImages, tableData, selectedSections) {
        const notificationStore = useNotificationStore()
        const notification = notificationStore.showNotification({
          type: 'loading',
          message: 'Generating dashboard snapshot... Please wait.',
          persistent: true,
        })

                 return new Promise((resolve, reject) => {
           const payload = {
             chartImages,
             tableData,
             selectedSections,
             dashboardData: {
               stats: this.stats,
               statusDistribution: this.statusDistribution,
               totalSales: this.totalSales,
               totalReceipts: this.totalReceipts,
               totalExpenses: this.totalExpenses,
               totalNetIncome: this.totalNetIncome,
               recentDueInvoices: this.recentDueInvoices,
               recentEstimates: this.recentEstimates
             }
           }

          axios
            .post('/api/v1/dashboard/export-snapshot', payload, {
              responseType: 'blob',
            })
            .then((response) => {
              const url = window.URL.createObjectURL(new Blob([response.data]))
              const link = document.createElement('a')
              link.href = url

              const contentDisposition = response.headers['content-disposition']
              let fileName = 'dashboard-snapshot.pdf'
              if (contentDisposition) {
                  const fileNameMatch = contentDisposition.match(/filename="([^"]+)"/)
                  if (fileNameMatch && fileNameMatch.length === 2)
                      fileName = fileNameMatch[1]
              }

              link.setAttribute('download', fileName)
              document.body.appendChild(link)
              link.click()
              link.remove()
              window.URL.revokeObjectURL(url)

              notificationStore.hideNotification(notification)
              notificationStore.showNotification({
                type: 'success',
                message: 'Dashboard snapshot generated successfully.',
                timeout: 3000,
              })
              resolve(response)
            })
            .catch((err) => {
              notificationStore.hideNotification(notification)
              // Try to read the error message from the blob
              const reader = new FileReader()
              reader.onload = () => {
                try {
                  const errorData = JSON.parse(reader.result)
                  notificationStore.showNotification({
                    type: 'error',
                    message: errorData.message || 'An error occurred during snapshot export.',
                  })
                } catch (e) {
                  notificationStore.showNotification({
                    type: 'error',
                    message: 'An unknown error occurred during snapshot export.',
                  })
                }
              }
              reader.onerror = () => {
                 notificationStore.showNotification({
                    type: 'error',
                    message: 'Could not read error response.',
                  })
              }
              reader.readAsText(err.response.data)
              
              reject(err)
            })
        })
      },

      /**
       * Export dashboard data
       * @param {Object} params - Export parameters (format, sections, filters)
       * @returns {Promise}
       */
      exportDashboard(params) {
        const notificationStore = useNotificationStore()
        const notification = notificationStore.showNotification({
          type: 'loading',
          message: 'Generating your export... Please wait.',
          persistent: true,
        })

        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/dashboard/export', params, {
              responseType: 'blob',
            })
            .then((response) => {
              const url = window.URL.createObjectURL(new Blob([response.data]))
              const link = document.createElement('a')
              link.href = url

              const contentDisposition = response.headers['content-disposition']
              let fileName = 'export.dat'
              if (contentDisposition) {
                  const fileNameMatch = contentDisposition.match(/filename="([^"]+)"/)
                  if (fileNameMatch && fileNameMatch.length === 2)
                      fileName = fileNameMatch[1]
              }

              link.setAttribute('download', fileName)
              document.body.appendChild(link)
              link.click()
              link.remove()
              window.URL.revokeObjectURL(url)

              notificationStore.hideNotification(notification)
              notificationStore.showNotification({
                type: 'success',
                message: 'Export generated successfully.',
                timeout: 3000,
              })
              resolve(response)
            })
            .catch((err) => {
              notificationStore.hideNotification(notification)
              // Try to read the error message from the blob
              const reader = new FileReader()
              reader.onload = () => {
                try {
                  const errorData = JSON.parse(reader.result)
                  notificationStore.showNotification({
                    type: 'error',
                    message: errorData.message || 'An error occurred during export.',
                  })
                } catch (e) {
                  notificationStore.showNotification({
                    type: 'error',
                    message: 'An unknown error occurred during export.',
                  })
                }
              }
              reader.onerror = () => {
                 notificationStore.showNotification({
                    type: 'error',
                    message: 'Could not read error response.',
                  })
              }
              reader.readAsText(err.response.data)
              
              reject(err)
            })
        })
      }
    },
  })()
}
