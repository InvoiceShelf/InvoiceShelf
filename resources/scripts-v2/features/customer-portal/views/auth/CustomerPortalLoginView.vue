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
        :type="inputType"
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

    <div class="flex items-center justify-between">
      <router-link
        :to="{
          name: 'customer-portal.forgot-password',
          params: { company: companySlug },
        }"
        class="text-sm text-primary-500 hover:text-heading"
      >
        {{ $t('login.forgot_password') }}
      </router-link>
    </div>

    <BaseButton
      :disabled="isLoading"
      :loading="isLoading"
      class="w-full justify-center"
      type="submit"
    >
      <template #left="slotProps">
        <BaseIcon name="LockClosedIcon" :class="slotProps.class" />
      </template>
      {{ $t('login.login') }}
    </BaseButton>
  </form>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useVuelidate } from '@vuelidate/core'
import { email, helpers, required } from '@vuelidate/validators'
import { useNotificationStore } from '@v2/stores/notification.store'
import { getErrorTranslationKey, handleApiError } from '@v2/utils/error-handling'
import { useCustomerPortalStore } from '../../store'
import { resolveCompanySlug } from '../../utils/routes'

interface CustomerPortalLoginForm {
  email: string
  password: string
}

const store = useCustomerPortalStore()
const notificationStore = useNotificationStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const formData = reactive<CustomerPortalLoginForm>({
  email: '',
  password: '',
})

const isLoading = ref<boolean>(false)
const isShowPassword = ref<boolean>(false)

const companySlug = computed<string>(() => {
  return resolveCompanySlug(route.params.company)
})

const inputType = computed<string>(() => {
  return isShowPassword.value ? 'text' : 'password'
})

const rules = computed(() => ({
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  password: {
    required: helpers.withMessage(t('validation.required'), required),
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
    await store.login({
      ...formData,
      company: companySlug.value,
      device_name: 'customer-portal-web',
    })
    await store.bootstrap(companySlug.value)

    notificationStore.showNotification({
      type: 'success',
      message: t('general.login_successfully'),
    })

    await router.push({
      name: 'customer-portal.dashboard',
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
