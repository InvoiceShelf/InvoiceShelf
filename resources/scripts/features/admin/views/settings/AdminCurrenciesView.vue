<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { client } from '@/scripts/api/client'
import { API } from '@/scripts/api/endpoints'

interface AdminCurrency {
  id: number
  name: string
  code: string
  symbol: string | null
  precision: number
}

const { t } = useI18n()
const notificationStore = useNotificationStore()

const currencies = ref<AdminCurrency[]>([])
const search = ref('')
const isLoading = ref(false)
const isRefreshing = ref(false)

const visible = computed<AdminCurrency[]>(() => {
  const term = search.value.trim().toLowerCase()

  if (!term) {
    return currencies.value
  }

  return currencies.value.filter(
    (currency) =>
      currency.code.toLowerCase().includes(term) ||
      currency.name.toLowerCase().includes(term)
  )
})

onMounted(load)

async function load(): Promise<void> {
  isLoading.value = true

  try {
    const { data } = await client.get(API.ADMIN_CURRENCIES)
    currencies.value = data.data
  } finally {
    isLoading.value = false
  }
}

/**
 * Plant whatever this release ships that the installation does not have yet.
 * Nothing is ever removed, so this is safe to press at any time.
 */
async function refresh(): Promise<void> {
  isRefreshing.value = true

  try {
    const { data } = await client.post(API.ADMIN_CURRENCIES_REFRESH)

    await load()

    const added = data.added.length
    const updated = data.updated.length

    notificationStore.showNotification({
      type: 'success',
      message:
        added || updated
          ? t('settings.currencies.refreshed', { added, updated })
          : t('settings.currencies.already_current'),
    })
  } catch {
    notificationStore.showNotification({
      type: 'error',
      message: t('settings.currencies.refresh_failed'),
    })
  } finally {
    isRefreshing.value = false
  }
}
</script>

<template>
  <BaseSettingCard
    :title="$t('settings.currencies.title')"
    :description="$t('settings.currencies.description')"
  >
    <template #action>
      <BaseButton
        variant="primary-outline"
        :loading="isRefreshing"
        :disabled="isRefreshing"
        @click="refresh"
      >
        <template #left="slotProps">
          <BaseIcon
            v-if="!isRefreshing"
            name="ArrowPathIcon"
            :class="slotProps.class"
          />
        </template>
        {{ $t('settings.currencies.refresh') }}
      </BaseButton>
    </template>

    <BaseInput
      v-model="search"
      :placeholder="$t('settings.currencies.search')"
      class="mb-6"
    >
      <template #left="slotProps">
        <BaseIcon name="MagnifyingGlassIcon" :class="slotProps.class" />
      </template>
    </BaseInput>

    <p class="mb-4 text-sm text-muted">
      {{ $t('settings.currencies.count', { count: currencies.length }) }}
    </p>

    <div class="border border-line-light rounded-lg divide-y divide-line-light">
      <div
        v-for="currency in visible"
        :key="currency.id"
        class="flex items-center justify-between px-4 py-3"
      >
        <div class="flex items-center gap-3">
          <span
            class="w-12 text-xs font-medium tracking-wide text-subtle shrink-0"
          >
            {{ currency.code }}
          </span>
          <span class="text-sm text-heading">{{ currency.name }}</span>
        </div>

        <div class="flex items-center gap-6 text-sm text-muted">
          <!-- Isolated, so "C$" or "Fr." keeps its order in right-to-left text -->
          <bdi>{{ currency.symbol }}</bdi>
          <span class="w-24 text-end text-xs whitespace-nowrap">
            {{ $t('settings.currencies.decimals', { count: currency.precision }) }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="!visible.length && !isLoading" class="py-8 text-center text-muted">
      {{ $t('settings.currencies.none_found') }}
    </div>
  </BaseSettingCard>
</template>
