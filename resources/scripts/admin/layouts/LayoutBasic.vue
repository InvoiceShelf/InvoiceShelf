<template>
  <div v-if="isAppLoaded" class="h-full">
    <NotificationRoot />

    <ImpersonationBanner />

    <SiteHeader />

    <SiteSidebar v-if="hasCompany" />

    <ExchangeRateBulkUpdateModal />

    <main
      :class="[
        'h-screen h-screen-ios overflow-y-auto min-h-0',
        hasCompany ? 'md:pl-56 xl:pl-64' : '',
      ]"
    >
      <div class="pt-16 pb-16">
        <router-view />
      </div>
    </main>
  </div>

  <BaseGlobalLoader v-else />
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useModalStore } from '@/scripts/stores/modal'
import { useExchangeRateStore } from '@/scripts/admin/stores/exchange-rate'
import { useCompanyStore } from '@/scripts/admin/stores/company'

import SiteHeader from '@/scripts/admin/layouts/partials/TheSiteHeader.vue'
import SiteSidebar from '@/scripts/admin/layouts/partials/TheSiteSidebar.vue'
import NotificationRoot from '@/scripts/components/notifications/NotificationRoot.vue'
import ExchangeRateBulkUpdateModal from '@/scripts/admin/components/modal-components/ExchangeRateBulkUpdateModal.vue'
import ImpersonationBanner from '@/scripts/admin/components/ImpersonationBanner.vue'

const globalStore = useGlobalStore()
const route = useRoute()
const userStore = useUserStore()
const router = useRouter()
const modalStore = useModalStore()
const { t } = useI18n()
const exchangeRateStore = useExchangeRateStore()
const companyStore = useCompanyStore()

const isAppLoaded = computed(() => {
  return globalStore.isAppLoaded
})

const hasCompany = computed(() => {
  return !!companyStore.selectedCompany || !!userStore.currentUser?.is_super_admin
})

onMounted(() => {
  globalStore.bootstrap().then((res) => {
    if (!res.data.current_company && !res.data.current_user.is_super_admin) {
      if (route.name !== 'no.company') {
        router.push({ name: 'no.company' })
      }
      return
    }

    if (route.meta.ability && !userStore.hasAbilities(route.meta.ability)) {
      router.push({ name: 'account.settings' })
    } else if (route.meta.isSuperAdmin && !userStore.currentUser.is_super_admin) {
      router.push({ name: 'dashboard' })
    } else if (route.meta.isOwner && !userStore.currentUser.is_owner) {
      router.push({ name: 'account.settings' })
    }

    if (
      res.data.current_company_settings.bulk_exchange_rate_configured === 'NO'
    ) {
      exchangeRateStore.fetchBulkCurrencies().then((res) => {
        if (res.data.currencies.length) {
          modalStore.openModal({
            componentName: 'ExchangeRateBulkUpdateModal',
            size: 'sm',
          })
        } else {
          let data = {
            settings: {
              bulk_exchange_rate_configured: 'YES',
            },
          }
          companyStore.updateCompanySettings({
            data,
          })
        }
      })
    }
  })
})
</script>
