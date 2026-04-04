<template>
  <div class="flex items-center justify-center min-h-screen bg-surface-secondary">
    <div class="w-full max-w-md p-8">
      <!-- Logo -->
      <div class="mb-8 text-center">
        <MainLogo
          v-if="!loginPageLogo"
          class="inline-block w-48 h-auto text-primary-500"
        />
        <img
          v-else
          :src="loginPageLogo"
          class="inline-block w-48 h-auto"
        />
      </div>

      <!-- Loading -->
      <div v-if="isLoading" class="text-center">
        <p class="text-muted">Loading invitation details...</p>
      </div>

      <!-- Invalid/Expired -->
      <div v-else-if="error" class="text-center">
        <BaseIcon
          name="ExclamationCircleIcon"
          class="w-16 h-16 mx-auto text-red-400 mb-4"
        />
        <h1 class="text-xl font-semibold text-heading mb-2">
          Invalid Invitation
        </h1>
        <p class="text-muted">{{ error }}</p>
        <router-link to="/login" class="text-primary-500 mt-4 inline-block">
          Go to Login
        </router-link>
      </div>

      <!-- Registration Form -->
      <div v-else>
        <div class="text-center mb-6">
          <h1 class="text-2xl font-semibold text-heading">
            Create Your Account
          </h1>
          <p class="text-muted mt-2">
            You've been invited to join
            <strong>{{ invitationDetails.company_name }}</strong> as
            <strong>{{ invitationDetails.role_name }}</strong>
          </p>
        </div>

        <BaseCard class="p-6">
          <form @submit.prevent="submitRegistration">
            <div class="space-y-4">
              <BaseInputGroup
                label="Name"
                :error="v$.name.$error && v$.name.$errors[0].$message"
                required
              >
                <BaseInput
                  v-model="form.name"
                  :invalid="v$.name.$error"
                  @input="v$.name.$touch()"
                />
              </BaseInputGroup>

              <BaseInputGroup label="Email">
                <BaseInput
                  v-model="form.email"
                  type="email"
                  disabled
                />
              </BaseInputGroup>

              <BaseInputGroup
                label="Password"
                :error="v$.password.$error && v$.password.$errors[0].$message"
                required
              >
                <BaseInput
                  v-model="form.password"
                  type="password"
                  :invalid="v$.password.$error"
                  @input="v$.password.$touch()"
                />
              </BaseInputGroup>

              <BaseInputGroup
                label="Confirm Password"
                :error="
                  v$.password_confirmation.$error &&
                  v$.password_confirmation.$errors[0].$message
                "
                required
              >
                <BaseInput
                  v-model="form.password_confirmation"
                  type="password"
                  :invalid="v$.password_confirmation.$error"
                  @input="v$.password_confirmation.$touch()"
                />
              </BaseInputGroup>
            </div>

            <BaseButton
              :loading="isSubmitting"
              :disabled="isSubmitting"
              class="w-full mt-6"
              type="submit"
            >
              Create Account & Join
            </BaseButton>
          </form>
        </BaseCard>

        <div class="text-center mt-4">
          <router-link to="/login" class="text-sm text-muted hover:text-primary-500">
            Already have an account? Log in
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { helpers, required, minLength, sameAs } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import http from '@/scripts/http'
import MainLogo from '@/scripts/components/icons/MainLogo.vue'

const route = useRoute()
const router = useRouter()

const loginPageLogo = computed(() => {
  return window.login_page_logo || false
})

const isLoading = ref(true)
const isSubmitting = ref(false)
const error = ref(null)
const invitationDetails = ref({})

const form = reactive({
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
  computed(() => form)
)

const token = computed(() => route.query.invitation)

onMounted(async () => {
  if (!token.value) {
    error.value = 'No invitation token provided.'
    isLoading.value = false
    return
  }

  try {
    const response = await http.get(`/api/v1/invitations/${token.value}/details`)
    invitationDetails.value = response.data
    form.email = response.data.email
  } catch (e) {
    error.value = 'This invitation is invalid or has expired.'
  } finally {
    isLoading.value = false
  }
})

async function submitRegistration() {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSubmitting.value = true
  try {
    const response = await http.post('/api/v1/auth/register-with-invitation', {
      name: form.name,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
      invitation_token: token.value,
    })

    // Store the token and redirect
    window.Ls.set('auth.token', response.data.token)
    router.push('/admin/dashboard')
  } catch (e) {
    // Validation errors handled by http interceptor
  } finally {
    isSubmitting.value = false
  }
}
</script>
