<template>
  <div class="mt-10 text-start">
    <!-- No server yet: point the app at one. -->
    <form v-if="state.status === 'no-server'" @submit.prevent="onConnect">
      <h2 class="text-base font-medium text-heading">
        {{ $t('client.connect_heading') }}
      </h2>
      <p class="mt-1 mb-6 text-sm text-muted">
        {{ $t('client.connect_description') }}
      </p>

      <BaseInputGroup
        :label="$t('client.server_address')"
        :error="connectError"
        required
      >
        <!-- The server address is this screen's only question, so it starts focused -->
        <!-- eslint-disable vuejs-accessibility/no-autofocus -->
        <BaseInput
          v-model="serverUrlInput"
          :invalid="Boolean(connectError)"
          type="text"
          name="server_url"
          autofocus
          autocapitalize="none"
          autocorrect="off"
          spellcheck="false"
          placeholder="https://books.example.com"
        />
        <!-- eslint-enable vuejs-accessibility/no-autofocus -->
      </BaseInputGroup>

      <ul
        v-if="warnings.length"
        class="
          mt-4 space-y-1 rounded-md bg-alert-warning-bg px-4 py-3 text-sm
          text-alert-warning-text
        "
      >
        <li v-for="warning in warnings" :key="warning">
          {{ warning }}
        </li>
      </ul>

      <BaseButton
        :loading="isBusy"
        :disabled="isBusy"
        type="submit"
        class="mt-6 w-full justify-center"
      >
        {{ warnings.length ? $t('client.connect_anyway') : $t('client.connect') }}
      </BaseButton>
    </form>

    <!-- A server is stored but this app cannot work against it. -->
    <div v-else-if="state.status !== 'ready'">
      <h2 class="text-base font-medium text-heading">
        {{ blockedHeading }}
      </h2>
      <p class="mt-2 text-sm text-muted">
        {{ blockedDescription }}
      </p>
      <p v-if="state.error" class="mt-2 text-sm text-subtle">
        {{ state.error }}
      </p>

      <BaseButton
        v-if="state.status === 'unreachable'"
        type="button"
        class="mt-6 w-full justify-center"
        @click="onRetry"
      >
        {{ $t('client.retry') }}
      </BaseButton>

      <button
        type="button"
        class="
          mt-4 w-full text-sm text-primary-600 transition-colors
          hover:text-body
        "
        @click="onChangeServer"
      >
        {{ $t('client.change_server') }}
      </button>
    </div>

    <!-- Connected: the ordinary credentials form. -->
    <form v-else id="loginForm" @submit.prevent="onSubmit">
      <ul
        v-if="readyWarnings.length"
        class="
          mb-6 space-y-1 rounded-md bg-alert-warning-bg px-4 py-3 text-sm
          text-alert-warning-text
        "
      >
        <li v-for="warning in readyWarnings" :key="warning">
          {{ warning }}
        </li>
      </ul>

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
          type="email"
          name="email"
          autocapitalize="none"
          autocorrect="off"
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
        <router-link
          :to="{ name: 'forgot-password' }"
          class="text-sm text-primary-600 hover:text-body"
        >
          {{ $t('login.forgot_password') }}
        </router-link>
      </div>

      <div class="flex gap-3">
        <BaseButton
          :loading="isBusy"
          :disabled="isSsoBusy"
          type="submit"
          class="flex-1 justify-center"
        >
          {{ $t('login.login') }}
        </BaseButton>

        <!-- Styled like the Login button, in white (its outline keeps it visible on the white card) -->
        <BaseButton
          v-if="state.manifest?.oauth2_login"
          :loading="isSsoBusy"
          :disabled="isBusy || isSsoBusy"
          type="button"
          variant="white"
          class="flex-1 justify-center"
          @click="onSignInWithSso"
        >
          {{ $t('login.sign_in_with_sso') }}
        </BaseButton>
      </div>

      <div
        class="
          mt-8 border-t border-line-light pt-4 text-center text-xs text-muted
        "
      >
        <p class="break-all">
          {{ $t('client.connected_to', { server: state.serverUrl }) }}
        </p>
        <button
          type="button"
          class="mt-1 text-primary-600 transition-colors hover:text-body"
          @click="onChangeServer"
        >
          {{ $t('client.change_server') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { demoState } from '@/scripts/utils/demo'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { email, helpers, required } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useI18n } from 'vue-i18n'
import { clientState } from '@/scripts/client/state'
import {
  appUrlMismatch,
  fetchClientManifest,
  gateManifest,
  ManifestError,
} from '@/scripts/client/manifest'
import {
  CLIENT_VERSION,
  MIN_SERVER_VERSION,
  clearServerBaseUrl,
  normalizeServerUrl,
  setServerBaseUrl,
} from '@/scripts/config/runtime'
import { flushClientState } from '@/scripts/utils/local-storage'
import { SsoSignInError, useAuthStore } from '@/scripts/stores/auth.store'
import type { SsoSignInFailure } from '@/scripts/stores/auth.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'

/**
 * The client's login screen.
 *
 * On the web the server is wherever the page came from, so signing in is one
 * form. A client has to be told which server it belongs to first, and the
 * answer decides what this screen can even offer: connect, retry, update, or
 * credentials.
 */

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const notificationStore = useNotificationStore()

const state = clientState

const serverUrlInput = ref<string>('')
const connectError = ref<string>('')
const warnings = ref<string[]>([])
const acknowledged = ref<string>('')
const isBusy = ref<boolean>(false)
const isSsoBusy = ref<boolean>(false)

/** What a sign-in with SSO that ended without a token says. */
const SSO_FAILURES: Readonly<Record<SsoSignInFailure, string>> = {
  cancelled: 'login.oauth2_cancelled',
  no_account: 'login.oauth2_no_account',
  failed: 'login.oauth2_failed',
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

const v$ = useVuelidate(
  rules,
  computed(() => authStore.loginData)
)

const blockedHeading = computed<string>(() => {
  switch (state.status) {
    case 'too-old':
      return t('client.too_old_heading')
    case 'update-app':
      return t('client.update_app_heading')
    default:
      return t('client.unreachable_heading')
  }
})

const blockedDescription = computed<string>(() => {
  switch (state.status) {
    case 'too-old':
      return t('client.too_old_description', {
        version: state.manifest?.version ?? '',
        required: MIN_SERVER_VERSION,
      })
    case 'update-app':
      return t('client.update_app_description', {
        required: state.manifest?.min_client_version ?? '',
        current: CLIENT_VERSION,
      })
    default:
      return t('client.unreachable_description', { server: state.serverUrl })
  }
})

/** Warnings worth keeping in front of the user after connecting. */
const readyWarnings = computed<string[]>(() => {
  const collected: string[] = []

  if (state.appUrlMismatch) {
    collected.push(t('client.app_url_mismatch', { host: state.appUrlMismatch }))
  }

  if (state.moduleErrors.length > 0) {
    collected.push(t('client.modules_failed', { names: state.moduleErrors.join(', ') }))
  }

  return collected
})

/**
 * Probe the typed address, show anything the user should see before trusting
 * it, then store it and reload so modules load with page-load semantics.
 */
async function onConnect(): Promise<void> {
  connectError.value = ''

  const serverUrl = normalizeServerUrl(serverUrlInput.value)

  if (serverUrl === '') {
    warnings.value = []
    connectError.value = t('client.invalid_server_url')

    return
  }

  // Second press on the same address: the warnings have been read.
  if (warnings.value.length > 0 && acknowledged.value === serverUrl) {
    await connectTo(serverUrl)

    return
  }

  warnings.value = []
  isBusy.value = true

  try {
    const manifest = await fetchClientManifest(serverUrl)
    const verdict = gateManifest(manifest)

    if (verdict === 'too-old') {
      connectError.value = t('client.too_old_description', {
        version: manifest.version,
        required: MIN_SERVER_VERSION,
      })

      return
    }

    if (verdict === 'update-app') {
      connectError.value = t('client.update_app_description', {
        required: manifest.min_client_version,
        current: CLIENT_VERSION,
      })

      return
    }

    const found: string[] = []

    if (serverUrl.toLowerCase().startsWith('http://')) {
      found.push(t('client.insecure_warning'))
    }

    const mismatch = appUrlMismatch(serverUrl, manifest.app_url)

    if (mismatch !== null) {
      found.push(t('client.app_url_mismatch', { host: mismatch }))
    }

    if (found.length > 0) {
      warnings.value = found
      acknowledged.value = serverUrl

      return
    }

    await connectTo(serverUrl)
  } catch (error: unknown) {
    connectError.value =
      error instanceof ManifestError && error.failure === 'not-found'
        ? t('client.too_old_short')
        : t('client.unreachable_description', { server: serverUrl })
  } finally {
    isBusy.value = false
  }
}

async function connectTo(serverUrl: string): Promise<void> {
  setServerBaseUrl(serverUrl)
  // The reload must not outrun the write it depends on.
  await flushClientState()
  window.location.reload()
}

function onRetry(): void {
  window.location.reload()
}

/**
 * Forget the server, keep the token: it belongs to that server, and a user
 * who comes back to it should not have to sign in again.
 */
async function onChangeServer(): Promise<void> {
  clearServerBaseUrl()
  await flushClientState()
  window.location.reload()
}

async function onSubmit(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isBusy.value = true

  try {
    await authStore.login(authStore.loginData)

    continueSignedIn()
  } catch (err: unknown) {
    const { handleApiError } = await import('@/scripts/utils/error-handling')
    const normalized = handleApiError(err)
    notificationStore.showNotification({
      type: 'error',
      message: normalized.message,
    })
    isBusy.value = false
  }
}

/**
 * Sign in in the system browser through the server's SSO
 * (`authStore.loginWithSso`). A cancel says so and leaves the form as it was.
 */
async function onSignInWithSso(): Promise<void> {
  isSsoBusy.value = true

  try {
    await authStore.loginWithSso()

    continueSignedIn()
  } catch (err: unknown) {
    const reason: SsoSignInFailure = err instanceof SsoSignInError ? err.reason : 'failed'

    notificationStore.showNotification({
      type: reason === 'cancelled' ? 'info' : 'error',
      message: t(SSO_FAILURES[reason]),
    })
  } finally {
    isSsoBusy.value = false
  }
}

/**
 * Go on once a token is stored, by either way of signing in.
 *
 * Honour a ?next= query param if present (set by the 401 response
 * interceptor so users land back on the page they were trying to reach).
 * Sanitize to in-app paths only: reject protocol-relative (`//evil.com`) and
 * absolute URLs so a crafted link can never open-redirect.
 */
function continueSignedIn(): void {
  const nextRaw = typeof route.query.next === 'string' ? route.query.next : ''
  const safeNext =
    nextRaw.startsWith('/') && !nextRaw.startsWith('//')
      ? nextRaw
      : '/admin/dashboard'

  router.push(safeNext)

  notificationStore.showNotification({
    type: 'success',
    message: 'Logged in successfully.',
  })
}

onMounted(() => {
  serverUrlInput.value = state.serverUrl

  const demo = demoState()

  if (demo) {
    authStore.loginData.email = demo.email
    authStore.loginData.password = demo.password
  }
})
</script>
