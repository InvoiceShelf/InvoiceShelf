<template>
  <div class="bg-glass-gradient relative min-h-screen w-full overflow-hidden">
    <NotificationRoot />

    <main
      class="
        relative flex min-h-screen flex-col items-center justify-center
        px-4 py-12 sm:px-6
      "
    >
      <!-- Logo above the card -->
      <div class="mb-8 flex justify-center">
        <MainLogo
          v-if="!loginPageLogo"
          class="h-12 w-auto text-primary-500"
        />
        <img
          v-else
          :src="loginPageLogo"
          alt="InvoiceShelf"
          class="h-12 w-auto"
        />
      </div>

      <!-- Auth card — same visual language as BaseCard -->
      <article
        class="
          w-full max-w-md
          bg-surface
          rounded-xl
          border border-line-default
          shadow-sm
          backdrop-blur-sm
          px-8 py-10 sm:px-10 sm:py-12
        "
      >
        <header class="text-center mb-8">
          <h1 class="text-2xl font-semibold text-heading">
            {{ heading }}
          </h1>
          <p class="mt-2 text-sm text-muted">
            {{ subheading }}
          </p>
        </header>

        <router-view />
      </article>

      <!-- Footer -->
      <footer class="mt-8 text-center text-xs text-subtle">
        <span v-if="copyrightText">{{ copyrightText }}</span>
        <span v-else>
          Powered by
          <a
            href="https://invoiceshelf.com/"
            target="_blank"
            rel="noopener noreferrer"
            class="text-primary-500 hover:text-primary-600 font-medium transition-colors"
          >InvoiceShelf</a>
        </span>
      </footer>
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import NotificationRoot from '@/scripts/components/notifications/NotificationRoot.vue'
import MainLogo from '@/scripts/components/icons/MainLogo.vue'

declare global {
  interface Window {
    login_page_heading?: string
    login_page_description?: string
    copyright_text?: string
    login_page_logo?: string
  }
}

const route = useRoute()

interface RouteCopy {
  heading: string
  subheading: string
}

const COPY: Record<string, RouteCopy> = {
  login: {
    heading: 'Welcome back',
    subheading: 'Sign in to continue to your account',
  },
  'forgot-password': {
    heading: 'Forgot your password?',
    subheading: 'Enter your email and we will send you a reset link',
  },
  'reset-password': {
    heading: 'Set a new password',
    subheading: 'Choose a strong password to secure your account',
  },
  'register-with-invitation': {
    heading: 'Create your account',
    subheading: 'Complete your registration to get started',
  },
}

const heading = computed<string>(() => {
  if (window.login_page_heading) return window.login_page_heading
  const name = route.name?.toString() ?? 'login'
  return COPY[name]?.heading ?? COPY.login.heading
})

const subheading = computed<string>(() => {
  if (window.login_page_description) return window.login_page_description
  const name = route.name?.toString() ?? 'login'
  return COPY[name]?.subheading ?? COPY.login.subheading
})

const copyrightText = computed<string | null>(() => window.copyright_text ?? null)

const loginPageLogo = computed<string | false>(() => {
  if (window.login_page_logo) return window.login_page_logo
  return false
})
</script>
