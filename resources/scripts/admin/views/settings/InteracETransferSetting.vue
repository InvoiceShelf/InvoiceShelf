<template>
  <BaseSettingCard
    :title="$t('settings.interac.title')"
    :description="$t('settings.interac.description')"
  >
    <template #action>
      <BaseButton
        variant="primary-outline"
        :disabled="!canSync || isSyncing || isSaving"
        :loading="isSyncing"
        type="button"
        @click="syncMailbox"
      >
        <template #left="slotProps">
          <BaseIcon :class="slotProps.class" name="ArrowPathIcon" />
        </template>
        {{ $t('settings.interac.sync_now') }}
      </BaseButton>
    </template>

    <form class="mt-10" @submit.prevent="saveSettings">
      <ul class="divide-y divide-gray-200">
        <BaseSwitchSection
          v-model="form.enabled"
          :title="$t('settings.interac.enable_sync')"
          :description="$t('settings.interac.description')"
        />
      </ul>

      <BaseInputGrid class="mt-6">
        <BaseInputGroup :label="$t('settings.interac.sender_filter')">
          <BaseInput
            v-model.trim="form.sender_filter"
            :content-loading="isLoading"
            :placeholder="$t('settings.interac.sender_filter_help')"
          />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.interac.imap_host')" required>
          <BaseInput
            v-model.trim="form.host"
            :content-loading="isLoading"
            required
          />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.interac.imap_port')" required>
          <BaseInput
            v-model.number="form.port"
            type="number"
            min="1"
            :content-loading="isLoading"
            required
          />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.interac.imap_encryption')" required>
          <BaseMultiselect
            v-model="form.encryption"
            :content-loading="isLoading"
            :options="encryptionOptions"
            value-prop="value"
            label="label"
            :can-clear="false"
          />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.interac.imap_username')" required>
          <BaseInput
            v-model.trim="form.username"
            :content-loading="isLoading"
            autocomplete="username"
            required
          />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.interac.imap_password')" required>
          <BaseInput
            v-model="form.password"
            :content-loading="isLoading"
            type="password"
            autocomplete="current-password"
            required
          />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.interac.imap_folder')" required>
          <BaseInput
            v-model.trim="form.folder"
            :content-loading="isLoading"
            required
          />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('settings.interac.move_to_folder')">
          <BaseInput
            v-model.trim="form.move_to"
            :content-loading="isLoading"
            :placeholder="$t('settings.interac.move_to_folder_help')"
          />
          <p class="mt-1 text-xs text-gray-500">
            {{ $t('settings.interac.move_to_folder_help') }}
          </p>
        </BaseInputGroup>
      </BaseInputGrid>

      <ul class="divide-y divide-gray-200 mt-6">
        <BaseSwitchSection
          v-model="form.mark_as_read"
          :title="$t('settings.interac.mark_as_read')"
          :description="$t('settings.interac.mark_as_read_desc')"
        />
        <BaseSwitchSection
          v-model="form.validate_cert"
          :title="$t('settings.interac.validate_cert')"
          :description="$t('settings.interac.validate_cert_desc')"
        />
      </ul>

      <div v-if="lastSyncResult" class="mt-4 text-sm text-gray-600">
        {{ $t('settings.interac.last_sync', {
          status: lastSyncResult.status,
          messages: lastSyncResult.messages_checked ?? 0,
          created: lastSyncResult.payments_created ?? 0,
        }) }}
      </div>

      <div v-if="connectionStatus" class="mt-4 text-sm text-gray-600">
        {{ $t('settings.interac.connection_status', { status: connectionStatus.status }) }}
      </div>

      <div class="flex justify-end gap-3 mt-8">
        <BaseButton
          type="submit"
          variant="primary"
          :loading="isSaving"
          :disabled="isSaving"
        >
          <template #left="slotProps">
            <BaseIcon :class="slotProps.class" name="ArrowDownOnSquareIcon" />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseSettingCard>
</template>

<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'
import { useI18n } from 'vue-i18n'

const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isLoading = ref(false)
const isSaving = ref(false)
const isSyncing = ref(false)
const lastSyncResult = ref(null)
const connectionStatus = ref(null)

const form = reactive({
  enabled: false,
  sender_filter: 'notify@payments.interac.ca',
  host: '',
  port: 993,
  encryption: 'ssl',
  username: '',
  password: '',
  folder: 'INBOX',
  mark_as_read: true,
  move_to: '',
  validate_cert: true,
})

