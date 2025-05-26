const { defineStore } = window.pinia
import { useGlobalStore } from '@/scripts/customer/stores/global'
import axios from 'axios'
import { handleError } from '@/scripts/customer/helpers/error-handling'

export const useDashboardStore = defineStore({
  id: 'dashboard',
  state: () => ({
    recentInvoices: [],
    recentEstimates: [],
    invoiceCount: 0,
    estimateCount: 0,
    paymentCount: 0,
    totalDueAmount: [],
    isDashboardDataLoaded: false,
    isLoading: false,

    // Active Filter State
    activeFilter: {
      enabled: false,
      persistKey: 'customer_dashboard_active_filter',
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
     * @param {Object} params - Request parameters
     * @returns {Promise}
     */
          loadData(params = {}) {
      const globalStore = useGlobalStore()
      
      // Add active filter to params if enabled
      if (this.activeFilter.enabled) {
        params.active_only = true
      }

      this.isLoading = true

      return new Promise((resolve, reject) => {
        axios
          .get(`/api/v1/${globalStore.companySlug}/customer/dashboard`, {
            params,
          })
          .then((response) => {
            this.totalDueAmount = response.data.due_amount
            this.estimateCount = response.data.estimate_count
            this.invoiceCount = response.data.invoice_count
            this.paymentCount = response.data.payment_count
            this.recentInvoices = response.data.recentInvoices
            this.recentEstimates = response.data.recentEstimates
            globalStore.getDashboardDataLoaded = true
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
      const globalStore = useGlobalStore()
      globalStore.getDashboardDataLoaded = false
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
        const globalStore = useGlobalStore()
        globalStore.getDashboardDataLoaded = false
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
})
