import { ref, onMounted, onUnmounted } from 'vue'
import type { Ref } from 'vue'
import { THEME, LS_KEYS } from '@/scripts/config/constants'
import type { Theme } from '@/scripts/config/constants'
import * as ls from '../utils/local-storage'

export interface UseThemeReturn {
  currentTheme: Ref<Theme>
  setTheme: (theme: Theme) => void
  applyTheme: (theme?: Theme) => void
}

const currentTheme = ref<Theme>(
  (ls.get<string>(LS_KEYS.THEME) as Theme) ?? THEME.SYSTEM
)

let mediaQueryCleanup: (() => void) | null = null

/**
 * Apply the correct data-theme attribute to the document element.
 *
 * @param theme - The theme to apply (light, dark, or system)
 */
function applyThemeToDocument(theme: Theme): void {
  const prefersDark =
    theme === THEME.DARK ||
    (theme === THEME.SYSTEM &&
      window.matchMedia('(prefers-color-scheme: dark)').matches)

  if (prefersDark) {
    document.documentElement.setAttribute('data-theme', 'dark')
  } else {
    document.documentElement.removeAttribute('data-theme')
  }
}

/**
 * Composable for managing the application theme (light/dark/system).
 * Extracted from TheSiteHeader. Listens for system preference changes
 * when set to "system" mode.
 */
export function useTheme(): UseThemeReturn {
  /**
   * Set and persist the current theme.
   *
   * @param theme - The theme to set
   */
  function setTheme(theme: Theme): void {
    currentTheme.value = theme
    ls.set(LS_KEYS.THEME, theme)
    applyThemeToDocument(theme)
  }

  /**
   * Apply the given or current theme to the document.
   *
   * @param theme - Optional theme override; uses currentTheme if not provided
   */
  function applyTheme(theme?: Theme): void {
    applyThemeToDocument(theme ?? currentTheme.value)
  }

  function handleSystemThemeChange(): void {
    if (currentTheme.value === THEME.SYSTEM) {
      applyThemeToDocument(THEME.SYSTEM)
    }
  }

  onMounted(() => {
    applyThemeToDocument(currentTheme.value)

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    mediaQuery.addEventListener('change', handleSystemThemeChange)

    mediaQueryCleanup = () => {
      mediaQuery.removeEventListener('change', handleSystemThemeChange)
    }
  })

  onUnmounted(() => {
    if (mediaQueryCleanup) {
      mediaQueryCleanup()
      mediaQueryCleanup = null
    }
  })

  return {
    currentTheme,
    setTheme,
    applyTheme,
  }
}
