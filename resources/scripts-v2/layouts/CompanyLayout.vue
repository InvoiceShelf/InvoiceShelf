<template>
  <div v-if="isAppLoaded" class="h-full">
    <NotificationRoot />

    <ImpersonationBanner />

    <SiteHeader />

    <SiteSidebar v-if="hasCompany" />

    <main
      :class="[
        'h-screen h-screen-ios overflow-y-auto min-h-0 transition-all duration-300',
        hasCompany
          ? globalStore.isSidebarCollapsed
            ? 'md:pl-16'
            : 'md:pl-56 xl:pl-64'
          : '',
      ]"
    >
      <div class="pt-16 pb-16">
        <router-view />
      </div>
    </main>
  </div>

  <BaseGlobalLoader v-else />
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGlobalStore } from '@v2/stores/global.store'
import { useUserStore } from '@v2/stores/user.store'
import { useModalStore } from '@v2/stores/modal.store'
import { useCompanyStore } from '@v2/stores/company.store'
import SiteHeader from './partials/SiteHeader.vue'
import SiteSidebar from './partials/SiteSidebar.vue'
import NotificationRoot from '@v2/components/notifications/NotificationRoot.vue'
import ImpersonationBanner from './partials/ImpersonationBanner.vue'

interface RouteMeta {
  ability?: string | string[]
  isSuperAdmin?: boolean
  isOwner?: boolean
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

onMounted(() => {
  globalStore.bootstrap().then((res) => {
    if (companyStore.isAdminMode) {
      return
    }

    if (!res.current_company) {
      if (route.name !== 'no.company') {
        router.push({ name: 'no.company' })
      }
      return
    }

    const meta = route.meta as RouteMeta

    if (meta.ability && !userStore.hasAbilities(meta.ability as string | string[])) {
      router.push({ name: 'account.settings' })
    } else if (meta.isSuperAdmin && !userStore.currentUser?.is_super_admin) {
      router.push({ name: 'dashboard' })
    } else if (meta.isOwner && !userStore.currentUser?.is_owner) {
      router.push({ name: 'account.settings' })
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
  })
})
</script>
