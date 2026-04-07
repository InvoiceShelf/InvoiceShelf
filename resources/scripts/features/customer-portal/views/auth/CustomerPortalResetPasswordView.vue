<template>
  <form class="space-y-6" @submit.prevent="onSubmit">
    <BaseInputGroup
      :error="v$.email.$error ? String(v$.email.$errors[0]?.$message) : undefined"
      :label="$t('login.email')"
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

    <BaseInputGroup
      :error="v$.password.$error ? String(v$.password.$errors[0]?.$message) : undefined"
      :label="$t('login.password')"
      required
    >
      <BaseInput
        v-model="formData.password"
        :invalid="v$.password.$error"
        :type="passwordInputType"
        name="password"
        @input="v$.password.$touch()"
      >
        <template #right>
          <BaseIcon
            :name="isShowPassword ? 'EyeIcon' : 'EyeSlashIcon'"
            class="mr-1 cursor-pointer text-muted"
            @click="isShowPassword = !isShowPassword"
          />
        </template>
      </BaseInput>
    </BaseInputGroup>

    <BaseInputGroup
      :error="v$.password_confirmation.$error ? String(v$.password_confirmation.$errors[0]?.$message) : undefined"
      :label="$t('login.retype_password')"
      required
    >
      <BaseInput
        v-model="formData.password_confirmation"
        :invalid="v$.password_confirmation.$error"
        :type="confirmPasswordInputType"
        name="password_confirmation"
        @input="v$.password_confirmation.$touch()"
      >
        <template #right>
          <BaseIcon
            :name="isShowConfirmPassword ? 'EyeIcon' : 'EyeSlashIcon'"
            class="mr-1 cursor-pointer text-muted"
            @click="isShowConfirmPassword = !isShowConfirmPassword"
          />
        </template>
      </BaseInput>
    </BaseInputGroup>

    <BaseButton
      :disabled="isLoading"
      :loading="isLoading"
      class="w-full justify-center"
      type="submit"
    >
      {{ $t('login.reset_password') }}
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
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useVuelidate } from '@vuelidate/core'
import { email, helpers, minLength, required, sameAs } from '@vuelidate/validators'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import { useCustomerPortalStore } from '../../store'
import { resolveCompanySlug } from '../../utils/routes'

interface CustomerPortalResetPasswordForm {
  email: string
  password: string
  password_confirmation: string
}

const store = useCustomerPortalStore()
const notificationStore = useNotificationStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const formData = reactive<CustomerPortalResetPasswordForm>({
  email: '',
  password: '',
  password_confirmation: '',
})

const isLoading = ref<boolean>(false)
const isShowPassword = ref<boolean>(false)
const isShowConfirmPassword = ref<boolean>(false)

const companySlug = computed<string>(() => {
  return resolveCompanySlug(route.params.company)
})

const passwordInputType = computed<string>(() => {
  return isShowPassword.value ? 'text' : 'password'
})

const confirmPasswordInputType = computed<string>(() => {
  return isShowConfirmPassword.value ? 'text' : 'password'
})

const rules = computed(() => ({
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  password: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.password_min_length', { count: 8 }),
      minLength(8),
    ),
  },
  password_confirmation: {
    sameAsPassword: helpers.withMessage(
      t('validation.password_incorrect'),
      sameAs(formData.password),
    ),
  },
}))

const v$ = useVuelidate(rules, formData)

async function onSubmit(): Promise<void> {
  v$.value.$touch()

  if (
    v$.value.$invalid ||
    !companySlug.value ||
    typeof route.params.token !== 'string'
  ) {
    return
  }

  isLoading.value = true

  try {
    await store.resetPassword(
      {
        email: formData.email,
        password: formData.password,
        password_confirmation: formData.password_confirmation,
        token: route.params.token,
      },
      companySlug.value,
    )

    notificationStore.showNotification({
      type: 'success',
      message: t('login.password_reset_successfully'),
    })

    await router.push({
      name: 'customer-portal.login',
      params: { company: companySlug.value },
    })
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
