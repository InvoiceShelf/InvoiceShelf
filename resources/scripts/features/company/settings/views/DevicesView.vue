<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { tokenService } from '@/scripts/api/services/token.service'
import { handleApiError } from '@/scripts/utils/error-handling'
import { formatDate, DEFAULT_DATETIME_FORMAT } from '@/scripts/utils/format-date'
import * as localStore from '@/scripts/utils/local-storage'
import { LS_KEYS } from '@/scripts/config/constants'
import type { PersonalAccessToken } from '@/scripts/types/domain/personal-access-token'

const { t } = useI18n()
const router = useRouter()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()

const devices = ref<PersonalAccessToken[]>([])
const isFetching = ref<boolean>(true)
const revokingId = ref<number | null>(null)

const showEmptyScreen = computed<boolean>(
  () => !isFetching.value && devices.value.length === 0
)

onMounted(fetchDevices)

async function fetchDevices(): Promise<void> {
  isFetching.value = true

  try {
    const response = await tokenService.listTokens()
    devices.value = response.data
  } catch (err: unknown) {
    notificationStore.showNotification({
      type: 'error',
      message: handleApiError(err).message,
    })
  } finally {
    isFetching.value = false
  }
}

function addedLabel(device: PersonalAccessToken): string {
  return t('devices.added_on', {
    date: formatDate(device.created_at, DEFAULT_DATETIME_FORMAT),
  })
}

function lastUsedLabel(device: PersonalAccessToken): string {
  if (!device.last_used_at) {
    return t('devices.never_used')
  }

  return t('devices.last_used', {
    date: formatDate(device.last_used_at, DEFAULT_DATETIME_FORMAT),
  })
}

function confirmRevoke(device: PersonalAccessToken): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: device.current
        ? t('devices.revoke_current_confirm')
        : t('devices.revoke_confirm', { name: device.name }),
      yesLabel: t('general.yes'),
      noLabel: t('general.no'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((confirmed: boolean) => {
      if (!confirmed) {
        return
      }

      revoke(device)
    })
}

async function revoke(device: PersonalAccessToken): Promise<void> {
  revokingId.value = device.id

  try {
    await tokenService.revokeToken(device.id)

    if (device.current) {
      await signOutThisDevice()
      return
    }

    devices.value = devices.value.filter((item) => item.id !== device.id)

    notificationStore.showNotification({
      type: 'success',
      message: t('devices.revoked_message'),
    })
  } catch (err: unknown) {
    notificationStore.showNotification({
      type: 'error',
      message: handleApiError(err).message,
    })
  } finally {
    revokingId.value = null
  }
}

/**
 * The token this session carries is gone, so the session is over: drop what
 * identifies it in both stores and go to login, rather than waiting for the
 * next request to come back 401.
 */
async function signOutThisDevice(): Promise<void> {
  localStore.remove(LS_KEYS.AUTH_TOKEN)
  localStore.remove(LS_KEYS.SELECTED_COMPANY)
  localStore.remove(LS_KEYS.IS_ADMIN_MODE)

  // A client mirrors those removals into platform storage asynchronously;
  // waiting keeps a cold start from restoring the token we just revoked.
  await localStore.flushClientState()

  await router.push({ name: 'login' })
}
</script>

<template>
  <BaseSettingCard
    :title="$t('devices.title')"
    :description="$t('devices.description')"
  >
    <BaseContentPlaceholders v-if="isFetching" rounded>
      <BaseContentPlaceholdersBox
        v-for="placeholder in 2"
        :key="placeholder"
        class="w-full h-16 mt-4"
        rounded
      />
    </BaseContentPlaceholders>

    <BaseEmptyPlaceholder
      v-else-if="showEmptyScreen"
      :title="$t('devices.no_devices')"
      :description="$t('devices.no_devices_description')"
    >
      <BaseIcon name="DevicePhoneMobileIcon" class="w-12 h-12 text-subtle" />
    </BaseEmptyPlaceholder>

    <div v-else class="border-t border-line-default divide-y divide-line-default">
      <div
        v-for="device in devices"
        :key="device.id"
        class="flex flex-wrap items-center justify-between gap-3 py-4"
      >
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <BaseIcon
              name="DevicePhoneMobileIcon"
              class="w-5 h-5 shrink-0 text-subtle"
            />

            <span class="text-sm font-medium truncate text-heading">
              {{ device.name }}
            </span>

            <span
              v-if="device.current"
              class="
                px-2
                py-0.5
                text-xs
                font-medium
                whitespace-nowrap
                rounded-full
                bg-surface-tertiary
                text-primary-500
              "
            >
              {{ $t('devices.this_device') }}
            </span>
          </div>

          <p class="flex flex-wrap mt-1 text-sm gap-x-2 text-muted">
            <span>{{ addedLabel(device) }}</span>
            <span aria-hidden="true">&middot;</span>
            <span>{{ lastUsedLabel(device) }}</span>
          </p>
        </div>

        <BaseButton
          variant="danger"
          size="sm"
          :loading="revokingId === device.id"
          :disabled="revokingId !== null"
          @click="confirmRevoke(device)"
        >
          {{ $t('devices.revoke') }}
        </BaseButton>
      </div>
    </div>
  </BaseSettingCard>
</template>
