<template>
  <div v-if="isAppLoaded" class="h-full">
    <NotificationRoot />
    <CustomerPortalHeader />
    <main class="mt-16 pb-16 h-screen overflow-y-auto min-h-0">
      <router-view />
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCustomerPortalStore } from '../store'
import { resolveCompanySlug } from '../utils/routes'
import NotificationRoot from '@v2/components/notifications/NotificationRoot.vue'
import CustomerPortalHeader from './CustomerPortalHeader.vue'

const store = useCustomerPortalStore()
const route = useRoute()
const router = useRouter()

const isAppLoaded = computed<boolean>(() => store.isAppLoaded)

watch(
  () => route.params.company,
  async (companyParam) => {
    const companySlug = resolveCompanySlug(companyParam)

    if (!companySlug) {
      return
    }

    if (store.isAppLoaded && store.companySlug === companySlug) {
      return
    }

    try {
      await store.bootstrap(companySlug)
    } catch {
      await router.push({
        name: 'customer-portal.login',
        params: { company: companySlug },
      })
    }
  },
  { immediate: true }
)
</script>
