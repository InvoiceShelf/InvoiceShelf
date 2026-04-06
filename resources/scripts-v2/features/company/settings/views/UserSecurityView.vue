<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { minLength, sameAs, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useUserStore } from '../../../../stores/user.store'

const userStore = useUserStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)

const form = reactive({
  password: '',
  confirm_password: '',
})

const rules = computed(() => ({
  password: {
    minLength: helpers.withMessage(
      t('validation.password_min_length', { count: 8 }),
      minLength(8),
    ),
  },
  confirm_password: {
    sameAsPassword: helpers.withMessage(
      t('validation.password_incorrect'),
      sameAs(form.password),
    ),
  },
}))

const v$ = useVuelidate(rules, form)

async function updatePassword(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true
  try {
    await userStore.updateCurrentUser({
      name: userStore.currentUser?.name ?? '',
      email: userStore.currentUser?.email ?? '',
      password: form.password,
      confirm_password: form.confirm_password,
    })
    form.password = ''
    form.confirm_password = ''
    v$.value.$reset()
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="updatePassword">
    <BaseSettingCard
      :title="$t('settings.account_settings.security')"
      :description="$t('settings.account_settings.security_description')"
    >
      <BaseInputGrid class="mt-5">
        <BaseInputGroup
          :label="$t('settings.account_settings.password')"
          :error="v$.password.$error && v$.password.$errors[0]?.$message"
        >
          <BaseInput
            v-model="form.password"
            type="password"
            :invalid="v$.password.$error"
            @blur="v$.password.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.account_settings.confirm_password')"
          :error="v$.confirm_password.$error && v$.confirm_password.$errors[0]?.$message"
        >
          <BaseInput
            v-model="form.confirm_password"
            type="password"
            :invalid="v$.confirm_password.$error"
            @blur="v$.confirm_password.$touch()"
          />
        </BaseInputGroup>
      </BaseInputGrid>

      <BaseButton :loading="isSaving" :disabled="isSaving" type="submit" class="mt-6">
        <template #left="slotProps">
          <BaseIcon v-if="!isSaving" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('settings.company_info.save') }}
      </BaseButton>
    </BaseSettingCard>
  </form>
</template>
