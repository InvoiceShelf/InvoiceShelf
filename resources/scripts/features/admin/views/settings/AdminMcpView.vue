<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { mcpService } from '@/scripts/api/services/mcp.service'
import { handleApiError } from '@/scripts/utils/error-handling'
import CopyableValue from '@/scripts/features/shared/mcp/CopyableValue.vue'
import type { McpServerSettings } from '@/scripts/types/domain/mcp'

const { t } = useI18n()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()

const settings = ref<McpServerSettings | null>(null)
const isFetching = ref<boolean>(true)
const isSaving = ref<boolean>(false)
const isRegenerating = ref<boolean>(false)
const newDomain = ref<string>('')
const domainError = ref<string>('')

onMounted(async () => {
  try {
    settings.value = await mcpService.settings()
  } catch (err: unknown) {
    notify(err)
  } finally {
    isFetching.value = false
  }
})

const keyStatus = computed<string>(() => {
  switch (settings.value?.key_status) {
    case 'env':
      return t('mcp.admin.key_status_env')
    case 'file':
      return t('mcp.admin.key_status_file')
    default:
      return t('mcp.admin.key_status_missing')
  }
})

function notify(err: unknown): void {
  notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
}

async function save(payload: { enabled?: boolean; redirect_domains?: string[] }): Promise<boolean> {
  isSaving.value = true

  try {
    settings.value = await mcpService.updateSettings(payload)
    notificationStore.showNotification({ type: 'success', message: t('mcp.admin.saved') })

    return true
  } catch (err: unknown) {
    notify(err)

    return false
  } finally {
    isSaving.value = false
  }
}

function toggle(enabled: boolean): void {
  void save({ enabled })
}

async function addDomain(): Promise<void> {
  const domain = newDomain.value.trim().replace(/\/+$/, '')
  domainError.value = ''

  if (!/^https:\/\/[^/\s*]+$/i.test(domain)) {
    domainError.value = t('mcp.admin.redirect_invalid')

    return
  }

  const current = settings.value?.redirect_domains ?? []

  if (current.includes(domain) || (await save({ redirect_domains: [...current, domain] }))) {
    newDomain.value = ''
  }
}

function removeDomain(domain: string): void {
  void save({ redirect_domains: (settings.value?.redirect_domains ?? []).filter((item) => item !== domain) })
}

function confirmRegenerate(): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('mcp.admin.regenerate_confirm'),
      yesLabel: t('mcp.admin.regenerate'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (confirmed: boolean) => {
      if (!confirmed) {
        return
      }

      isRegenerating.value = true

      try {
        settings.value = await mcpService.regenerateKeys()
        notificationStore.showNotification({ type: 'success', message: t('mcp.admin.regenerated') })
      } catch (err: unknown) {
        notify(err)
      } finally {
        isRegenerating.value = false
      }
    })
}
</script>

<template>
  <BaseSettingCard :title="$t('mcp.admin.title')" :description="$t('mcp.admin.description')">
    <BaseContentPlaceholders v-if="isFetching" rounded>
      <BaseContentPlaceholdersBox class="w-full h-24 mt-4" rounded />
    </BaseContentPlaceholders>

    <div v-else-if="settings" class="space-y-6">
      <div
        v-if="!settings.secure"
        role="status"
        class="p-4 mt-4 rounded-lg bg-alert-warning-bg text-alert-warning-text"
      >
        <p class="text-sm font-medium">{{ $t('mcp.admin.insecure_title') }}</p>
        <p class="mt-1 text-sm">{{ $t('mcp.admin.insecure_description') }}</p>
      </div>

      <div class="divide-y divide-line-default">
        <BaseSwitchSection
          :model-value="settings.enabled"
          :disabled="isSaving"
          :title="$t('mcp.admin.enable')"
          :description="$t('mcp.admin.enable_description')"
          @update:model-value="toggle"
        />
      </div>

      <div>
        <p class="mb-2 text-sm font-medium text-heading">{{ $t('mcp.admin.server_url') }}</p>
        <CopyableValue :value="settings.server_url" :label="$t('mcp.admin.server_url')" />
        <p class="mt-2 text-sm text-muted">
          {{ $t('mcp.admin.connections', { count: settings.connection_count }, settings.connection_count) }}
        </p>
      </div>
    </div>
  </BaseSettingCard>

  <BaseSettingCard
    v-if="settings"
    :title="$t('mcp.admin.keys_title')"
    :description="$t('mcp.admin.keys_description')"
    class="mt-6"
  >
    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm text-body">{{ keyStatus }}</p>

      <BaseButton
        variant="danger"
        size="sm"
        :loading="isRegenerating"
        :disabled="settings.key_status !== 'file' || isRegenerating"
        @click="confirmRegenerate"
      >
        {{ $t('mcp.admin.regenerate') }}
      </BaseButton>
    </div>
  </BaseSettingCard>

  <BaseSettingCard
    v-if="settings"
    :title="$t('mcp.admin.redirect_title')"
    :description="$t('mcp.admin.redirect_description')"
    class="mt-6"
  >
    <div class="space-y-5">
      <div>
        <p class="mb-2 text-sm font-medium text-heading">{{ $t('mcp.admin.redirect_defaults') }}</p>
        <ul class="flex flex-wrap gap-2">
          <li
            v-for="domain in settings.default_redirect_domains"
            :key="domain"
            dir="ltr"
            class="px-2.5 py-1 font-mono text-xs rounded-full bg-surface-tertiary text-body"
          >
            {{ domain }}
          </li>
        </ul>
      </div>

      <div>
        <p class="mb-2 text-sm font-medium text-heading">{{ $t('mcp.admin.redirect_extra') }}</p>

        <p v-if="settings.redirect_domains.length === 0" class="text-sm text-muted">
          {{ $t('mcp.admin.redirect_none') }}
        </p>

        <ul v-else class="flex flex-wrap gap-2">
          <li
            v-for="domain in settings.redirect_domains"
            :key="domain"
            class="flex items-center gap-1 ps-2.5 pe-1 py-0.5 rounded-full bg-surface-tertiary text-body"
          >
            <span dir="ltr" class="font-mono text-xs">{{ domain }}</span>
            <button
              type="button"
              class="p-0.5 rounded-full text-muted hover:text-heading focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
              :aria-label="$t('mcp.admin.redirect_remove', { domain })"
              :disabled="isSaving"
              @click="removeDomain(domain)"
            >
              <BaseIcon name="XMarkIcon" class="w-4 h-4" />
            </button>
          </li>
        </ul>
      </div>

      <form class="flex flex-wrap items-start gap-2" @submit.prevent="addDomain">
        <BaseInputGroup
          :label="$t('mcp.admin.redirect_input')"
          :error="domainError"
          class="flex-1 min-w-[14rem]"
        >
          <BaseInput
            v-model="newDomain"
            type="url"
            dir="ltr"
            :placeholder="$t('mcp.admin.redirect_placeholder')"
            :invalid="!!domainError"
          />
        </BaseInputGroup>

        <BaseButton type="submit" variant="primary-outline" class="mt-6" :loading="isSaving">
          {{ $t('mcp.admin.redirect_add') }}
        </BaseButton>
      </form>
    </div>
  </BaseSettingCard>
</template>
