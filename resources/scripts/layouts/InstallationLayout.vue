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

      <!-- Wizard card — same visual language as AuthLayout / BaseCard -->
      <article
        class="
          w-full max-w-3xl
          bg-surface
          rounded-xl
          border border-line-default
          shadow-sm
          backdrop-blur-sm
          px-8 py-10 sm:px-10 sm:py-12
        "
      >
        <!-- Step progress indicator -->
        <div
          v-if="totalSteps > 0"
          class="mb-8 flex items-center justify-center gap-2"
        >
          <span
            v-for="step in totalSteps"
            :key="step"
            :class="[
              'h-2 rounded-full transition-all duration-300',
              step === currentStep
                ? 'w-8 bg-primary-500'
                : step < currentStep
                  ? 'w-2 bg-primary-500'
                  : 'w-2 bg-line-default',
            ]"
          />
        </div>

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
    login_page_logo?: string
    copyright_text?: string
  }
}

const route = useRoute()

/**
 * Step ordinal lookup. Each child route's `meta.installStep` is its 1-based
 * position in the wizard. The progress dots in the layout react to this.
 */
const STEP_ORDER = [
  'installation.language',
  'installation.requirements',
  'installation.permissions',
  'installation.database',
  'installation.domain',
  'installation.mail',
  'installation.account',
  'installation.company',
  'installation.preferences',
]

const totalSteps = computed<number>(() => STEP_ORDER.length)

const currentStep = computed<number>(() => {
  const name = route.name?.toString() ?? ''
  const idx = STEP_ORDER.indexOf(name)
  return idx >= 0 ? idx + 1 : 1
})

const copyrightText = computed<string | null>(() => window.copyright_text ?? null)

const loginPageLogo = computed<string | false>(() => {
  if (window.login_page_logo) return window.login_page_logo
  return false
})
</script>
