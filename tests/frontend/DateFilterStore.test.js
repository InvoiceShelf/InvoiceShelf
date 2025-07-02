import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useDateFilterStore } from '@/scripts/admin/stores/dateFilter'

describe('useDateFilterStore', () => {
  let store

  beforeEach(() => {
    setActivePinia(createPinia())
    store = useDateFilterStore()
    
    // Mock localStorage
    global.localStorage = {
      getItem: vi.fn(),
      setItem: vi.fn(),
      removeItem: vi.fn(),
    }
  })

  it('should initialize with default values', () => {
    expect(store.selectedDateRange).toBe('last_30_days')
    expect(store.customStartDate).toBe('')
    expect(store.customEndDate).toBe('')
  })

  it('should calculate correct date ranges for predefined options', () => {
    const now = new Date()
    
    // Test Last 7 days
    store.setDateRange('last_7_days')
    const sevenDaysRange = store.dateRange
    expect(sevenDaysRange.start).toBeDefined()
    expect(sevenDaysRange.end).toBeDefined()
    
    // Test Year to date
    store.setDateRange('year_to_date')
    const ytdRange = store.dateRange
    expect(ytdRange.start.includes(`${now.getFullYear()}-01-01`)).toBe(true)
  })

  it('should handle custom date range validation', () => {
    store.setCustomRange('2024-01-01', '2024-01-31')
    expect(store.hasValidCustomRange).toBe(true)
    
    store.setCustomRange('2024-01-31', '2024-01-01')
    expect(store.hasValidCustomRange).toBe(false)
    
    store.setCustomRange('', '2024-01-31')
    expect(store.hasValidCustomRange).toBe(false)
  })

  it('should generate correct API parameters', () => {
    store.setDateRange('last_30_days')
    const params = store.getApiParams()
    
    expect(params).toHaveProperty('date_range', 'last_30_days')
    expect(params).toHaveProperty('start_date')
    expect(params).toHaveProperty('end_date')
  })

  it('should persist and load from localStorage', () => {
    const mockData = {
      selectedDateRange: 'last_7_days',
      customStartDate: '',
      customEndDate: ''
    }
    
    global.localStorage.getItem.mockReturnValue(JSON.stringify(mockData))
    
    store.loadFromLocalStorage()
    expect(store.selectedDateRange).toBe('last_7_days')
    
    store.saveToLocalStorage()
    expect(global.localStorage.setItem).toHaveBeenCalledWith(
      'dashboard_date_filter',
      JSON.stringify(expect.objectContaining({
        selectedDateRange: store.selectedDateRange
      }))
    )
  })

  it('should reset to defaults', () => {
    store.setDateRange('last_7_days')
    store.setCustomRange('2024-01-01', '2024-01-31')
    
    store.reset()
    
    expect(store.selectedDateRange).toBe('last_30_days')
    expect(store.customStartDate).toBe('')
    expect(store.customEndDate).toBe('')
  })

  it('should provide correct display labels', () => {
    store.setDateRange('last_30_days')
    expect(store.displayLabel).toBe('Last 30 days')
    
    store.setCustomRange('2024-01-01', '2024-01-31')
    expect(store.displayLabel).toContain('Jan 1, 2024 - Jan 31, 2024')
  })
}) 