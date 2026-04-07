<template>
  <!-- Loading -->
  <div v-if="isLoading" class="mt-12 text-center">
    <BaseSpinner class="w-8 h-8 text-primary-400 mx-auto" />
    <p class="text-muted mt-4 text-sm">Loading invitation details...</p>
  </div>

  <!-- Invalid/Expired -->
  <div v-else-if="error" class="mt-12 text-center">
    <BaseIcon
      name="ExclamationCircleIcon"
      class="w-12 h-12 mx-auto text-red-400 mb-4"
    />
    <h2 class="text-lg font-semibold text-heading mb-2">
      Invalid Invitation
    </h2>
    <p class="text-sm text-muted mb-4">{{ error }}</p>
    <router-link
      to="/login"
      class="text-sm text-primary-400 hover:text-primary-500"
    >
      Go to Login
    </router-link>
  </div>

  <!-- Registration Form -->
  <div v-else class="mt-12">
    <div class="mb-8">
      <h1 class="text-2xl font-semibold text-heading">
        Create Your Account
      </h1>
      <p class="text-sm text-muted mt-2">
        You've been invited to join
        <strong class="text-heading">{{ invitationDetails.company_name }}</strong>
        as <strong class="text-heading">{{ invitationDetails.role_name }}</strong>
      </p>
    </div>

    <form @submit.prevent="submitRegistration">
      <BaseInputGroup
        label="Name"
        :error="v$.name.$error && v$.name.$errors[0].$message"
        class="mb-4"
        required
      >
        <BaseInput
          v-model="form.name"
          :invalid="v$.name.$error"
          focus
          @input="v$.name.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup label="Email" class="mb-4">
        <BaseInput
          v-model="form.email"
          type="email"
          disabled
        />
      </BaseInputGroup>

      <BaseInputGroup
        label="Password"
        :error="v$.password.$error && v$.password.$errors[0].$message"
        class="mb-4"
        required
      >
        <BaseInput
          v-model="form.password"
          :type="isShowPassword ? 'text' : 'password'"
          :invalid="v$.password.$error"
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

      <BaseInputGroup
        label="Confirm Password"
        :error="
          v$.password_confirmation.$error &&
          v$.password_confirmation.$errors[0].$message
        "
        class="mb-4"
        required
      >
        <BaseInput
          v-model="form.password_confirmation"
          :type="isShowConfirmPassword ? 'text' : 'password'"
          :invalid="v$.password_confirmation.$error"
          @input="v$.password_confirmation.$touch()"
        >
          <template #right>
            <BaseIcon
              :name="isShowConfirmPassword ? 'EyeIcon' : 'EyeSlashIcon'"
              class="mr-1 text-muted cursor-pointer"
              @click="isShowConfirmPassword = !isShowConfirmPassword"
            />
          </template>
        </BaseInput>
      </BaseInputGroup>

      <div class="mt-5 mb-8">
        <router-link
          to="/login"
          class="text-sm text-primary-400 hover:text-body"
        >
          Already have an account? Log in
        </router-link>
      </div>

      <BaseButton
        :loading="isSubmitting"
        :disabled="isSubmitting"
        type="submit"
        class="w-full justify-center"
      >
        Create Account & Join
      </BaseButton>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { helpers, required, minLength, sameAs } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { authService } from '../../../api/services/auth.service'

interface InvitationDetailsData {
  email: string
  company_name: string
  role_name: string
}

interface RegistrationForm {
  name: string
  email: string
  password: string
  password_confirmation: string
}

const route = useRoute()
const router = useRouter()

const isLoading = ref<boolean>(true)
const isSubmitting = ref<boolean>(false)
const isShowPassword = ref<boolean>(false)
const isShowConfirmPassword = ref<boolean>(false)
const error = ref<string | null>(null)
const invitationDetails = ref<InvitationDetailsData>({
  email: '',
  company_name: '',
  role_name: '',
})

const form = reactive<RegistrationForm>({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const rules = computed(() => ({
  name: {
    required: helpers.withMessage('Name is required', required),
  },
  password: {
    required: helpers.withMessage('Password is required', required),
    minLength: helpers.withMessage('Password must be at least 8 characters', minLength(8)),
  },
  password_confirmation: {
    required: helpers.withMessage('Please confirm your password', required),
    sameAs: helpers.withMessage('Passwords do not match', sameAs(form.password)),
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => form),
)

const token = computed<string>(() => route.query.invitation as string)

onMounted(async () => {
  if (!token.value) {
    error.value = 'No invitation token provided.'
    isLoading.value = false
    return
  }

  try {
    const details = await authService.getInvitationDetails(token.value) as unknown as InvitationDetailsData
    invitationDetails.value = {
      email: details.email,
      company_name: details.company_name,
      role_name: details.role_name,
    }
    form.email = details.email
  } catch {
    error.value = 'This invitation is invalid or has expired.'
  } finally {
    isLoading.value = false
  }
})

async function submitRegistration(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSubmitting.value = true

  try {
    const response = await authService.registerWithInvitation({
      name: form.name,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
      invitation_token: token.value,
    })

    // Save the auth token before navigating (matching old version's pattern)
    localStorage.setItem('auth.token', `Bearer ${response.token}`)

    router.push('/admin/dashboard')
  } catch (err: unknown) {
    const { handleApiError } = await import('../../../utils/error-handling')
    const { useNotificationStore } = await import('../../../stores/notification.store')
    const normalized = handleApiError(err)
    const notificationStore = useNotificationStore()
    notificationStore.showNotification({
      type: 'error',
      message: normalized.message,
    })
  } finally {
    isSubmitting.value = false
  }
}
</script>
