<template>
  <BaseWizardStep
    :title="$t('wizard.install_language.title')"
    :description="$t('wizard.install_language.description')"
    step-container-class="w-full"
  >
    <form @submit.prevent="next">
      <BaseInputGroup
        :label="$t('wizard.language')"
        :error="v$.language.$error ? String(v$.language.$errors[0]?.$message) : undefined"
        :content-loading="isFetchingInitialData"
        required
      >
        <BaseMultiselect
          v-model="formData.language"
          :content-loading="isFetchingInitialData"
          :options="languages"
          label="name"
          value-prop="code"
          :placeholder="$t('settings.preferences.select_language')"
          track-by="name"
          :searchable="true"
          :invalid="v$.language.$error"
          class="w-full"
          @change="onLanguageChange"
        />
      </BaseInputGroup>

      <BaseButton
        type="submit"
        :loading="isSaving"
        :disabled="isSaving || isFetchingInitialData"
        class="mt-8"
      >
        {{ $t('wizard.continue') }}
        <template #right="slotProps">
          <BaseIcon name="ArrowRightIcon" :class="slotProps.class" />
        </template>
      </BaseButton>
    </form>
  </BaseWizardStep>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { required, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { installClient } from '../../../api/install-client'
import { useInstallationFeedback } from '../use-installation-feedback'

interface LanguageOption {
  code: string
  name: string
}

const router = useRouter()
const { t } = useI18n()
const { showRequestError } = useInstallationFeedback()

/**
 * The Language step runs BEFORE the Database step, so we can't persist the
 * choice to the `settings` table — there is no database yet. Instead we
 * store it in localStorage. InvoiceShelf::start() reads this on app boot
 * (see resources/scripts/InvoiceShelf.ts) and pre-loads the matching locale,
 * so the language survives page reloads through the rest of the wizard.
 *
 * The user-facing language preference is persisted to the DB later by
 * PreferencesView (the final step) via the existing /api/v1/me/settings
 * and /api/v1/company/settings endpoints — no separate backend call here.
 */
const STORAGE_KEY = 'install_language'

const isFetchingInitialData = ref<boolean>(true)
const isSaving = ref<boolean>(false)
const languages = ref<LanguageOption[]>([])

const formData = reactive<{ language: string }>({
  language: localStorage.getItem(STORAGE_KEY) ?? 'en',
})

const rules = computed(() => ({
  language: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, formData)

onMounted(async () => {
  try {
    const { data } = await installClient.get('/api/v1/installation/languages')
    languages.value = data?.languages ?? []
  } catch (error: unknown) {
    showRequestError(error)
  } finally {
    isFetchingInitialData.value = false
  }
})

/**
 * Switch the UI language as soon as the user picks one — they shouldn't have
 * to click Continue to see the wizard re-render in their preferred locale.
 * Falls back silently if the locale loader hasn't been registered yet.
 */
async function onLanguageChange(code: string): Promise<void> {
  if (!code) return
  if (typeof window.loadLanguage === 'function') {
    await window.loadLanguage(code)
  }
}

async function next(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true
  try {
    // Persist the choice client-side so a page reload mid-wizard doesn't
    // lose the language. The DB doesn't exist yet at this step.
    localStorage.setItem(STORAGE_KEY, formData.language)
    await router.push({ name: 'installation.requirements' })
  } finally {
    isSaving.value = false
  }
}
</script>
