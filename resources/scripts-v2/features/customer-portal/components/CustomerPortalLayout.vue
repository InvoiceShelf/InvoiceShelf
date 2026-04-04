<template>
  <div v-if="isAppLoaded" class="h-full">
    <slot name="header" />
    <main class="mt-16 pb-16 h-screen overflow-y-auto min-h-0">
      <router-view />
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useCustomerPortalStore } from '../store'

const store = useCustomerPortalStore()
const route = useRoute()

const isAppLoaded = computed<boolean>(() => store.isAppLoaded)

onMounted(async () => {
  const companySlug = route.params.company as string
  if (companySlug && !store.isAppLoaded) {
    await store.bootstrap(companySlug)
  }
})
</script>
