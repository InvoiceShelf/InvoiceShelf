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
        :type="getInputType"
        name="password"
        @input="v$.password.$touch()"
      >
        <template #right>
          <BaseIcon
            :name="isShowPassword ? 'EyeIcon' : 'EyeSlashIcon'"
            class="mr-1 text-gray-500 cursor-pointer"
            @click="isShowPassword = !isShowPassword"
          />
        </template>
      </BaseInput>
    </BaseInputGroup>

    <div class="mt-5 mb-8">
      <div class="mb-4">
        <router-link
          to="forgot-password"
          class="text-sm text-primary-400 hover:text-gray-700"
        >
          {{ $t('login.forgot_password') }}
        </router-link>
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-3">
      <BaseButton :loading="isLoading" type="submit">
        {{ $t('login.login') }}
      </BaseButton>

      <!-- Styled like the Login button, in white -->
      <BaseButton
        v-if="oauth2Login"
        tag="a"
        :href="oauth2Login.url"
        variant="white"
        class="!border-transparent shadow-xs focus:!ring-offset-2"
      >
        {{ $t('login.sign_in_with_sso') }}
      </BaseButton>
    </div>

    <p v-if="oauth2Error" class="mt-3 text-sm text-red-600" role="alert">
      {{ oauth2Error }}
    </p>
  </form>
</template>

<script setup>
import http from '@/scripts/http'
import { ref, computed, onMounted } from 'vue'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useRouter } from 'vue-router'
import { required, email, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/scripts/admin/stores/auth'
import { handleError } from '@/scripts/helpers/error-handling'

const notificationStore = useNotificationStore()
const authStore = useAuthStore()
const { t } = useI18n()
const router = useRouter()
const isLoading = ref(false)
let isShowPassword = ref(false)

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

const getInputType = computed(() => {
  if (isShowPassword.value) {
    return 'text'
  }
  return 'password'
})

// Sign-in with SSO, when the server has it set up (app/Support/OAuth2Login.php)
const oauth2Login = window.oauth2_login
const oauth2Errors = {
  failed: 'login.oauth2_failed',
  no_account: 'login.oauth2_no_account',
}
const oauth2Error = ref(null)

onMounted(() => {
  const error = oauth2Errors[oauth2Login?.error]

  if (error) {
    oauth2Error.value = t(error)
  }

  // The error belongs to the sign-in that just came back, not to later visits
  if (oauth2Login) {
    oauth2Login.error = null
  }
})

async function onSubmit() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return true
  }

  isLoading.value = true

  try {
    isLoading.value = true
    await authStore.login(authStore.loginData)

    router.push('/admin/dashboard')

    notificationStore.showNotification({
      type: 'success',
      message: 'Logged in successfully.',
    })
  } catch (error) {
    isLoading.value = false
  }
}

// Pre-fill demo credentials if in demo environment
onMounted(() => {
  if (window.demo_mode) {
    authStore.loginData.email = 'demo@invoiceshelf.com'
    authStore.loginData.password = 'demo'
  }
})
</script>
