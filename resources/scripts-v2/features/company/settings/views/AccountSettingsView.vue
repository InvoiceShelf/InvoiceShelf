<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, email, sameAs, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useUserStore } from '../../../../stores/user.store'

const userStore = useUserStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)

const userForm = computed(() => userStore.userForm)

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(t('validation.name_min_length'), minLength(3)),
  },
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  password: {
    minLength: helpers.withMessage(t('validation.password_min_length'), minLength(8)),
  },
  confirm_password: {
    sameAsPassword: helpers.withMessage(
      t('validation.password_incorrect'),
      sameAs(userForm.value.password)
    ),
  },
}))

const v$ = useVuelidate(rules, userForm)

async function updateAccount(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true
  try {
    await userStore.updateCurrentUser({
      name: userForm.value.name,
      email: userForm.value.email,
      password: userForm.value.password || undefined,
      confirm_password: userForm.value.confirm_password || undefined,
    })
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="updateAccount">
    <BaseSettingCard
      :title="$t('settings.account_settings.account_settings')"
      :description="$t('settings.account_settings.section_description')"
    >
      <BaseInputGrid class="mt-5">
        <BaseInputGroup
          :label="$t('settings.account_settings.name')"
          :error="v$.name.$error && v$.name.$errors[0]?.$message"
          required
        >
          <BaseInput
            v-model="userForm.name"
            :invalid="v$.name.$error"
            @blur="v$.name.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.account_settings.email')"
          :error="v$.email.$error && v$.email.$errors[0]?.$message"
          required
        >
          <BaseInput
            v-model="userForm.email"
            type="email"
            :invalid="v$.email.$error"
            @blur="v$.email.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.account_settings.password')"
          :error="v$.password.$error && v$.password.$errors[0]?.$message"
        >
          <BaseInput
            v-model="userForm.password"
            type="password"
            :invalid="v$.password.$error"
            @blur="v$.password.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.account_settings.confirm_password')"
          :error="
            v$.confirm_password.$error &&
            v$.confirm_password.$errors[0]?.$message
          "
        >
          <BaseInput
            v-model="userForm.confirm_password"
            type="password"
            :invalid="v$.confirm_password.$error"
            @blur="v$.confirm_password.$touch()"
          />
        </BaseInputGroup>
      </BaseInputGrid>

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
        {{ $t('settings.account_settings.save') }}
      </BaseButton>
    </BaseSettingCard>
  </form>
</template>
