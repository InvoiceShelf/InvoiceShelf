<template>
  <div
    class="
      flex min-h-screen items-center justify-center bg-surface-tertiary px-4 py-12
      sm:px-6 lg:px-8
    "
  >
    <NotificationRoot />

    <div class="w-full max-w-md">
      <div class="mb-10 flex justify-center">
        <MainLogo
          v-if="!customerLogo"
          class="block h-auto w-44 max-w-full text-primary-500"
        />

        <img
          v-else
          :src="customerLogo"
          class="block h-auto w-44 max-w-full"
        />
      </div>

      <div class="rounded-2xl border border-line-default bg-surface px-6 py-8 shadow-sm sm:px-8">
        <div class="mb-8 text-left">
          <h1 class="text-2xl font-semibold tracking-tight text-heading">
            {{ pageTitle }}
          </h1>
          <p class="mt-2 text-sm leading-6 text-muted">
            {{ pageDescription }}
          </p>
        </div>

        <router-view />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import NotificationRoot from '@v2/components/notifications/NotificationRoot.vue'
import MainLogo from '@v2/components/icons/MainLogo.vue'

declare global {
  interface Window {
    customer_logo?: string
    customer_page_title?: string
  }
}

const route = useRoute()
const { t } = useI18n()

const customerLogo = computed<string | false>(() => {
  return window.customer_logo || false
})

const pageTitle = computed<string>(() => {
  if (route.name === 'customer-portal.forgot-password') {
    return t('login.forgot_password')
  }

  if (route.name === 'customer-portal.reset-password') {
    return t('login.reset_password')
  }

  return window.customer_page_title || t('customers.portal_access')
})

const pageDescription = computed<string>(() => {
  if (route.name === 'customer-portal.forgot-password') {
    return t('login.enter_email')
  }

  if (route.name === 'customer-portal.reset-password') {
    return t('customers.portal_access_text')
  }

  return t('customers.portal_access_text')
})
</script>
