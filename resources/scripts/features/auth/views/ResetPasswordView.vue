<template>
  <form id="loginForm" @submit.prevent="onSubmit">
    <BaseInputGroup
      :error="errorEmail"
      :label="$t('login.email')"
      class="mb-4"
      required
    >
      <BaseInput
        v-model="formData.email"
        :invalid="v$.email.$error"
        focus
        type="email"
        name="email"
        @input="v$.email.$touch()"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :error="errorPassword"
      :label="$t('login.password')"
      class="mb-4"
      required
    >
      <BaseInput
        v-model="formData.password"
        :invalid="v$.password.$error"
        type="password"
        name="password"
        @input="v$.password.$touch()"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :error="errorConfirmPassword"
      :label="$t('login.retype_password')"
      class="mb-4"
      required
    >
      <BaseInput
        v-model="formData.password_confirmation"
        :invalid="v$.password_confirmation.$error"
        type="password"
        name="password_confirmation"
        @input="v$.password_confirmation.$touch()"
      />
    </BaseInputGroup>

    <BaseButton :loading="isLoading" type="submit" variant="primary" class="w-full justify-center">
      {{ $t('login.reset_password') }}
    </BaseButton>
  </form>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import useVuelidate from '@vuelidate/core'
import { required, email, minLength, sameAs } from '@vuelidate/validators'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../../stores/auth.store'
import { useNotificationStore } from '../../../stores/notification.store'
import { handleApiError } from '../../../utils/error-handling'

interface ResetPasswordForm {
  email: string
  password: string
  password_confirmation: string
}

const notificationStore = useNotificationStore()
const authStore = useAuthStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const formData = reactive<ResetPasswordForm>({
  email: '',
  password: '',
  password_confirmation: '',
})

const isLoading = ref<boolean>(false)

const rules = computed(() => ({
  email: { required, email },
  password: {
    required,
    minLength: minLength(8),
  },
  password_confirmation: {
    sameAsPassword: sameAs(formData.password),
  },
}))

const v$ = useVuelidate(rules, formData)

const errorEmail = computed<string>(() => {
  if (!v$.value.email.$error) {
    return ''
  }
  if (v$.value.email.required.$invalid) {
    return t('validation.required')
  }
  if (v$.value.email.email) {
    return t('validation.email_incorrect')
  }
  return ''
})

const errorPassword = computed<string>(() => {
  if (!v$.value.password.$error) {
    return ''
  }
  if (v$.value.password.required.$invalid) {
    return t('validation.required')
  }
  if (v$.value.password.minLength) {
    return t('validation.password_min_length', {
      count: v$.value.password.minLength.$params.min,
    })
  }
  return ''
})

const errorConfirmPassword = computed<string>(() => {
  if (!v$.value.password_confirmation.$error) {
    return ''
  }
  if (v$.value.password_confirmation.sameAsPassword.$invalid) {
    return t('validation.password_incorrect')
  }
  return ''
})

async function onSubmit(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  try {
    isLoading.value = true

    await authStore.resetPassword({
      email: formData.email,
      password: formData.password,
      password_confirmation: formData.password_confirmation,
      token: route.params.token as string,
    })

    notificationStore.showNotification({
      type: 'success',
      message: t('login.password_reset_successfully'),
    })

    router.push('/login')
  } catch (err: unknown) {
    const normalized = handleApiError(err)
    notificationStore.showNotification({
      type: 'error',
      message: normalized.message,
    })
  } finally {
    isLoading.value = false
  }
}
</script>
