<template>
  <header
    class="
      fixed top-0 left-0 z-20 flex items-center justify-between w-full
      px-4 py-3 md:h-16 md:px-8 bg-linear-to-r from-header-from to-header-to
    "
  >
    <div class="flex items-center">
      <router-link
        :to="companyStore.isAdminMode ? '/admin/administration/dashboard' : '/admin/dashboard'"
        class="
          text-lg not-italic font-black tracking-wider text-white
          brand-main font-base hidden md:block
        "
      >
        <img v-if="adminLogo" :src="adminLogo" class="h-6" />
        <MainLogo v-else class="h-6" light-color="white" dark-color="white" />
      </router-link>
    </div>

    <!-- Mobile toggle button -->
    <div
      :class="{ 'is-active': globalStore.isSidebarOpen }"
      class="
        flex float-left p-1 overflow-visible text-sm ease-linear bg-surface
        border-0 rounded cursor-pointer md:hidden md:ml-0 hover:bg-hover-strong
      "
      @click.prevent="onToggle"
    >
      <BaseIcon name="Bars3Icon" class="!w-6 !h-6 text-muted" />
    </div>

    <ul class="flex float-right h-8 m-0 list-none md:h-9">
      <!-- Create dropdown -->
      <li
        v-if="hasCreateAbilities && !companyStore.isAdminMode"
        class="relative hidden float-left m-0 md:block"
      >
        <BaseDropdown width-class="w-48">
          <template #activator>
            <div
              class="
                flex items-center justify-center w-8 h-8 ml-2 text-sm text-white
                bg-white/20 rounded-lg hover:bg-white/30 md:h-9 md:w-9
              "
            >
              <BaseIcon name="PlusIcon" class="w-5 h-5 text-white" />
            </div>
          </template>

          <router-link to="/admin/invoices/create">
            <BaseDropdownItem
              v-if="userStore.hasAbilities(ABILITIES.CREATE_INVOICE)"
            >
              <BaseIcon
                name="DocumentTextIcon"
                class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
                aria-hidden="true"
              />
              {{ $t('invoices.new_invoice') }}
            </BaseDropdownItem>
          </router-link>

          <router-link to="/admin/estimates/create">
            <BaseDropdownItem
              v-if="userStore.hasAbilities(ABILITIES.CREATE_ESTIMATE)"
            >
              <BaseIcon
                name="DocumentIcon"
                class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
                aria-hidden="true"
              />
              {{ $t('estimates.new_estimate') }}
            </BaseDropdownItem>
          </router-link>

          <router-link to="/admin/customers/create">
            <BaseDropdownItem
              v-if="userStore.hasAbilities(ABILITIES.CREATE_CUSTOMER)"
            >
              <BaseIcon
                name="UserIcon"
                class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
                aria-hidden="true"
              />
              {{ $t('customers.new_customer') }}
            </BaseDropdownItem>
          </router-link>
        </BaseDropdown>
      </li>

      <!-- Global search -->
      <li v-if="!companyStore.isAdminMode" class="ml-2">
        <GlobalSearchBar
          v-if="
            userStore.currentUser?.is_owner ||
            userStore.hasAbilities(ABILITIES.VIEW_CUSTOMER)
          "
        />
      </li>

      <!-- Company switcher -->
      <li>
        <CompanySwitcher />
      </li>

      <!-- User dropdown -->
      <li class="relative block float-left ml-2">
        <BaseDropdown width-class="w-48">
          <template #activator>
            <img
              :src="previewAvatar"
              class="block w-8 h-8 rounded-full ring-2 ring-white/30 md:h-9 md:w-9 object-cover"
            />
          </template>

          <!-- Theme Toggle -->
          <div class="px-3 py-2">
            <div class="flex items-center justify-between rounded-lg bg-surface-secondary p-1">
              <button
                v-for="opt in themeOptions"
                :key="opt.value"
                :class="[
                  'flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors',
                  currentTheme === opt.value
                    ? 'bg-surface text-heading shadow-sm'
                    : 'text-muted hover:text-body',
                ]"
                @click.stop="setTheme(opt.value)"
              >
                <BaseIcon :name="opt.icon" class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <router-link to="/admin/user-settings">
            <BaseDropdownItem>
              <BaseIcon
                name="CogIcon"
                class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
                aria-hidden="true"
              />
              {{ $t('navigation.settings') }}
            </BaseDropdownItem>
          </router-link>

          <BaseDropdownItem @click="logout">
            <BaseIcon
              name="ArrowRightOnRectangleIcon"
              class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
              aria-hidden="true"
            />
            {{ $t('navigation.logout') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </li>
    </ul>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../../stores/auth.store'
import { useUserStore } from '../../../stores/user.store'
import { useGlobalStore } from '../../../stores/global.store'
import { useCompanyStore } from '../../../stores/company.store'
import { useTheme } from '../../../composables/use-theme'
import { ABILITIES } from '../../../config/abilities'
import { THEME } from '../../../config/constants'
import type { Theme } from '../../../config/constants'
import CompanySwitcher from './CompanySwitcher.vue'
import GlobalSearchBar from './GlobalSearchBar.vue'
import MainLogo from '../../../components/icons/MainLogo.vue'

interface ThemeOption {
  value: Theme
  icon: string
}

const authStore = useAuthStore()
const userStore = useUserStore()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()
const router = useRouter()
const { currentTheme, setTheme } = useTheme()

const previewAvatar = computed<string>(() => {
  if (userStore.currentUser && userStore.currentUser.avatar !== 0) {
    return userStore.currentUser.avatar as string
  }
  return getDefaultAvatar()
})

const adminLogo = computed<string | false>(() => {
  if (globalStore.globalSettings?.admin_portal_logo) {
    return '/storage/' + globalStore.globalSettings.admin_portal_logo
  }
  return false
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

function onToggle(): void {
  globalStore.setSidebarVisibility(true)
}

const themeOptions: ThemeOption[] = [
  { value: THEME.LIGHT, icon: 'SunIcon' },
  { value: THEME.DARK, icon: 'MoonIcon' },
  { value: THEME.SYSTEM, icon: 'ComputerDesktopIcon' },
]
</script>
