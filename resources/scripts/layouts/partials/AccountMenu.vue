<template>
  <BaseDropdown
    width-class="w-64"
    :position="position"
    :wrapper-class="wrapperClass"
    :label="$t('navigation.account_menu', { name: userStore.currentUser?.name ?? '' })"
  >
    <template #activator>
      <slot name="activator" :avatar="avatar" />
    </template>

    <div class="flex items-center gap-3 px-3 pt-2.5 pb-2">
      <img :src="avatar" alt="" class="object-cover w-9 h-9 rounded-full shrink-0" />
      <div class="min-w-0">
        <p class="text-sm font-medium truncate text-heading">
          {{ userStore.currentUser?.name }}
        </p>
        <p class="text-xs truncate text-muted">
          {{ userStore.currentUser?.email }}
        </p>
      </div>
    </div>

    <!-- Theme: three menu items in a row, so the arrow keys reach them -->
    <div class="px-2 pb-2">
      <div
        class="grid grid-cols-3 gap-1 p-1 rounded-lg bg-surface-muted/60"
        role="group"
        :aria-label="$t('general.theme')"
      >
        <MenuItem
          v-for="opt in themeOptions"
          :key="opt.value"
          v-slot="{ active }"
          as="template"
          @click="setTheme(opt.value)"
        >
          <button
            type="button"
            :class="[
              'flex items-center justify-center gap-1.5 rounded-md h-7 text-xs font-medium transition-colors',
              currentTheme === opt.value
                ? 'bg-surface text-heading shadow-xs'
                : 'text-muted hover:text-body',
              active ? 'ring-2 ring-focus' : '',
            ]"
          >
            <BaseIcon :name="opt.icon" class="w-3.5 h-3.5" aria-hidden="true" />
            {{ $t(opt.label) }}
            <span v-if="currentTheme === opt.value" class="sr-only">{{ $t('general.current') }}</span>
          </button>
        </MenuItem>
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

    <div class="my-1 border-t border-line-light" />

    <BaseDropdownItem @click="logout">
      <BaseIcon name="ArrowRightOnRectangleIcon" class="w-5 h-5 mr-3 text-subtle" />
      {{ $t('navigation.logout') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { MenuItem } from '@headlessui/vue'
import type { Placement } from '@popperjs/core'
import { useAuthStore } from '@/scripts/stores/auth.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useTheme } from '@/scripts/composables/use-theme'
import { THEME } from '@/scripts/config/constants'
import type { Theme } from '@/scripts/config/constants'

/**
 * The signed-in user's menu: who they are, theme, account settings, module
 * user-menu entries and sign out. The sidebar footer opens it on wider
 * screens and the phone app bar opens it as a sheet; the activator is the
 * caller's.
 */
interface Props {
  position?: Placement
  wrapperClass?: string
}

withDefaults(defineProps<Props>(), {
  position: 'bottom-end',
  wrapperClass: 'flex items-center',
})

interface ThemeOption {
  value: Theme
  icon: string
  label: string
}

const authStore = useAuthStore()
const userStore = useUserStore()
const globalStore = useGlobalStore()
const router = useRouter()
const { currentTheme, setTheme } = useTheme()

const avatar = computed<string>(() => {
  if (userStore.currentUser && userStore.currentUser.avatar !== 0) {
    return userStore.currentUser.avatar as string
  }

  return new URL('$images/default-avatar.jpg', import.meta.url).href
})

const themeOptions: ThemeOption[] = [
  { value: THEME.LIGHT, icon: 'SunIcon', label: 'general.theme_light' },
  { value: THEME.DARK, icon: 'MoonIcon', label: 'general.theme_dark' },
  { value: THEME.SYSTEM, icon: 'ComputerDesktopIcon', label: 'general.theme_system' },
]

async function logout(): Promise<void> {
  await authStore.logout()
  router.push('/login')
}
</script>