const encryptionOptions = computed(() => [
  { label: t('settings.interac.encryption_ssl'), value: 'ssl' },
  { label: t('settings.interac.encryption_tls'), value: 'tls' },
  { label: t('settings.interac.encryption_none'), value: 'none' },
])

const canSync = computed(() => {
  return (
    form.enabled &&
    form.host &&
    form.port &&
    form.username &&
    form.password &&
    form.folder
  )
})

const settingKeys = [
  'interac_enabled',
  'interac_sender_filter',
  'interac_imap_host',
  'interac_imap_port',
  'interac_imap_encryption',
  'interac_imap_username',
  'interac_imap_password',
  'interac_imap_folder',
  'interac_imap_mark_as_read',
  'interac_imap_move_to',
  'interac_imap_validate_cert',
]

onMounted(loadSettings)

async function loadSettings() {
  try {
    isLoading.value = true
    const { data } = await companyStore.fetchCompanySettings(settingKeys)
    hydrateForm(data)
    connectionStatus.value = null
  } catch (error) {
    handleError(error)
  } finally {
    isLoading.value = false
  }
}

function hydrateForm(settings) {
  form.enabled = toBool(settings.interac_enabled, form.enabled)
  form.sender_filter = settings.interac_sender_filter || form.sender_filter
  form.host = settings.interac_imap_host || ''
  form.port = settings.interac_imap_port
    ? parseInt(settings.interac_imap_port)
    : form.port
  form.encryption = settings.interac_imap_encryption || form.encryption
  form.username = settings.interac_imap_username || ''
  form.password = settings.interac_imap_password || ''
  form.folder = settings.interac_imap_folder || form.folder
  form.mark_as_read = toBool(settings.interac_imap_mark_as_read, form.mark_as_read)
  form.move_to = settings.interac_imap_move_to || ''
  form.validate_cert = toBool(
    settings.interac_imap_validate_cert,
    form.validate_cert
  )
}

function toBool(value, fallback = false) {
  if (value === undefined || value === null) return fallback
  if (typeof value === 'boolean') return value
  if (typeof value === 'number') return value === 1
  return ['true', '1', 'yes', 'on', 'y', 't'].includes(
    String(value).toLowerCase()
  )
}

async function saveSettings() {
  const payload = {
    interac_enabled: form.enabled,
    interac_sender_filter: form.sender_filter,
    interac_imap_host: form.host,
    interac_imap_port: form.port,
    interac_imap_encryption: form.encryption,
    interac_imap_username: form.username,
    interac_imap_password: form.password,
    interac_imap_folder: form.folder,
    interac_imap_mark_as_read: form.mark_as_read,
    interac_imap_move_to: form.move_to,
    interac_imap_validate_cert: form.validate_cert,
  }

  try {
    isSaving.value = true
    const { data } = await companyStore.updateCompanySettings({
      data: { settings: payload },
      message: 'settings.interac.save_success',
    })

    if (data?.connection_status) {
      connectionStatus.value = data.connection_status
      const statusLabel = connectionStatusLabel(data.connection_status.status)
      notificationStore.showNotification({
        type: data.connection_status.status === 'ok' ? 'success' : 'warning',
        message: t('settings.interac.connection_status_toast', {
          status: statusLabel,
        }),
      })
    }
  } catch (error) {
    handleError(error)
  } finally {
    isSaving.value = false
  }
}

async function syncMailbox() {
  try {
    isSyncing.value = true
    const { data } = await axios.post('/api/v1/payments/interac/sync')
    lastSyncResult.value = data?.data

    const messages = data?.data?.messages_checked ?? 0
    const created = data?.data?.payments_created ?? 0
    const status = data?.data?.status ?? 'ok'

    notificationStore.showNotification({
      type: status === 'ok' ? 'success' : 'warning',
      message: t('settings.interac.sync_success', { messages, created }),
    })
  } catch (error) {
    handleError(error)
  } finally {
    isSyncing.value = false
  }
}

function connectionStatusLabel(status) {
  const map = {
    ok: t('settings.interac.connection_status_states.ok'),
    disabled: t('settings.interac.connection_status_states.disabled'),
    connection_failed: t('settings.interac.connection_status_states.connection_failed'),
    missing_extension: t('settings.interac.connection_status_states.missing_extension'),
  }

  return map[status] || status
}
</script>
