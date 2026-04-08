<template>
  <BasePage>
    <BasePageHeader :title="pageTitle">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('modules.title')" to="/admin/modules" />
        <BaseBreadcrumbItem :title="pageTitle" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <div v-if="isFetching" class="mt-8 space-y-4">
      <div class="h-6 bg-surface-tertiary rounded w-1/4 animate-pulse" />
      <div class="h-12 bg-surface-tertiary rounded animate-pulse" />
      <div class="h-12 bg-surface-tertiary rounded animate-pulse" />
    </div>

    <BaseCard v-else-if="schema" class="mt-6">
      <BaseSchemaForm
        :schema="schema"
        :values="values"
        :is-saving="isSaving"
        @submit="onSubmit"
      />
    </BaseCard>

    <div v-else class="mt-16 text-center">
      <p class="text-muted">{{ $t('modules.settings.not_found') }}</p>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  moduleSettingsService,
  type ModuleSettingsSchema,
} from '@/scripts/api/services/moduleSettings.service'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { handleApiError } from '@/scripts/utils/error-handling'

const route = useRoute()
const { t } = useI18n()
const notificationStore = useNotificationStore()

const schema = ref<ModuleSettingsSchema | null>(null)
const values = ref<Record<string, unknown>>({})
const isFetching = ref<boolean>(false)
const isSaving = ref<boolean>(false)

const slug = computed<string>(() => route.params.slug as string)

const pageTitle = computed<string>(() => {
  // Modules supply their own translatable title via the schema first section
  return schema.value?.sections[0]?.title
    ? t(schema.value.sections[0].title)
    : t('modules.settings.title')
})

watch(slug, () => {
  loadSettings()
})

onMounted(() => {
  loadSettings()
})

async function loadSettings(): Promise<void> {
  if (!slug.value) return

  isFetching.value = true
  try {
    const response = await moduleSettingsService.fetch(slug.value)
    schema.value = response.schema
    values.value = response.values
  } catch (err: unknown) {
    schema.value = null
    handleApiError(err)
  } finally {
    isFetching.value = false
  }
}

async function onSubmit(formValues: Record<string, unknown>): Promise<void> {
  isSaving.value = true
  try {
    await moduleSettingsService.update(slug.value, formValues)
    values.value = formValues
    notificationStore.showNotification({
      type: 'success',
      message: t('modules.settings.saved'),
    })
  } catch (err: unknown) {
    handleApiError(err)
  } finally {
    isSaving.value = false
  }
}
</script>
