<template>
  <form id="loginForm" class="mt-12 text-start" @submit.prevent="onSubmit">
    <BaseInputGroup
      :error="v$.email.$error && v$.email.$errors[0].$message"
      :label="$t('login.email')"
      class="mb-4"
      required
    >
      <BaseInput
        v-model="authStore.loginData.email"
        autocomplete="username"
        :invalid="v$.email.$error"
        focus
        type="email"
        name="email"
        @input="v$.email.$touch()"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :error="v$.password.$error && v$.password.$errors[0].$message"
      :label="$t('login.password')"
      class="mb-4"
      required
    >
      <BaseInput
        v-model="authStore.loginData.password"
        autocomplete="current-password"
        :invalid="v$.password.$error"
        type="password"
        revealable
        name="password"
        @input="v$.password.$touch()"
      />
    </BaseInputGroup>

    <div class="mt-5 mb-8">
      <div class="mb-4">
        <router-link
          to="forgot-password"
          class="text-sm text-primary-600 hover:text-body"
        >
          {{ $t('login.forgot_password') }}
        </router-link>
      </div>
    </div>

    <div class="flex gap-3">
      <BaseButton :loading="isLoading" type="submit" class="flex-1 justify-center">
        {{ $t('login.login') }}
      </BaseButton>

      <!-- Styled like the Login button, in white (its outline keeps it visible on the white card) -->
      <BaseButton
        v-if="ssoLogin"
        tag="a"
        :href="ssoHref"
        variant="white"
        class="flex-1 justify-center"
      >
        {{ $t('login.sign_in_with_sso') }}
      </BaseButton>
    </div>

    <p v-if="ssoError" class="mt-3 text-sm text-danger" role="alert">
      {{ ssoError }}
    </p>

    <div
      v-if="demo"
      role="note"
      class="p-4 mt-8 text-sm border rounded-lg border-line-default bg-surface-secondary text-body"
    >
      <p class="font-medium text-heading">{{ $t('demo.login_title') }}</p>
      <p class="mt-1">{{ $t('demo.login_note', { email: demo.email, password: demo.password }) }}</p>
      <template v-if="demo.portal_path">
        <a :href="demo.portal_path" class="inline-flex mt-3 font-medium text-primary-600 hover:underline">
          {{ $t('demo.try_portal') }}
        </a>
        <p class="mt-1 text-muted">
          {{ $t('demo.portal_note', { email: demo.portal_email, password: demo.portal_password }) }}
        </p>
      </template>
    </div>
  </form>
</template>

<script setup lang="ts">
import { demoState } from '@/scripts/utils/demo'
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { required, email, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../../stores/auth.store'
import { useNotificationStore } from '../../../stores/notification.store'

declare global {
  interface Window {
    demo_mode?: boolean
    sso_login?: { url: string; error: string | null }
  }
}

const notificationStore = useNotificationStore()
const authStore = useAuthStore()
const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const isLoading = ref<boolean>(false)

// Server-rendered pages a sign-in may return to. Keep in step with
// PostLoginRedirect::SERVER_PATHS (app/Domains/Accounts/Application).
const SERVER_REDIRECT_PATHS = ['/oauth/authorize']

function isServerRedirectPath(path: string): boolean {
  if (path.includes('\\')) {
    return false
  }

  return SERVER_REDIRECT_PATHS.some(
    (prefix) => path === prefix || path.startsWith(`${prefix}?`)
  )
}

const rules = {
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  password: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}

// Single sign-on, when the server has it set up (SsoLogin::clientState()).
// The button carries `next` along; the server checks it with PostLoginRedirect.
const ssoLogin = window.sso_login
const ssoError = ref<string | null>(null)

const SSO_ERRORS: Readonly<Record<string, string>> = {
  failed: 'login.sso_failed',
  no_account: 'login.sso_no_account',
  email_unverified: 'login.sso_email_unverified',
}

const ssoHref = computed<string>(() => {
  const next = typeof route.query.next === 'string' ? route.query.next : ''

  return next !== '' ? `${ssoLogin?.url}?next=${encodeURIComponent(next)}` : (ssoLogin?.url ?? '')
})

const v$ = useVuelidate(
  rules,
  computed(() => authStore.loginData)
)

async function onSubmit(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isLoading.value = true

  try {
    await authStore.login(authStore.loginData)

    // Honour a ?next= query param if present (set by the 401 response
    // interceptor so users land back on the page they were trying to
    // reach). Sanitize to same-origin paths only — reject protocol-
    // relative (`//evil.com`) and absolute URLs so a crafted link
    // can never open-redirect.
    const nextRaw = typeof route.query.next === 'string' ? route.query.next : ''
    const safeNext =
      nextRaw.startsWith('/') && !nextRaw.startsWith('//')
        ? nextRaw
        : '/admin/dashboard'

    // Pages the server renders itself, such as the OAuth consent screen, are
    // not SPA routes: load them with a full navigation.
    if (isServerRedirectPath(safeNext)) {
      window.location.assign(safeNext)
      return
    }

    router.push(safeNext)

    notificationStore.showNotification({
      type: 'success',
      message: 'Logged in successfully.',
    })
  } catch (err: unknown) {
    const { handleApiError } = await import('../../../utils/error-handling')
    const normalized = handleApiError(err)
    notificationStore.showNotification({
      type: 'error',
      message: normalized.message,
    })
    isLoading.value = false
  }
}

const demo = demoState()

onMounted(() => {
  const error = ssoLogin?.error ? SSO_ERRORS[ssoLogin.error] : undefined

  if (error) {
    ssoError.value = t(error)
  }

  // The error belongs to the sign-in that just came back, not to later visits
  if (ssoLogin) {
    ssoLogin.error = null
  }

  if (demo) {
    authStore.loginData.email = demo.email
    authStore.loginData.password = demo.password
  }
})
</script>
