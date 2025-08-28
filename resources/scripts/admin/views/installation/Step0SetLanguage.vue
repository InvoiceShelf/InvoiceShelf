<template>
  <BaseWizardStep
    :title="$t('wizard.install_language.title')"
    :description="$t('wizard.install_language.description')"
  >
    <div class="w-full md:w-2/3">
      <div class="mb-6">
        <BaseInputGroup
          :label="$t('wizard.language')"
          :content-loading="isFetchingInitialData"
          required
        >
          <BaseMultiselect
            v-model="currentLanguage"
            :content-loading="isFetchingInitialData"
            :options="languages"
            label="name"
            value-prop="code"
            :placeholder="$t('settings.preferences.select_language')"
            class="w-full"
            track-by="name"
            :searchable="true"
            @change="changeLanguage"
          />
        </BaseInputGroup>
      </div>

      <BaseButton
        v-show="!isFetchingInitialData"
        :loading="isChangingLanguage"
        :disabled="isChangingLanguage"
        @click="next"
      >
        {{ $t('wizard.continue') }}
        <template #left="slotProps">
          <BaseIcon name="ArrowRightIcon" :class="slotProps.class" />
        </template>
      </BaseButton>

    </div>
  </BaseWizardStep>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useInstallationStore } from '@/scripts/admin/stores/installation.js'

const emit = defineEmits(['next'])

let isFetchingInitialData = ref(false)
let isSaving = ref(false)
let isChangingLanguage = ref(false)
let languages = ref([])
let currentLanguage = 'en'

const installationStore = useInstallationStore()

onMounted(() => {
  getLanguages()
})

async function getLanguages() {
  isFetchingInitialData.value = true

  const res = await installationStore.fetchInstallationLanguages()

  languages.value = res.data.languages

  isFetchingInitialData.value = false
}


function next() {
  isSaving.value = true
  emit('next')
  isSaving.value = false
}

async function changeLanguage(event) {
  if (!event) return

  isChangingLanguage.value = true

  try {
    // Dynamically load the selected language
    await window.loadLanguage(event)
    currentLanguage.value = event
  } catch (error) {
    console.error('Failed to change language:', error)
  } finally {
    isChangingLanguage.value = false
  }
}
</script>
