<template>
  <form class="space-y-6" @submit.prevent="onSubmit">
    <BaseInputGroup
      :error="v$.email.$error ? String(v$.email.$errors[0]?.$message) : undefined"
      :label="$t('login.enter_email')"
      required
    >
      <BaseInput
        v-model="formData.email"
        :invalid="v$.email.$error"
        focus
        name="email"
        type="email"
        @input="v$.email.$touch()"
      />
    </BaseInputGroup>

    <BaseButton
      :disabled="isLoading"
      :loading="isLoading"
      class="w-full justify-center"
      type="submit"
    >
      {{ isSent ? $t('validation.not_yet') : $t('validation.send_reset_link') }}
    </BaseButton>

    <router-link
      :to="{
        name: 'customer-portal.login',
        params: { company: companySlug },
      }"
      class="inline-block text-sm text-primary-500 hover:text-heading"
    >
      {{ $t('general.back_to_login') }}
    </router-link>
  </form>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useVuelidate } from '@vuelidate/core'
import { email, helpers, required } from '@vuelidate/validators'
import { useNotificationStore } from '@v2/stores/notification.store'
import { getErrorTranslationKey, handleApiError } from '@v2/utils/error-handling'
import { useCustomerPortalStore } from '../../store'
import { resolveCompanySlug } from '../../utils/routes'

interface CustomerPortalForgotPasswordForm {
  email: string
}

const store = useCustomerPortalStore()
const notificationStore = useNotificationStore()
const route = useRoute()
const { t } = useI18n()

const formData = reactive<CustomerPortalForgotPasswordForm>({
  email: '',
})

const isSent = ref<boolean>(false)
const isLoading = ref<boolean>(false)

const companySlug = computed<string>(() => {
  return resolveCompanySlug(route.params.company)
})

const rules = computed(() => ({
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
}))

const v$ = useVuelidate(rules, formData)

async function onSubmit(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid || !companySlug.value) {
    return
  }

  isLoading.value = true

  try {
    await store.forgotPassword({
      email: formData.email,
      company: companySlug.value,
    })

    notificationStore.showNotification({
      type: 'success',
      message: t('general.send_mail_successfully'),
    })

    isSent.value = true
  } catch (err: unknown) {
    showErrorNotification(err)
  } finally {
    isLoading.value = false
  }
}

function showErrorNotification(err: unknown): void {
  const normalizedError = handleApiError(err)
  const translationKey = getErrorTranslationKey(normalizedError.message)

  notificationStore.showNotification({
    type: 'error',
    message: translationKey ? t(translationKey) : normalizedError.message,
  })
}
</script>
