<template>
  <BaseWizardStep
    :title="$t('wizard.account_info')"
    :description="$t('wizard.account_info_desc')"
  >
    <form @submit.prevent="next">
      <div class="grid grid-cols-1 mb-4 md:grid-cols-2 md:mb-6">
        <BaseInputGroup :label="$t('settings.account_settings.profile_picture')">
          <BaseFileUploader
            :avatar="true"
            :preview-image="avatarUrl"
            @change="onFileInputChange"
            @remove="onFileInputRemove"
          />
        </BaseInputGroup>
      </div>

      <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2 md:mb-6">
        <BaseInputGroup
          :label="$t('wizard.name')"
          :error="v$.name.$error ? String(v$.name.$errors[0]?.$message) : undefined"
          required
        >
          <BaseInput
            v-model.trim="userForm.name"
            :invalid="v$.name.$error"
            type="text"
            name="name"
            @input="v$.name.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('wizard.email')"
          :error="v$.email.$error ? String(v$.email.$errors[0]?.$message) : undefined"
          required
        >
          <BaseInput
            v-model.trim="userForm.email"
            :invalid="v$.email.$error"
            type="text"
            name="email"
            @input="v$.email.$touch()"
          />
        </BaseInputGroup>
      </div>

      <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2">
        <BaseInputGroup
          :label="$t('wizard.password')"
          :error="v$.password.$error ? String(v$.password.$errors[0]?.$message) : undefined"
          required
        >
          <BaseInput
            v-model.trim="userForm.password"
            :invalid="v$.password.$error"
            :type="isShowPassword ? 'text' : 'password'"
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

        <BaseInputGroup
          :label="$t('wizard.confirm_password')"
          :error="v$.confirm_password.$error ? String(v$.confirm_password.$errors[0]?.$message) : undefined"
          required
        >
          <BaseInput
            v-model.trim="userForm.confirm_password"
            :invalid="v$.confirm_password.$error"
            :type="isShowConfirmPassword ? 'text' : 'password'"
            name="confirm_password"
            @input="v$.confirm_password.$touch()"
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
      </div>

      <BaseButton :loading="isSaving" :disabled="isSaving" class="mt-4">
        <template #left="slotProps">
          <BaseIcon v-if="!isSaving" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('wizard.save_cont') }}
      </BaseButton>
    </form>
  </BaseWizardStep>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  helpers,
  required,
  requiredIf,
  sameAs,
  minLength,
  email,
} from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { client } from '../../../api/client'

interface UserForm {
  name: string
  email: string
  password: string
  confirm_password: string
}

interface Emits {
  (e: 'next', step: number): void
}

const emit = defineEmits<Emits>()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const isShowPassword = ref<boolean>(false)
const isShowConfirmPassword = ref<boolean>(false)
const avatarUrl = ref<string>('')
const avatarFileBlob = ref<File | null>(null)

const userForm = reactive<UserForm>({
  name: '',
  email: '',
  password: '',
  confirm_password: '',
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
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.password_min_length', { count: 8 }),
      minLength(8),
    ),
  },
  confirm_password: {
    required: helpers.withMessage(
      t('validation.required'),
      requiredIf(() => !!userForm.password),
    ),
    sameAsPassword: helpers.withMessage(
      t('validation.password_incorrect'),
      sameAs(computed(() => userForm.password)),
    ),
  },
}))

const v$ = useVuelidate(rules, userForm)

function onFileInputChange(_fileName: string, file: File): void {
  avatarFileBlob.value = file
}

function onFileInputRemove(): void {
  avatarFileBlob.value = null
}

async function next(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true

  try {
    const { data: res } = await client.put('/api/v1/me', userForm)

    if (res.data) {
      if (avatarFileBlob.value) {
        const avatarData = new FormData()
        avatarData.append('admin_avatar', avatarFileBlob.value)
        await client.post('/api/v1/me/upload-avatar', avatarData)
      }

      const company = res.data.companies?.[0]
      if (company) {
        localStorage.setItem('selectedCompany', String(company.id))
      }

      emit('next', 6)
    }
  } finally {
    isSaving.value = false
  }
}
</script>
