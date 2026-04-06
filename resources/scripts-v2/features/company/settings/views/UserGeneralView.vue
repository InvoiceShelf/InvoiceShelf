<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, email, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useUserStore } from '../../../../stores/user.store'
import { useGlobalStore } from '../../../../stores/global.store'

const userStore = useUserStore()
const globalStore = useGlobalStore()
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
}))

const v$ = useVuelidate(rules, userForm)

async function updateGeneral(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true
  try {
    await userStore.updateCurrentUser({
      name: userForm.value.name,
      email: userForm.value.email,
      language: userForm.value.language || undefined,
    })
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="updateGeneral">
    <BaseSettingCard
      :title="$t('settings.account_settings.general')"
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

        <BaseInputGroup :label="$t('settings.language')">
          <BaseMultiselect
            v-model="userForm.language"
            :options="(globalStore.config as Record<string, unknown>)?.languages as Array<{ name: string; code: string }> ?? []"
            label="name"
            value-prop="code"
            track-by="name"
            :searchable="true"
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
