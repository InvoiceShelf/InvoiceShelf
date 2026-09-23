<template>
  <form id="loginForm" class="mt-12 text-left" @submit.prevent="onSubmit">
    <BaseInputGroup
      :error="v$.email.$error && v$.email.$errors[0].$message"
      :label="$t('login.email')"
      class="mb-4"
      required
    >
      <BaseInput
        v-model="authStore.loginData.email"
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
        :invalid="v$.password.$error"
        :type="inputType"
        name="password"
        @input="v$.password.$touch()"
      >
        <template #right>
          <BaseIcon
            :name="isShowPassword ? 'EyeIcon' : 'EyeSlashIcon'"
            class="mr-1 text-muted cursor-pointer"
            @click="isShowPassword = !isShowPassword"
          />
        </template>
      </BaseInput>
    </BaseInputGroup>

    <div class="mt-5 mb-8">
      <div class="mb-4">
        <router-link
          to="forgot-password"
          class="text-sm text-primary-400 hover:text-body"
        >
          {{ $t('login.forgot_password') }}
        </router-link>
      </div>
    </div>

    <BaseButton :loading="isLoading" type="submit" class="w-full justify-center">
      {{ $t('login.login') }}
    </BaseButton>
  </form>
</template>

<script setup lang="ts">
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
  }
}

const notificationStore = useNotificationStore()
const authStore = useAuthStore()
const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const isLoading = ref<boolean>(false)
const isShowPassword = ref<boolean>(false)

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

const v$ = useVuelidate(
  rules,
  computed(() => authStore.loginData)
)

const inputType = computed<string>(() => {
  return isShowPassword.value ? 'text' : 'password'
})

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

onMounted(() => {
  if (window.demo_mode) {
    authStore.loginData.email = 'demo@invoiceshelf.com'
    authStore.loginData.password = 'demo'
  }
})
</script>
