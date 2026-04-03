<template>
  <form @submit.prevent="updateGeneral">
    <BaseInputGrid>
      <BaseInputGroup
        :label="$t('settings.account_settings.name')"
        :error="v$.name.$error && v$.name.$errors[0].$message"
        required
      >
        <BaseInput
          v-model="form.name"
          :invalid="v$.name.$error"
          @input="v$.name.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('settings.account_settings.email')"
        :error="v$.email.$error && v$.email.$errors[0].$message"
        required
      >
        <BaseInput
          v-model="form.email"
          :invalid="v$.email.$error"
          @input="v$.email.$touch()"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('settings.language')">
        <BaseMultiselect
          v-model="form.language"
          :options="globalStore.config.languages"
          label="name"
          value-prop="code"
          track-by="name"
          :searchable="true"
          open-direction="top"
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
  </form>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useI18n } from 'vue-i18n'
import { helpers, email, required } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'

const userStore = useUserStore()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()
const { t } = useI18n()

const isSaving = ref(false)

const form = reactive({
  name: userStore.currentUser.name,
  email: userStore.currentUser.email,
  language:
    userStore.currentUserSettings.language ||
    companyStore.selectedCompanySettings.language,
})

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => form)
)

async function updateGeneral() {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true

  try {
    if (userStore.currentUserSettings.language !== form.language) {
      await window.loadLanguage(form.language)

      await userStore.updateUserSettings({
        settings: {
          language: form.language,
        },
      })
    }

    await userStore.updateCurrentUser({
      name: form.name,
      email: form.email,
    })
  } finally {
    isSaving.value = false
  }
}
</script>
