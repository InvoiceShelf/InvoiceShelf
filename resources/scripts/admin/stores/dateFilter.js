import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useDateFilterStore = defineStore('dateFilter', () => {
  // State
  const selectedDateRange = ref('last_30_days')
  const customStartDate = ref('')
  const customEndDate = ref('')
  
  // Date range options with English labels and business justification
  const dateRangeOptions = ref([
    { 
      label: 'All time', 
      value: 'all_time',
      description: 'View complete business history without date restrictions'
    },
    { 
      label: 'Last 7 days', 
      value: 'last_7_days',
      description: 'Quick view of recent activity for daily operations'
    },
    { 
      label: 'Last 30 days', 
      value: 'last_30_days',
      description: 'Monthly performance analysis and trend identification'
    },
    { 
      label: 'Last 90 days', 
      value: 'last_90_days',
      description: 'Quarterly business cycle assessment'
    },
    { 
      label: 'Last 12 months', 
      value: 'last_12_months',
      description: 'Full annual cycle for strategic planning'
    },
    { 
      label: 'Custom Range', 
      value: 'custom',
      description: 'Flexible analysis for specific business periods'
    }
  ])

  // Computed properties
  const dateRange = computed(() => {
    const now = new Date()
    let startDate, endDate = now

    switch (selectedDateRange.value) {
      case 'all_time':
        // Return null values to indicate no date filtering
        return {
          start: null,
          end: null
        }
      case 'last_7_days':
        startDate = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
        break
      case 'last_30_days':
        startDate = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000)
        break
      case 'last_90_days':
        startDate = new Date(now.getTime() - 90 * 24 * 60 * 60 * 1000)
        break
      case 'last_12_months':
        startDate = new Date(now.getFullYear() - 1, now.getMonth(), now.getDate())
        break
      case 'custom':
        return {
          start: customStartDate.value,
          end: customEndDate.value
        }
      default:
        startDate = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000)
    }

    return {
      start: formatDate(startDate),
      end: formatDate(endDate)
    }
  })

  const isCustomRange = computed(() => selectedDateRange.value === 'custom')
  
  const hasValidCustomRange = computed(() => {
    if (!isCustomRange.value) return true
    return customStartDate.value && customEndDate.value && 
           new Date(customStartDate.value) <= new Date(customEndDate.value)
  })

  const displayLabel = computed(() => {
    const option = dateRangeOptions.value.find(opt => opt.value === selectedDateRange.value)
    if (selectedDateRange.value === 'custom' && hasValidCustomRange.value) {
      return `${formatDisplayDate(customStartDate.value)} - ${formatDisplayDate(customEndDate.value)}`
    }
    return option ? option.label : 'Last 30 days'
  })

  // Methods
  function formatDate(date) {
    if (!date) return ''
    const d = new Date(date)
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }

  function formatDisplayDate(dateString) {
    if (!dateString) return ''
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', { 
      month: 'short', 
      day: 'numeric',
      year: 'numeric'
    })
  }

  function setDateRange(range) {
    selectedDateRange.value = range
    if (range !== 'custom') {
      customStartDate.value = ''
      customEndDate.value = ''
    }
  }

  function setCustomRange(start, end) {
    customStartDate.value = start
    customEndDate.value = end
    selectedDateRange.value = 'custom'
  }

  function getApiParams() {
    if (!hasValidCustomRange.value) {
      return {}
    }
    
    // For 'all_time', don't send any date parameters
    if (selectedDateRange.value === 'all_time') {
      return {}
    }
    
    return {
      date_range: selectedDateRange.value,
      start_date: dateRange.value.start,
      end_date: dateRange.value.end
    }
  }

  // Reset to default
  function reset() {
    selectedDateRange.value = 'last_30_days'
    customStartDate.value = ''
    customEndDate.value = ''
  }

  // Persistence
  function saveToLocalStorage() {
    try {
      const data = {
        selectedDateRange: selectedDateRange.value,
        customStartDate: customStartDate.value,
        customEndDate: customEndDate.value
      }
      localStorage.setItem('dashboard_date_filter', JSON.stringify(data))
    } catch (error) {
      console.warn('Failed to save date filter to localStorage:', error)
    }
  }

  function loadFromLocalStorage() {
    try {
      const stored = localStorage.getItem('dashboard_date_filter')
      if (stored) {
        const data = JSON.parse(stored)
        selectedDateRange.value = data.selectedDateRange || 'last_30_days'
        customStartDate.value = data.customStartDate || ''
        customEndDate.value = data.customEndDate || ''
      }
    } catch (error) {
      console.warn('Failed to load date filter from localStorage:', error)
      reset()
    }
  }

  return {
    // State
    selectedDateRange,
    customStartDate,
    customEndDate,
    dateRangeOptions,
    
    // Computed
    dateRange,
    isCustomRange,
    hasValidCustomRange,
    displayLabel,
    
    // Methods
    setDateRange,
    setCustomRange,
    getApiParams,
    reset,
    saveToLocalStorage,
    loadFromLocalStorage,
    formatDate,
    formatDisplayDate
  }
}) 