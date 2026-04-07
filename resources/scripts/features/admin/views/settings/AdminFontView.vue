<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { client } from '@/scripts/api/client'
import { API } from '@/scripts/api/endpoints'

interface FontPackage {
  key: string
  name: string
  family: string
  locales: string[]
  size: string
  installed: boolean
  bundled?: boolean
}

const { t } = useI18n()
const notificationStore = useNotificationStore()

const packages = ref<FontPackage[]>([])
const isLoading = ref(false)
const installing = ref<Set<string>>(new Set())

onMounted(async () => {
  await loadStatus()
})

async function loadStatus(): Promise<void> {
  isLoading.value = true
  try {
    const { data } = await client.get(API.FONTS_STATUS)
    packages.value = data.packages
  } catch {
    // Silently fail
  } finally {
    isLoading.value = false
  }
}

async function installFont(pkg: FontPackage): Promise<void> {
  installing.value.add(pkg.key)

  try {
    await client.post(`${API.FONTS_INSTALL}/${pkg.key}/install`)

    pkg.installed = true

    notificationStore.showNotification({
      type: 'success',
      message: t('settings.fonts.download_complete', { name: pkg.name }),
    })
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: t('settings.fonts.download_failed', { name: pkg.name }),
    })
  } finally {
    installing.value.delete(pkg.key)
  }
}
</script>

<template>
  <BaseSettingCard
    :title="$t('settings.fonts.title')"
    :description="$t('settings.fonts.description')"
  >
    <p class="text-sm text-muted mb-6">
      {{ $t('settings.fonts.bundled_info') }}
    </p>

    <div class="space-y-3">
      <div
        v-for="pkg in packages"
        :key="pkg.key"
        class="flex items-center justify-between p-4 border border-line-light rounded-lg"
      >
        <div>
          <div class="font-medium text-heading">{{ pkg.name }}</div>
          <div class="text-xs text-subtle mt-1">
            {{ pkg.locales.join(', ') }} — {{ pkg.size }}
          </div>
        </div>

        <div class="flex items-center gap-3">
          <span
            v-if="pkg.bundled"
            class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-600"
          >
            {{ $t('settings.fonts.bundled') }}
          </span>

          <span
            v-else-if="pkg.installed"
            class="inline-flex items-center rounded-full bg-success px-2.5 py-1 text-xs font-medium text-status-green"
          >
            {{ $t('settings.fonts.installed') }}
          </span>

          <BaseButton
            v-else
            size="sm"
            variant="primary-outline"
            :loading="installing.has(pkg.key)"
            :disabled="installing.has(pkg.key)"
            @click="installFont(pkg)"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!installing.has(pkg.key)"
                name="CloudArrowDownIcon"
                :class="slotProps.class"
              />
            </template>
            {{ installing.has(pkg.key) ? $t('settings.fonts.downloading') : $t('settings.fonts.install') }}
          </BaseButton>
        </div>
      </div>
    </div>

    <div v-if="!packages.length && !isLoading" class="text-center py-8 text-muted">
      {{ $t('settings.fonts.no_packages') }}
    </div>
  </BaseSettingCard>
</template>
