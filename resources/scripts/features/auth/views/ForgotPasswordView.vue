<template>
  <form id="loginForm" @submit.prevent="onSubmit">
    <BaseInputGroup
      :error="v$.email.$error && v$.email.$errors[0].$message"
      :label="$t('login.enter_email')"
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

    <BaseButton
      :loading="isLoading"
      :disabled="isLoading"
      type="submit"
      variant="primary"
    >
      <div v-if="!isSent">
        {{ $t('validation.send_reset_link') }}
      </div>
      <div v-else>
        {{ $t('validation.not_yet') }}
      </div>
    </BaseButton>

    <div class="mt-4 mb-4 text-sm">
      <router-link
        to="/login"
        class="text-sm text-primary-400 hover:text-body"
      >
        {{ $t('general.back_to_login') }}
      </router-link>
    </div>
  </form>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { required, email, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../../stores/auth.store'
import { useNotificationStore } from '../../../stores/notification.store'
import { handleApiError } from '../../../utils/error-handling'

interface ForgotPasswordForm {
  email: string
}

const notificationStore = useNotificationStore()
const authStore = useAuthStore()
const { t } = useI18n()

const formData = reactive<ForgotPasswordForm>({
  email: '',
})

const isSent = ref<boolean>(false)
const isLoading = ref<boolean>(false)

const rules = {
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
}

const v$ = useVuelidate(rules, formData)

async function onSubmit(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  try {
    isLoading.value = true

    await authStore.forgotPassword({ email: formData.email })

    notificationStore.showNotification({
      type: 'success',
      message: 'Mail sent successfully',
    })

    isSent.value = true
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
