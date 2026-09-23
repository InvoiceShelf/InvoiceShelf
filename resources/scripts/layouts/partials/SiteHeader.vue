<template>
  <!-- Phones: the brand app bar, glass over the content scrolling beneath -->
  <header
    v-if="isPhone"
    class="sticky top-0 z-20 glass-chrome text-chrome-fg safe-header"
  >
    <div class="flex items-center gap-1.5 h-14 px-3">
      <div class="flex flex-1 min-w-0">
        <CompanySwitcher v-if="hasCompany" variant="appbar" tone="chrome" />
      </div>

      <button
        type="button"
        class="flex items-center justify-center w-10 h-10 rounded-lg text-chrome-fg hover:bg-chrome-hover"
        :aria-label="$t('general.search')"
        @click="globalStore.setSearchOpen(true)"
      >
        <BaseIcon name="MagnifyingGlassIcon" class="w-5.5 h-5.5" />
      </button>

      <ul class="flex items-center m-0 list-none header-actions">
        <ExtensionSlot name="header-actions" />
      </ul>

      <AccountMenu>
        <template #activator="{ avatar }">
          <img
            :src="avatar"
            alt=""
            class="block object-cover w-8 h-8 rounded-full ring-2 ring-chrome-line"
          />
        </template>
      </AccountMenu>
    </div>
  </header>

  <!-- Tablet and desktop: a full-width glass bar; content scrolls under it -->
  <header
    v-else
    class="sticky top-0 z-20 border-b glass-bar border-line-light/80"
  >
    <div class="flex items-center gap-3 px-4 h-14 md:px-6 lg:px-8">
      <button
        type="button"
        class="
          flex items-center w-full max-w-md gap-2.5 h-9 px-3 text-sm transition-colors
          border rounded-xl bg-surface/80 border-line-default text-subtle
          hover:border-line-strong hover:text-muted
        "
        @click="globalStore.setSearchOpen(true)"
      >
        <BaseIcon name="MagnifyingGlassIcon" class="w-4 h-4 shrink-0" />
        <span class="flex-1 text-left truncate">{{ $t('global_search.placeholder') }}</span>
        <kbd
          class="hidden px-1.5 font-sans text-[11px] leading-5 border rounded-md lg:block border-line-default text-subtle"
        >
          {{ shortcutLabel }}
        </kbd>
      </button>

      <div class="flex-1" />

      <div class="flex items-center gap-1">
        <ul class="flex items-center m-0 list-none header-actions">
          <ExtensionSlot name="header-actions" />
        </ul>

        <!-- Create -->
        <BaseDropdown
          v-if="createActions.length"
          width-class="w-56"
          wrapper-class="flex items-center"
        >
          <template #activator>
            <span
              class="
                inline-flex items-center gap-1.5 h-9 pl-2.5 pr-3 text-sm font-medium rounded-xl transition-colors
                bg-primary-600/10 text-primary-700 ring-1 ring-inset ring-primary-600/15 hover:bg-primary-600/15
              "
            >
              <BaseIcon name="PlusIcon" class="w-4 h-4" />
              {{ $t('general.new') }}
              <BaseIcon name="ChevronDownIcon" class="w-3.5 h-3.5 -mr-0.5 opacity-70" />
            </span>
          </template>

          <BaseDropdownItem v-for="action in createActions" :key="action.to" :to="action.to">
            <BaseIcon :name="action.icon" class="w-5 h-5 mr-3 text-subtle" />
            {{ $t(action.label) }}
          </BaseDropdownItem>
        </BaseDropdown>

        <!-- Theme: light, dark, then follow the system -->
        <button
          v-tooltip="{ content: themeLabel }"
          type="button"
          class="flex items-center justify-center transition-colors w-9 h-9 rounded-xl text-muted hover:bg-hover-strong hover:text-heading"
          :aria-label="themeLabel"
          @click="cycleTheme"
        >
          <BaseIcon :name="themeIcon" class="w-5 h-5" />
        </button>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useTheme } from '@/scripts/composables/use-theme'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useCreateActions } from '@/scripts/composables/use-create-actions'
import { THEME } from '@/scripts/config/constants'
import type { Theme } from '@/scripts/config/constants'
import CompanySwitcher from './CompanySwitcher.vue'
import AccountMenu from './AccountMenu.vue'
import ExtensionSlot from '@/scripts/extensions/ExtensionSlot.vue'

const globalStore = useGlobalStore()
const companyStore = useCompanyStore()
const { t } = useI18n()
const { currentTheme, setTheme } = useTheme()
const { isPhone } = useBreakpoints()
const { createActions } = useCreateActions()

const hasCompany = computed<boolean>(() => {
  return !!companyStore.selectedCompany || companyStore.isAdminMode
})

const shortcutLabel = computed<string>(() => {
  return /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘K' : 'Ctrl K'
})

const THEME_CYCLE: Theme[] = [THEME.LIGHT, THEME.DARK, THEME.SYSTEM]

const THEME_META: Record<string, { icon: string; label: string }> = {
  [THEME.LIGHT]: { icon: 'SunIcon', label: 'general.theme_light' },
  [THEME.DARK]: { icon: 'MoonIcon', label: 'general.theme_dark' },
  [THEME.SYSTEM]: { icon: 'ComputerDesktopIcon', label: 'general.theme_system' },
}

const themeIcon = computed<string>(() => THEME_META[currentTheme.value]?.icon ?? 'SunIcon')

const themeLabel = computed<string>(() => {
  const label = THEME_META[currentTheme.value]?.label ?? 'general.theme_light'
  return `${t('general.theme')}: ${t(label)}`
})

function cycleTheme(): void {
  const index = THEME_CYCLE.indexOf(currentTheme.value)
  setTheme(THEME_CYCLE[(index + 1) % THEME_CYCLE.length])
}
</script>
