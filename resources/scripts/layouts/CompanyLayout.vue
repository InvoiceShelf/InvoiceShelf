<template>
  <!-- The ambient canvas sits behind everything; glass surfaces pick it up -->
  <div v-if="isAppLoaded" class="flex h-dvh bg-ambient isolate">
    <!-- First stop for keyboard users: past the navigation to the page -->
    <a
      href="#main-content"
      class="
        sr-only focus:not-sr-only focus:fixed focus:top-3 focus:start-3 focus:z-60 focus:px-4 focus:py-2.5
        focus:rounded-xl focus:bg-surface focus:text-heading focus:font-medium focus:shadow-lg
        focus:outline-2 focus:outline-focus
      "
      @click.prevent="focusMain"
    >
      {{ $t('general.skip_to_content') }}
    </a>

    <NotificationRoot />

    <SiteSidebar v-if="hasCompany" />

    <div
      :class="[
        'flex flex-col flex-1 min-w-0 h-dvh',
        hasCompany ? (isExpanded ? 'md:ps-16 lg:ps-64' : 'md:ps-16') : '',
      ]"
    >
      <ImpersonationBanner />

      <!--
        The top bar lives inside the scrolling area so content passes under
        it; the bottom inset keeps the last rows clear of the phone's
        floating tab bar or action bar. The header and the page are siblings,
        so the header is the page's banner rather than part of its content.
      -->
      <div
        id="app-scroll"
        class="relative flex-1 min-h-0 overflow-y-auto overscroll-contain"
        :style="{ paddingBottom: 'var(--app-bottom-inset)' }"
      >
        <SiteHeader />
        <main id="main-content" tabindex="-1" class="focus:outline-hidden">
          <router-view />
        </main>
      </div>
    </div>

    <!-- BaseActionBar teleports a page's phone actions here -->
    <div id="app-action-bar" class="fixed inset-x-0 bottom-0 z-30" />

    <MobileTabBar v-if="showTabBar" />

    <CommandPalette />

    <ExtensionSlot name="company-layout-overlays" />
  </div>

  <BaseGlobalLoader v-else />
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { focusMain } from '@/scripts/utils/page-focus'
import { onMounted, onUnmounted, computed, watch, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import SiteHeader from './partials/SiteHeader.vue'
import SiteSidebar from './partials/SiteSidebar.vue'
import NotificationRoot from '@/scripts/components/notifications/NotificationRoot.vue'
import ImpersonationBanner from './partials/ImpersonationBanner.vue'
import MobileTabBar from './partials/MobileTabBar.vue'
import CommandPalette from './partials/CommandPalette.vue'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import ExtensionSlot from '@/scripts/extensions/ExtensionSlot.vue'

interface RouteMeta {
  ability?: string | string[]
  isSuperAdmin?: boolean
  isOwner?: boolean
  usesAdminBootstrap?: boolean
}

const globalStore = useGlobalStore()
const route = useRoute()
const userStore = useUserStore()
const router = useRouter()
const modalStore = useModalStore()
const { t } = useI18n()
const companyStore = useCompanyStore()

const isAppLoaded = computed<boolean>(() => {
  return globalStore.isAppLoaded
})

const hasCompany = computed<boolean>(() => {
  return !!companyStore.selectedCompany || companyStore.isAdminMode
})

const { isPhone, isDesktop } = useBreakpoints()

// Tablets always get the rail; desktops follow the collapse preference.
const isExpanded = computed<boolean>(() => {
  return isDesktop.value && !globalStore.isSidebarCollapsed
})

// A page's own sticky action bar takes the tab bar's place.
const showTabBar = computed<boolean>(() => {
  return isPhone.value && hasCompany.value && globalStore.actionBarCount === 0
})

// Publish how much of the viewport the fixed chrome covers, for overlays and
// modules (documented in resources/css/invoiceshelf.css).
watchEffect(() => {
  const root = document.documentElement
  // The top bar: 3.5rem, plus the status bar area on phones
  root.style.setProperty(
    '--app-top-inset',
    isPhone.value ? 'calc(3.5rem + env(safe-area-inset-top))' : 'calc(3.5rem + 1px)',
  )
  let bottom = 'env(safe-area-inset-bottom)'

  if (showTabBar.value) {
    // The floating tab bar: 3.75rem tall, 0.5rem above the home indicator, plus breathing room
    bottom = 'calc(5rem + env(safe-area-inset-bottom))'
  } else if (isPhone.value && globalStore.actionBarCount > 0) {
    bottom = 'calc(4.5rem + env(safe-area-inset-bottom))'
  }

  root.style.setProperty('--app-bottom-inset', bottom)
})

onUnmounted(() => {
  document.documentElement.style.removeProperty('--app-top-inset')
  document.documentElement.style.removeProperty('--app-bottom-inset')
})

const usesAdminBootstrap = computed<boolean>(() => {
  return route.meta.usesAdminBootstrap === true
})

async function initializeLayout(): Promise<void> {
  const meta = route.meta as RouteMeta
  const res = await globalStore.bootstrap({
    adminMode: meta.usesAdminBootstrap === true,
  })

  if (res.admin_mode === true) {
    return
  }

  if (!res.current_company) {
    if (route.name !== 'no.company') {
      router.push({ name: 'no.company' })
    }
    return
  }

  if (meta.ability && !userStore.hasAbilities(meta.ability as string | string[])) {
    router.push({ name: 'settings.account' })
  } else if (meta.isSuperAdmin && !userStore.currentUser?.is_super_admin) {
    router.push({ name: 'dashboard' })
  } else if (meta.isOwner && !userStore.currentUser?.is_owner) {
    router.push({ name: 'settings.account' })
  }

  if (
    companyStore.selectedCompanySettings.bulk_exchange_rate_configured === 'NO'
  ) {
    modalStore.openModal({
      componentName: 'ExchangeRateBulkUpdateModal',
      title: t('exchange_rates.bulk_update'),
      size: 'sm',
    })
  }
}

onMounted(() => {
  void initializeLayout()
})

watch(usesAdminBootstrap, (isAdminBootstrap, previousValue) => {
  if (previousValue !== undefined && isAdminBootstrap !== previousValue) {
    void initializeLayout()
  }
})
</script>
