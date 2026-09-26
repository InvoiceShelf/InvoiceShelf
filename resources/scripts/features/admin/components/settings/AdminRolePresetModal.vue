<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { rolePresetService } from '@/scripts/api/services/role-preset.service'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import AbilityMatrix from '@/scripts/features/shared/roles/AbilityMatrix.vue'
import type { AbilityDefinition } from '@/scripts/features/shared/roles/abilities'
import type { RolePreset } from '@/scripts/types/domain/role'

/**
 * Creates or edits a role preset, or shows the Owner preset, which cannot be
 * changed. Saving reaches every company.
 */
const props = defineProps<{
  show: boolean
  preset: RolePreset | null
  catalogue: AbilityDefinition[]
}>()

const emit = defineEmits<{ close: []; saved: [] }>()

const { t } = useI18n()
const notificationStore = useNotificationStore()

const title = ref<string>('')
const abilities = ref<string[]>([])
const titleError = ref<string | null>(null)
const abilitiesError = ref<string | null>(null)
const isSaving = ref<boolean>(false)

const readonly = computed<boolean>(() => props.preset?.is_owner === true)
const heading = computed<string>(() => {
  if (!props.preset) return t('settings.role_presets.add')
  return readonly.value ? t('settings.role_presets.view') : t('settings.role_presets.edit')
})

watch(
  () => [props.show, props.preset] as const,
  ([show]) => {
    if (!show) return
    title.value = props.preset?.title ?? ''
    abilities.value = [...(props.preset?.abilities ?? [])]
    titleError.value = null
    abilitiesError.value = null
  },
  { immediate: true }
)

watch(title, (value) => {
  if (value.trim() !== '') titleError.value = null
})

watch(abilities, (value) => {
  if (value.length > 0) abilitiesError.value = null
})

async function save(): Promise<void> {
  if (readonly.value) return

  titleError.value = title.value.trim() === '' ? t('validation.required') : null
  abilitiesError.value = abilities.value.length === 0 ? t('validation.at_least_one_ability') : null
  if (titleError.value || abilitiesError.value) return

  isSaving.value = true
  try {
    const payload = { title: title.value.trim(), abilities: abilities.value }
    if (props.preset) {
      await rolePresetService.update(props.preset.id, payload)
      notificationStore.showNotification({ type: 'success', message: t('settings.role_presets.updated') })
    } else {
      await rolePresetService.create(payload)
      notificationStore.showNotification({ type: 'success', message: t('settings.role_presets.created') })
    }
    emit('saved')
    emit('close')
  } catch (err: unknown) {
    const error = handleApiError(err)
    if (error.validationErrors.title) {
      titleError.value = error.validationErrors.title[0]
    } else {
      notificationStore.showNotification({
        type: 'error',
        message: getErrorTranslationKey(error.message) ?? error.message,
      })
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal :show="show" closable size="lg" @close="emit('close')">
    <template #header>
      {{ heading }}
    </template>

    <form @submit.prevent="save">
      <div class="px-4 md:px-8 py-4 md:py-6 space-y-3">
        <p class="text-sm text-muted">
          {{ readonly ? $t('settings.role_presets.owner_hint') : $t('settings.role_presets.applies_everywhere') }}
        </p>
        <BaseInputGroup :label="$t('settings.role_presets.name')" :error="titleError" :required="!readonly">
          <BaseInput v-model="title" :disabled="readonly" :invalid="!!titleError" type="text" />
        </BaseInputGroup>
      </div>

      <AbilityMatrix v-model="abilities" :abilities="catalogue" :readonly="readonly" :error="abilitiesError" />

      <div class="z-0 flex justify-end p-4 border-t border-solid border-line-default">
        <BaseButton
          class="text-sm"
          :class="{ 'me-3': !readonly }"
          variant="primary-outline"
          type="button"
          @click="emit('close')"
        >
          {{ readonly ? $t('general.close') : $t('general.cancel') }}
        </BaseButton>
        <BaseButton v-if="!readonly" :loading="isSaving" :disabled="isSaving" variant="primary" type="submit">
          <template #left="slotProps">
            <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
