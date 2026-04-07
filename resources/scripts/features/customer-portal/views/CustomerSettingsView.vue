<template>
  <BasePage>
    <BasePageHeader :title="$t('settings.account_settings.account_settings')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem
          :title="$t('general.home')"
          :to="`/${store.companySlug}/customer/dashboard`"
        />
        <BaseBreadcrumbItem
          :title="$t('settings.account_settings.account_settings')"
          to="#"
          active
        />
      </BaseBreadcrumb>
    </BasePageHeader>

    <form @submit.prevent="updateCustomerData">
      <BaseCard>
        <p
          class="text-sm leading-snug text-muted"
          style="max-width: 680px"
        >
          {{ $t('settings.account_settings.section_description') }}
        </p>

        <div class="grid gap-6 sm:grid-col-1 md:grid-cols-2 mt-6">
          <BaseInputGroup
            :label="$t('settings.account_settings.profile_picture')"
          >
            <BaseFileUploader
              v-model="imgFiles"
              :avatar="true"
              accept="image/*"
              @change="onFileInputChange"
              @remove="onFileInputRemove"
            />
          </BaseInputGroup>

          <span />

          <BaseInputGroup
            :label="$t('settings.account_settings.name')"
            :error="v$.name.$error ? String(v$.name.$errors[0]?.$message) : undefined"
            required
          >
            <BaseInput
              v-model="formData.name"
              :invalid="v$.name.$error"
              @input="v$.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.account_settings.email')"
            :error="v$.email.$error ? String(v$.email.$errors[0]?.$message) : undefined"
            required
          >
            <BaseInput
              v-model="formData.email"
              :invalid="v$.email.$error"
              @input="v$.email.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :error="v$.password.$error ? String(v$.password.$errors[0]?.$message) : undefined"
            :label="$t('settings.account_settings.password')"
          >
            <BaseInput
              v-model="formData.password"
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
            :label="$t('settings.account_settings.confirm_password')"
            :error="v$.confirm_password.$error ? String(v$.confirm_password.$errors[0]?.$message) : undefined"
          >
            <BaseInput
              v-model="formData.confirm_password"
              :type="isShowConfirmPassword ? 'text' : 'password'"
              :invalid="v$.confirm_password.$error"
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

        <BaseButton :loading="isSaving" :disabled="isSaving" class="mt-6">
          <template #left="slotProps">
            <BaseIcon v-if="!isSaving" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </BaseCard>
    </form>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  helpers,
  sameAs,
  email,
  required,
  minLength,
} from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useCustomerPortalStore } from '../store'

interface AvatarFile {
  image: string
}

const store = useCustomerPortalStore()
const { t, tm } = useI18n()

const imgFiles = ref<AvatarFile[]>([])
const isSaving = ref<boolean>(false)
const avatarFileBlob = ref<File | null>(null)
const isShowPassword = ref<boolean>(false)
const isShowConfirmPassword = ref<boolean>(false)
const isCustomerAvatarRemoved = ref<boolean>(false)

const formData = reactive<{
  name: string
  email: string
  password: string
  confirm_password: string
}>({
  name: store.userForm.name,
  email: store.userForm.email,
  password: '',
  confirm_password: '',
})

if (typeof store.userForm.avatar === 'string' && store.userForm.avatar) {
  imgFiles.value.push({ image: store.userForm.avatar })
}

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3),
    ),
  },
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  password: {
    minLength: helpers.withMessage(
      t('validation.password_min_length', { count: 8 }),
      minLength(8),
    ),
  },
  confirm_password: {
    sameAsPassword: helpers.withMessage(
      t('validation.password_incorrect'),
      sameAs(formData.password),
    ),
  },
}))

const v$ = useVuelidate(rules, formData)

function onFileInputChange(_fileName: string, file: File): void {
  avatarFileBlob.value = file
}

function onFileInputRemove(): void {
  avatarFileBlob.value = null
  isCustomerAvatarRemoved.value = true
}

async function updateCustomerData(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true

  const data = new FormData()
  data.append('name', formData.name)
  data.append('email', formData.email)

  if (formData.password) {
    data.append('password', formData.password)
  }

  if (avatarFileBlob.value) {
    data.append('customer_avatar', avatarFileBlob.value)
  }

  data.append('is_customer_avatar_removed', String(isCustomerAvatarRemoved.value))

  try {
    const res = await store.updateCurrentUser(data)
    if (res.data.data) {
      formData.password = ''
      formData.confirm_password = ''
      avatarFileBlob.value = null
      isCustomerAvatarRemoved.value = false
    }
  } finally {
    isSaving.value = false
  }
}
</script>
