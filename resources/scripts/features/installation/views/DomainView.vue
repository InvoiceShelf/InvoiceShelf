<template>
  <BaseWizardStep
    :title="$t('wizard.verify_domain.title')"
    :description="$t('wizard.verify_domain.desc')"
  >
    <div class="w-full">
      <BaseInputGroup
        :label="$t('wizard.verify_domain.app_domain')"
        :error="v$.app_domain.$error ? String(v$.app_domain.$errors[0]?.$message) : undefined"
        required
      >
        <BaseInput
          v-model="formData.app_domain"
          :invalid="v$.app_domain.$error"
          type="text"
          @input="v$.app_domain.$touch()"
        />
      </BaseInputGroup>
    </div>

    <p class="mt-4 mb-0 text-sm text-body">
      {{ $t('wizard.verify_domain.notes.notes') }}
    </p>
    <ul class="w-full text-body list-disc list-inside">
      <li class="text-sm leading-8">
        {{ $t('wizard.verify_domain.notes.not_contain') }}
        <b class="inline-block px-1 bg-surface-tertiary rounded-xs">https://</b>
        {{ $t('wizard.verify_domain.notes.or') }}
        <b class="inline-block px-1 bg-surface-tertiary rounded-xs">http</b>
        {{ $t('wizard.verify_domain.notes.in_front') }}
      </li>
      <li class="text-sm leading-8">
        {{ $t('wizard.verify_domain.notes.if_you') }}
        <b class="inline-block px-1 bg-surface-tertiary">localhost:8080</b>
      </li>
    </ul>

    <BaseButton
      :loading="isSaving"
      :disabled="isSaving"
      class="mt-8"
      @click="verifyDomain"
    >
      {{ $t('wizard.verify_domain.verify_now') }}
    </BaseButton>
  </BaseWizardStep>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { client } from '../../../api/client'

interface Emits {
  (e: 'next', step: number): void
}

const emit = defineEmits<Emits>()
const { t } = useI18n()
const isSaving = ref<boolean>(false)

const formData = reactive<{ app_domain: string }>({
  app_domain: window.location.origin.replace(/(^\w+:|^)\/\//, ''),
})

function isUrl(value: string): boolean {
  if (!value) return false
  // Simple domain validation -- no protocol prefix
  return !value.startsWith('http://') && !value.startsWith('https://')
}

const rules = computed(() => ({
  app_domain: {
    required: helpers.withMessage(t('validation.required'), required),
    isUrl: helpers.withMessage(t('validation.invalid_domain_url'), isUrl),
  },
}))

const v$ = useVuelidate(rules, formData)

async function verifyDomain(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true

  try {
    await client.put('/api/v1/installation/set-domain', formData)
    await client.get('/sanctum/csrf-cookie')
    await client.post('/api/v1/installation/login')
    const { data } = await client.get('/api/v1/auth/check')

    if (data) {
      emit('next', 4)
    }
  } finally {
    isSaving.value = false
  }
}
</script>
