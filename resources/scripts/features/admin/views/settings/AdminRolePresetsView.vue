<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { rolePresetService } from '@/scripts/api/services/role-preset.service'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import AdminRolePresetModal from '@/scripts/features/admin/components/settings/AdminRolePresetModal.vue'
import type { AbilityDefinition } from '@/scripts/features/shared/roles/abilities'
import type { RolePreset } from '@/scripts/types/domain/role'

/**
 * The roles every company gets. Owners assign them; only the super
 * administrator changes them, here, for every company at once.
 */
const { t } = useI18n()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()

const presets = ref<RolePreset[]>([])
const catalogue = ref<AbilityDefinition[]>([])
const isFetching = ref<boolean>(true)
const showModal = ref<boolean>(false)
const editing = ref<RolePreset | null>(null)

async function load(): Promise<void> {
  try {
    const [list, abilities] = await Promise.all([rolePresetService.list(), rolePresetService.abilities()])
    presets.value = list
    catalogue.value = abilities
  } catch (err: unknown) {
    notify(err)
  } finally {
    isFetching.value = false
  }
}

function notify(err: unknown): void {
  const { message } = handleApiError(err)
  notificationStore.showNotification({ type: 'error', message: getErrorTranslationKey(message) ?? message })
}

function open(preset: RolePreset | null): void {
  editing.value = preset
  showModal.value = true
}

function confirmDelete(preset: RolePreset): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.role_presets.confirm_delete'),
      yesLabel: t('general.delete'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (confirmed: boolean) => {
      if (!confirmed) return

      try {
        await rolePresetService.remove(preset.id)
        notificationStore.showNotification({ type: 'success', message: t('settings.role_presets.deleted') })
        await load()
      } catch (err: unknown) {
        notify(err)
      }
    })
}

onMounted(load)
</script>

<template>
  <BaseSettingCard :title="$t('settings.role_presets.title')" :description="$t('settings.role_presets.description')">
    <template #action>
      <BaseButton variant="primary-outline" :disabled="isFetching" @click="open(null)">
        <template #left="slotProps">
          <BaseIcon name="PlusIcon" :class="slotProps.class" />
        </template>
        {{ $t('settings.role_presets.add') }}
      </BaseButton>
    </template>

    <BaseContentPlaceholders v-if="isFetching" rounded>
      <BaseContentPlaceholdersBox class="w-full h-32 mt-6" rounded />
    </BaseContentPlaceholders>

    <ul v-else class="mt-6 divide-y divide-line-light rounded-lg border border-line-default">
      <li v-for="preset in presets" :key="preset.id" class="flex items-center gap-4 px-4 py-3">
        <div class="min-w-0 flex-1">
          <p class="flex items-center gap-2 text-sm font-medium text-heading">
            {{ preset.title }}
            <span
              v-if="preset.is_owner"
              class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-surface-tertiary text-muted ring-1 ring-inset ring-line-default"
            >
              {{ $t('settings.role_presets.built_in') }}
            </span>
          </p>
          <p class="text-xs text-muted">
            {{ $t('settings.role_presets.permission_count', { count: preset.abilities.length }, preset.abilities.length) }}
          </p>
        </div>

        <BaseButton size="sm" variant="white" @click="open(preset)">
          {{ preset.is_owner ? $t('general.view') : $t('general.edit') }}
        </BaseButton>
        <BaseButton
          v-if="!preset.is_owner"
          size="sm"
          variant="white"
          :aria-label="`${$t('general.delete')} ${preset.title}`"
          @click="confirmDelete(preset)"
        >
          <BaseIcon name="TrashIcon" class="h-4 w-4 text-danger" aria-hidden="true" />
        </BaseButton>
      </li>
    </ul>
  </BaseSettingCard>

  <AdminRolePresetModal
    :show="showModal"
    :preset="editing"
    :catalogue="catalogue"
    @close="showModal = false"
    @saved="load"
  />
</template>
