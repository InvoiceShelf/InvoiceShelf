import axios from 'axios'
import { defineStore } from 'pinia'
import { useGlobalStore } from '@/scripts/admin/stores/global'
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

      totalSales: null,
      totalReceipts: null,
      totalExpenses: null,
      totalNetIncome: null,

      recentDueInvoices: [],
      recentEstimates: [],

      isDashboardDataLoaded: false,
      isLoading: false,

      // Active Filter State
      activeFilter: {
        enabled: false,
        persistKey: 'dashboard_active_filter',
      },
    }),

    getters: {
      /**
       * Get the current active filter state
       * @returns {boolean}
       */
      isActiveFilterEnabled: (state) => state.activeFilter.enabled,
    },

    actions: {
      /**
       * Load dashboard data with optional parameters
       * @param {Object} params - Query parameters for the API call
       * @returns {Promise}
       */
      loadData(params = {}) {
        // Add active filter to params if enabled
        if (this.activeFilter.enabled) {
          params.active_only = true
        }

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
       * Toggle the active filter state
       * @returns {Promise}
       */
      toggleActiveFilter() {
        this.activeFilter.enabled = !this.activeFilter.enabled
        this.persistActiveFilter()
        
        // Reload data with new filter state
        this.isDashboardDataLoaded = false
        return this.loadData()
      },

      /**
       * Set the active filter state
       * @param {boolean} enabled - Whether the filter should be enabled
       * @returns {Promise}
       */
      setActiveFilter(enabled) {
        if (this.activeFilter.enabled !== enabled) {
          this.activeFilter.enabled = enabled
          this.persistActiveFilter()
          
          // Reload data with new filter state
          this.isDashboardDataLoaded = false
          return this.loadData()
        }
        return Promise.resolve()
      },

      /**
       * Persist the active filter state to localStorage
       */
      persistActiveFilter() {
        try {
          localStorage.setItem(
            this.activeFilter.persistKey,
            JSON.stringify(this.activeFilter.enabled)
          )
        } catch (error) {
          console.warn('Failed to persist active filter state:', error)
        }
      },

      /**
       * Load the active filter state from localStorage
       */
      loadActiveFilter() {
        try {
          const stored = localStorage.getItem(this.activeFilter.persistKey)
          if (stored !== null) {
            this.activeFilter.enabled = JSON.parse(stored)
          }
        } catch (error) {
          console.warn('Failed to load active filter state:', error)
          this.activeFilter.enabled = false
        }
      },

      /**
       * Initialize the dashboard store
       * @returns {Promise}
       */
      initialize() {
        this.loadActiveFilter()
        return this.loadData()
      },
    },
  })()
}
