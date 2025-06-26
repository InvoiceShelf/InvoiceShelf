import { defineStore } from 'pinia'

export const useThemeStore = defineStore('theme', {
  state: () => ({
    isDarkMode: false,
    persistKey: 'invoiceshelf_dark_mode',
  }),

  getters: {
    /**
     * Get the current theme mode
     * @returns {string} 'dark' or 'light'
     */
    currentTheme: (state) => state.isDarkMode ? 'dark' : 'light',

    /**
     * Get the opposite theme mode
     * @returns {string} 'light' or 'dark'
     */
    oppositeTheme: (state) => state.isDarkMode ? 'light' : 'dark',
  },

  actions: {
    /**
     * Toggle dark mode
     */
    toggleDarkMode() {
      this.isDarkMode = !this.isDarkMode
      this.applyTheme()
      this.persistTheme()
    },

    /**
     * Set dark mode state
     * @param {boolean} enabled - Whether dark mode should be enabled
     */
    setDarkMode(enabled) {
      if (this.isDarkMode !== enabled) {
        this.isDarkMode = enabled
        this.applyTheme()
        this.persistTheme()
      }
    },

    /**
     * Apply theme to document
     */
    applyTheme() {
      const htmlElement = document.documentElement
      
      if (this.isDarkMode) {
        htmlElement.classList.add('dark')
      } else {
        htmlElement.classList.remove('dark')
      }
    },

    /**
     * Persist theme preference to localStorage
     */
    persistTheme() {
      try {
        localStorage.setItem(this.persistKey, JSON.stringify(this.isDarkMode))
      } catch (error) {
        console.warn('Failed to persist theme preference:', error)
      }
    },

    /**
     * Load theme preference from localStorage
     */
    loadTheme() {
      try {
        const stored = localStorage.getItem(this.persistKey)
        if (stored !== null) {
          this.isDarkMode = JSON.parse(stored)
        } else {
          // Default to system preference if no stored preference
          this.isDarkMode = this.getSystemPreference()
        }
      } catch (error) {
        console.warn('Failed to load theme preference:', error)
        this.isDarkMode = this.getSystemPreference()
      }
    },

    /**
     * Get system dark mode preference
     * @returns {boolean}
     */
    getSystemPreference() {
      if (typeof window !== 'undefined' && window.matchMedia) {
        return window.matchMedia('(prefers-color-scheme: dark)').matches
      }
      return false
    },

    /**
     * Initialize theme store
     */
    initialize() {
      this.loadTheme()
      this.applyTheme()
      
      // Listen for system theme changes
      if (typeof window !== 'undefined' && window.matchMedia) {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
        mediaQuery.addEventListener('change', (e) => {
          // Only update if user hasn't set a preference
          const stored = localStorage.getItem(this.persistKey)
          if (stored === null) {
            this.isDarkMode = e.matches
            this.applyTheme()
          }
        })
      }
    },

    /**
     * Reset to system preference
     */
    resetToSystemPreference() {
      localStorage.removeItem(this.persistKey)
      this.isDarkMode = this.getSystemPreference()
      this.applyTheme()
    },
  },
}) 