<template>
  <BaseModal
    :show="modalActive"
    @close="closeModal"
    @open="loadSettings"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>

    <div v-if="isFetching" class="p-8 sm:p-6 space-y-4">
      <div class="h-6 bg-surface-tertiary rounded w-1/3 animate-pulse" />
      <div class="h-12 bg-surface-tertiary rounded animate-pulse" />
      <div class="h-12 bg-surface-tertiary rounded animate-pulse" />
    </div>

    <div v-else-if="schema" class="p-8 sm:p-6">
      <BaseSchemaForm
        :schema="schema"
        :values="values"
        :is-saving="isSaving"
        @submit="onSubmit"
      />
    </div>

    <div v-else class="p-8 sm:p-6 text-center">
      <p class="text-muted">{{ $t('modules.settings.not_found') }}</p>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  moduleSettingsService,
  type ModuleSettingsSchema,
} from '@/scripts/api/services/moduleSettings.service'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { handleApiError } from '@/scripts/utils/error-handling'
import type { CompanyModuleSummary } from '../store'

const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const schema = ref<ModuleSettingsSchema | null>(null)
const values = ref<Record<string, unknown>>({})
const isFetching = ref<boolean>(false)
const isSaving = ref<boolean>(false)
const resetTimeoutId = ref<number | null>(null)

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'ModuleSettingsModal'
)

const currentModule = computed<CompanyModuleSummary | null>(() => {
  if (!modalStore.data || typeof modalStore.data !== 'object') {
    return null
  }

  return modalStore.data as CompanyModuleSummary
})

async function loadSettings(): Promise<void> {
  if (!currentModule.value?.slug) {
    return
  }

  clearResetTimeout()
  isFetching.value = true

  try {
    const response = await moduleSettingsService.fetch(currentModule.value.slug)
    schema.value = response.schema
    values.value = response.values
  } catch (err: unknown) {
    schema.value = null
    values.value = {}
    handleApiError(err)
  } finally {
    isFetching.value = false
  }
}

async function onSubmit(formValues: Record<string, unknown>): Promise<void> {
  if (!currentModule.value?.slug) {
    return
  }

  isSaving.value = true

  try {
    await moduleSettingsService.update(currentModule.value.slug, formValues)
    values.value = formValues
    notificationStore.showNotification({
      type: 'success',
      message: t('modules.settings.saved'),
    })
    closeModal()
  } catch (err: unknown) {
    handleApiError(err)
  } finally {
    isSaving.value = false
  }
}

function closeModal(): void {
  modalStore.closeModal()
  clearResetTimeout()
  resetTimeoutId.value = window.setTimeout(resetState, 300)
}

function resetState(): void {
  schema.value = null
  values.value = {}
  isFetching.value = false
  isSaving.value = false
  resetTimeoutId.value = null
}

function clearResetTimeout(): void {
  if (resetTimeoutId.value !== null) {
    window.clearTimeout(resetTimeoutId.value)
    resetTimeoutId.value = null
  }
}
</script>
