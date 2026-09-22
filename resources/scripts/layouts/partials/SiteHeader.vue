<template>
  <header
    :class="
      isPhone
        ? 'bg-chrome text-chrome-fg'
        : 'bg-surface-tertiary border-b border-line-light'
    "
    class="
      relative z-20 flex items-center gap-2 shrink-0 h-14 px-3
      md:gap-3 md:px-6 lg:px-8 box-content safe-header
    "
  >
    <!-- Phone: the company is the context of everything below it -->
    <div v-if="isPhone" class="flex flex-1 min-w-0">
      <CompanySwitcher v-if="hasCompany" variant="appbar" tone="chrome" />
    </div>

    <!-- Tablet and desktop: search -->
    <button
      v-else
      type="button"
      class="
        flex items-center w-full max-w-md gap-2.5 h-9 px-3 text-sm transition-colors
        border rounded-lg bg-surface border-line-default text-subtle
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

    <div v-if="!isPhone" class="flex-1" />

    <button
      v-if="isPhone"
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

    <!-- Create -->
    <BaseDropdown
      v-if="hasCreateAbilities && !companyStore.isAdminMode && !isPhone"
      width-class="w-56"
      wrapper-class="flex items-center shrink-0"
    >
      <template #activator>
        <span
          class="
            inline-flex items-center gap-1.5 h-9 pl-3 pr-3.5 text-sm font-medium rounded-lg
            bg-btn-primary text-on-primary hover:bg-btn-primary-hover transition-colors
          "
        >
          <BaseIcon name="PlusIcon" class="w-4 h-4" />
          {{ $t('general.new') }}
        </span>
      </template>

      <router-link
        v-for="action in createActions"
        :key="action.to"
        :to="action.to"
      >
        <BaseDropdownItem>
          <BaseIcon :name="action.icon" class="w-5 h-5 mr-3 text-subtle" />
          {{ $t(action.label) }}
        </BaseDropdownItem>
      </router-link>
    </BaseDropdown>

    <!-- Account -->
    <BaseDropdown width-class="w-64" wrapper-class="flex items-center shrink-0">
      <template #activator>
        <img
          :src="previewAvatar"
          alt=""
          :class="isPhone ? 'ring-chrome-line' : 'ring-line-default'"
          class="block object-cover rounded-full w-8 h-8 ring-2"
        />
      </template>

      <div class="px-4 pt-3 pb-2">
        <p class="text-sm font-medium truncate text-heading">
          {{ userStore.currentUser?.name }}
        </p>
        <p class="text-xs truncate text-muted">
          {{ userStore.currentUser?.email }}
        </p>
      </div>

      <div class="px-3 pb-2">
        <div
          class="grid grid-cols-3 gap-1 p-1 rounded-lg bg-surface-secondary"
          role="radiogroup"
          :aria-label="$t('general.theme')"
        >
          <button
            v-for="opt in themeOptions"
            :key="opt.value"
            type="button"
            role="radio"
            :aria-checked="currentTheme === opt.value"
            :aria-label="$t(opt.label)"
            :class="[
              'flex items-center justify-center rounded-md h-7 transition-colors',
              currentTheme === opt.value
                ? 'bg-surface text-heading shadow-xs'
                : 'text-muted hover:text-body',
            ]"
            @click.stop="setTheme(opt.value)"
          >
            <BaseIcon :name="opt.icon" class="w-4 h-4" />
          </button>
        </div>
      </div>

      <router-link to="/admin/settings/account-settings">
        <BaseDropdownItem>
          <BaseIcon name="UserCircleIcon" class="w-5 h-5 mr-3 text-subtle" />
          {{ $t('navigation.account_settings') }}
        </BaseDropdownItem>
      </router-link>

      <router-link
        v-for="item in globalStore.userMenu"
        :key="item.name"
        :to="item.link"
      >
        <BaseDropdownItem>
          <BaseIcon :name="item.icon" class="w-5 h-5 mr-3 text-subtle" />
          {{ item.title }}
        </BaseDropdownItem>
      </router-link>

      <BaseDropdownItem @click="logout">
        <BaseIcon
          name="ArrowRightOnRectangleIcon"
          class="w-5 h-5 mr-3 text-subtle"
        />
        {{ $t('navigation.logout') }}
      </BaseDropdownItem>
    </BaseDropdown>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/scripts/stores/auth.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useTheme } from '@/scripts/composables/use-theme'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useCreateActions } from '@/scripts/composables/use-create-actions'
import { ABILITIES } from '@/scripts/config/abilities'
import { THEME } from '@/scripts/config/constants'
import type { Theme } from '@/scripts/config/constants'
import CompanySwitcher from './CompanySwitcher.vue'
import ExtensionSlot from '@/scripts/extensions/ExtensionSlot.vue'

interface ThemeOption {
  value: Theme
  icon: string
  label: string
}

const authStore = useAuthStore()
const userStore = useUserStore()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()
const router = useRouter()
const { currentTheme, setTheme } = useTheme()
const { isPhone } = useBreakpoints()
const { createActions } = useCreateActions()

const hasCompany = computed<boolean>(() => {
  return !!companyStore.selectedCompany || companyStore.isAdminMode
})

const shortcutLabel = computed<string>(() => {
  return /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘K' : 'Ctrl K'
})

const previewAvatar = computed<string>(() => {
  if (userStore.currentUser && userStore.currentUser.avatar !== 0) {
    return userStore.currentUser.avatar as string
  }
  return getDefaultAvatar()
})

const hasCreateAbilities = computed<boolean>(() => {
  return userStore.hasAbilities([
    ABILITIES.CREATE_INVOICE,
    ABILITIES.CREATE_ESTIMATE,
    ABILITIES.CREATE_CUSTOMER,
  ])
})

function getDefaultAvatar(): string {
  const imgUrl = new URL('$images/default-avatar.jpg', import.meta.url)
  return imgUrl.href
}

async function logout(): Promise<void> {
  await authStore.logout()
  router.push('/login')
}

const themeOptions: ThemeOption[] = [
  { value: THEME.LIGHT, icon: 'SunIcon', label: 'general.theme_light' },
  { value: THEME.DARK, icon: 'MoonIcon', label: 'general.theme_dark' },
  { value: THEME.SYSTEM, icon: 'ComputerDesktopIcon', label: 'general.theme_system' },
]
</script>
