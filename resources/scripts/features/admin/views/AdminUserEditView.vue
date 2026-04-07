<template>
  <BasePage v-if="!isLoading">
    <BasePageHeader :title="$t('administration.users.edit_user')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('administration.users.title')"
          to="/admin/administration/users"
        />
        <BaseBreadcrumbItem
          :title="$t('administration.users.edit_user')"
          to="#"
          active
        />
      </BaseBreadcrumb>
    </BasePageHeader>

    <form @submit.prevent="submitForm">
      <BaseCard class="mt-6">
        <BaseInputGrid class="mt-5">
          <BaseInputGroup
            :label="$t('users.name')"
            :error="v$.name.$error && v$.name.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="formData.name"
              :invalid="v$.name.$error"
              @blur="v$.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('general.email')"
            :error="v$.email.$error && v$.email.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="formData.email"
              type="email"
              :invalid="v$.email.$error"
              @blur="v$.email.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('users.phone')">
            <BaseInput v-model="formData.phone" type="text" />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('users.password')"
            :error="v$.password.$error && v$.password.$errors[0].$message"
          >
            <BaseInput
              v-model="formData.password"
              type="password"
              :invalid="v$.password.$error"
              autocomplete="new-password"
              @blur="v$.password.$touch()"
            />
          </BaseInputGroup>
        </BaseInputGrid>

        <div v-if="userData" class="mt-6 text-sm text-muted">
          <p>
            <strong>{{ $t('administration.users.role') }}:</strong>
            {{ userData.role }}
          </p>
          <p v-if="userData.companies && userData.companies.length" class="mt-1">
            <strong>{{ $t('navigation.companies') }}:</strong>
            {{ userData.companies.map((c) => c.name).join(', ') }}
          </p>
        </div>

        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          type="submit"
          class="mt-6"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              :class="slotProps.class"
              name="ArrowDownOnSquareIcon"
            />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </BaseCard>
    </form>
  </BasePage>

  <BaseGlobalLoader v-else />
</template>

<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { required, email, minLength, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useAdminStore } from '../stores/admin.store'
import type { User } from '../../../types/domain/user'

interface UserFormData {
  name: string
  email: string
  phone: string
  password: string
}

const route = useRoute()
const router = useRouter()
const adminStore = useAdminStore()
const { t } = useI18n()

const isLoading = ref<boolean>(true)
const isSaving = ref<boolean>(false)
const userData = ref<User | null>(null)

const formData = reactive<UserFormData>({
  name: '',
  email: '',
  phone: '',
  password: '',
})

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  password: {
    minLength: helpers.withMessage(
      t('validation.password_min_length', { count: 8 }),
      minLength(8)
    ),
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => formData)
)

onMounted(async () => {
  const response = await adminStore.fetchUser(route.params.id as string)
  const user = response.data
  userData.value = user

  formData.name = user.name
  formData.email = user.email
  formData.phone = user.phone ?? ''

  isLoading.value = false
})

async function submitForm(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true

  const data: Record<string, string> = {
    name: formData.name,
    email: formData.email,
    phone: formData.phone,
  }

  if (formData.password) {
    data.password = formData.password
  }

  await adminStore.updateUser(route.params.id as string, data)

  isSaving.value = false

  router.push({ name: 'admin.users.index' })
}
</script>
