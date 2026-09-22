<template>
  <!-- Tablet and desktop. Phones reach the same menu through the tab bar's More sheet. -->
  <aside
    :class="[isRail ? 'w-16' : 'w-64']"
    class="
      fixed inset-y-0 left-0 z-30 hidden md:flex flex-col
      bg-chrome text-chrome-fg safe-header
      transition-[width] duration-200
    "
  >
    <div
      :class="[isRail ? 'justify-center px-0' : 'px-5']"
      class="flex items-center h-14 shrink-0"
    >
      <router-link
        :to="homeLink"
        class="flex items-center rounded-md focus-visible:outline-2"
        :aria-label="$t('navigation.dashboard')"
      >
        <img
          v-if="adminLogo && !isRail"
          :src="adminLogo"
          alt=""
          class="object-contain w-auto h-7 max-w-44"
        />
        <MainLogoMark v-else-if="isRail" class="h-7 w-7 text-chrome-fg" />
        <MainLogo v-else class="w-auto h-7" light-color="var(--color-chrome-fg)" />
      </router-link>
    </div>

    <div :class="[isRail ? 'flex justify-center px-0' : 'px-3']" class="pb-2">
      <CompanySwitcher :variant="isRail ? 'rail' : 'sidebar'" tone="chrome" />
    </div>

    <nav class="flex-1 min-h-0 pb-4 overflow-x-hidden overflow-y-auto">
      <div
        v-for="(menu, index) in globalStore.menuGroups"
        :key="index"
        :class="[isRail ? 'px-3' : 'px-3', index === 0 ? 'mt-2' : 'mt-5']"
      >
        <template v-if="menu[0] && menu[0].group_label">
          <p
            v-if="showGroupLabels && !isRail"
            class="px-2.5 pb-1.5 text-xs font-medium text-chrome-muted whitespace-nowrap"
          >
            {{ $t(menu[0].group_label) }}
          </p>
          <div
            v-else-if="isRail && index > 0"
            class="mx-2 mb-3 border-t border-chrome-line"
          />
        </template>

        <ul class="space-y-0.5">
          <li v-for="item in menu" :key="item.name">
            <router-link
              v-tooltip="isRail ? { content: $t(item.title), placement: 'right' } : null"
              :to="item.link"
              :aria-current="hasActiveUrl(item.link) ? 'page' : undefined"
              :class="[
                hasActiveUrl(item.link)
                  ? 'bg-chrome-active text-chrome-fg before:absolute before:left-0 before:top-2 before:bottom-2 before:w-0.5 before:rounded-full before:bg-chrome-accent'
                  : 'text-chrome-muted hover:bg-chrome-hover hover:text-chrome-fg',
                isRail ? 'justify-center w-10 h-10' : 'gap-3 px-2.5 h-9',
              ]"
              class="relative flex items-center text-sm font-medium transition-colors rounded-lg group"
            >
              <BaseIcon
                :name="item.icon"
                :class="[
                  hasActiveUrl(item.link)
                    ? 'text-chrome-accent'
                    : 'text-chrome-muted group-hover:text-chrome-fg',
                  isRail ? 'h-5.5 w-5.5' : 'h-5 w-5',
                ]"
                class="shrink-0"
              />
              <span v-if="!isRail" class="truncate">{{ $t(item.title) }}</span>
            </router-link>
          </li>
        </ul>
      </div>
    </nav>

    <div
      v-if="isDesktop"
      :class="[isRail ? 'justify-center' : 'justify-end px-3']"
      class="flex py-2 border-t border-chrome-line safe-rail"
    >
      <button
        v-tooltip="isRail ? { content: $t('general.expand'), placement: 'right' } : null"
        type="button"
        class="flex items-center justify-center w-9 h-9 transition-colors rounded-lg text-chrome-muted hover:text-chrome-fg hover:bg-chrome-hover"
        :aria-label="isRail ? $t('general.expand') : $t('general.collapse')"
        @click="globalStore.toggleSidebarCollapse()"
      >
        <BaseIcon
          :name="isRail ? 'ChevronDoubleRightIcon' : 'ChevronDoubleLeftIcon'"
          class="w-4 h-4"
        />
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import { useActiveMenuLink } from '@/scripts/composables/use-active-menu-link'
import { assetUrl } from '@/scripts/config/runtime'
import MainLogo from '@/scripts/components/icons/MainLogo.vue'
import MainLogoMark from '@/scripts/components/icons/MainLogoMark.vue'
import CompanySwitcher from './CompanySwitcher.vue'

const route = useRoute()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()
const { isDesktop } = useBreakpoints()
const { hasActiveUrl } = useActiveMenuLink(route)

// Tablets always get the rail; desktops follow the collapse preference.
const isRail = computed<boolean>(() => {
  return !isDesktop.value || globalStore.isSidebarCollapsed
})

const homeLink = computed<string>(() => {
  return companyStore.isAdminMode ? '/admin/administration/dashboard' : '/admin/dashboard'
})

const showGroupLabels = computed<boolean>(() => {
  return globalStore.globalSettings?.show_sidebar_group_labels === 'YES'
})

const adminLogo = computed<string | false>(() => {
  if (globalStore.globalSettings?.admin_portal_logo) {
    return assetUrl('/storage/' + globalStore.globalSettings.admin_portal_logo)
  }
  return false
})
</script>
