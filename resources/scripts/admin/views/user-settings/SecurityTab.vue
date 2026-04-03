<template>
  <form @submit.prevent="updatePassword">
    <BaseSettingCard
      :title="$t('settings.account_settings.security')"
      :description="$t('settings.account_settings.security_description')"
    >
    <BaseInputGrid>
      <BaseInputGroup
        :label="$t('settings.account_settings.password')"
        :error="v$.password.$error && v$.password.$errors[0].$message"
      >
        <BaseInput
          v-model="form.password"
          type="password"
          @input="v$.password.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.account_settings.confirm_password')"
        :error="
          v$.confirm_password.$error &&
          v$.confirm_password.$errors[0].$message
        "
      >
        <BaseInput
          v-model="form.confirm_password"
          type="password"
          @input="v$.confirm_password.$touch()"
        />
      </BaseInputGroup>
    </BaseInputGrid>

    <BaseButton :loading="isSaving" :disabled="isSaving" class="mt-6">
      <template #left="slotProps">
        <BaseIcon
          v-if="!isSaving"
          name="ArrowDownOnSquareIcon"
          :class="slotProps.class"
        />
      </template>
      {{ $t('settings.company_info.save') }}
    </BaseButton>
    </BaseSettingCard>
  </form>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useI18n } from 'vue-i18n'
import { helpers, sameAs, minLength } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'

const userStore = useUserStore()
const { t } = useI18n()

const isSaving = ref(false)

const form = reactive({
  password: '',
  confirm_password: '',
})

const rules = computed(() => ({
  password: {
    minLength: helpers.withMessage(
      t('validation.password_length', { count: 8 }),
      minLength(8)
    ),
  },
  confirm_password: {
    sameAsPassword: helpers.withMessage(
      t('validation.password_incorrect'),
      sameAs(form.password)
    ),
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => form)
)

async function updatePassword() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  if (!form.password) {
    return
  }

  isSaving.value = true

  try {
    await userStore.updateCurrentUser({
      name: userStore.currentUser.name,
      email: userStore.currentUser.email,
      password: form.password,
    })

    form.password = ''
    form.confirm_password = ''
    v$.value.$reset()
  } finally {
    isSaving.value = false
  }
}
</script>
