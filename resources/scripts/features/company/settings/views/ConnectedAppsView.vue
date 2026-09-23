<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDialogStore } from '@/scripts/stores/dialog.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { mcpService } from '@/scripts/api/services/mcp.service'
import { handleApiError } from '@/scripts/utils/error-handling'
import { formatDate, DEFAULT_DATETIME_FORMAT } from '@/scripts/utils/format-date'
import CopyableValue from '@/scripts/features/shared/mcp/CopyableValue.vue'
import type { McpConnection, McpServerInfo } from '@/scripts/types/domain/mcp'

const { t } = useI18n()
const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()

const server = ref<McpServerInfo | null>(null)
const connections = ref<McpConnection[]>([])
const isFetching = ref<boolean>(true)
const busyId = ref<number | null>(null)

onMounted(() => {
  void load()
})

async function load(): Promise<void> {
  isFetching.value = true

  try {
    const [info, list] = await Promise.all([mcpService.server(), mcpService.listConnections()])
    server.value = info
    connections.value = list
  } catch (err: unknown) {
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    isFetching.value = false
  }
}

/**
 * How each assistant is pointed at this server. Claude Code and Cursor take
 * something to paste; the chat apps are set up in their own settings.
 */
const setups = computed(() => {
  const url = server.value?.server_url ?? ''

  return [
    {
      key: 'claude_code',
      hint: t('mcp.connected_apps.claude_code_hint'),
      snippet: `claude mcp add --transport http invoiceshelf ${url}`,
      multiline: false,
    },
    { key: 'claude', hint: t('mcp.connected_apps.claude_hint'), snippet: url, multiline: false },
    { key: 'chatgpt', hint: t('mcp.connected_apps.chatgpt_hint'), snippet: url, multiline: false },
    {
      key: 'cursor',
      hint: t('mcp.connected_apps.cursor_hint'),
      snippet: JSON.stringify({ mcpServers: { invoiceshelf: { url } } }, null, 2),
      multiline: true,
    },
  ]
})

function clientName(connection: McpConnection): string {
  return connection.client_name || t('mcp.connected_apps.unnamed_client')
}

function usedLabel(connection: McpConnection): string {
  return connection.last_used_at
    ? t('mcp.connected_apps.last_used', { date: formatDate(connection.last_used_at, DEFAULT_DATETIME_FORMAT) })
    : t('mcp.connected_apps.never_used')
}

async function makeReadOnly(connection: McpConnection): Promise<void> {
  busyId.value = connection.id

  try {
    const updated = await mcpService.makeReadOnly(connection.id)
    connections.value = connections.value.map((item) => (item.id === updated.id ? updated : item))
    notificationStore.showNotification({
      type: 'success',
      message: t('mcp.connected_apps.made_read_only', { client: clientName(connection) }),
    })
  } catch (err: unknown) {
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    busyId.value = null
  }
}

function confirmDisconnect(connection: McpConnection): void {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('mcp.connected_apps.disconnect_confirm', {
        client: clientName(connection),
        company: connection.company?.name ?? '',
      }),
      yesLabel: t('mcp.connected_apps.disconnect'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((confirmed: boolean) => {
      if (confirmed) {
        void disconnect(connection)
      }
    })
}

async function disconnect(connection: McpConnection): Promise<void> {
  busyId.value = connection.id

  try {
    await mcpService.disconnect(connection.id)
    connections.value = connections.value.filter((item) => item.id !== connection.id)
    notificationStore.showNotification({
      type: 'success',
      message: t('mcp.connected_apps.disconnected', { client: clientName(connection) }),
    })
  } catch (err: unknown) {
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    busyId.value = null
  }
}
</script>

<template>
  <BaseSettingCard
    :title="$t('mcp.connected_apps.connect_title')"
    :description="$t('mcp.connected_apps.connect_description')"
  >
    <BaseContentPlaceholders v-if="isFetching" rounded>
      <BaseContentPlaceholdersBox class="w-full h-24 mt-4" rounded />
    </BaseContentPlaceholders>

    <div
      v-else-if="server && !server.enabled"
      role="status"
      class="p-4 mt-4 rounded-lg bg-alert-warning-bg text-alert-warning-text"
    >
      <p class="text-sm font-medium">{{ $t('mcp.connected_apps.unavailable_title') }}</p>
      <p class="mt-1 text-sm">{{ $t('mcp.connected_apps.unavailable_description') }}</p>
    </div>

    <div v-else-if="server" class="mt-2 space-y-5">
      <div>
        <p class="mb-2 text-sm font-medium text-heading">{{ $t('mcp.connected_apps.server_url') }}</p>
        <CopyableValue :value="server.server_url" :label="$t('mcp.connected_apps.server_url')" />
      </div>

      <BaseTabGroup>
        <BaseTab
          v-for="setup in setups"
          :key="setup.key"
          :title="$t(`mcp.connected_apps.${setup.key}`)"
        >
          <p class="mb-2 text-sm text-body">{{ setup.hint }}</p>
          <CopyableValue
            :value="setup.snippet"
            :label="$t(`mcp.connected_apps.${setup.key}`)"
            :multiline="setup.multiline"
          />
        </BaseTab>
      </BaseTabGroup>
    </div>
  </BaseSettingCard>

  <BaseSettingCard
    :title="$t('mcp.connected_apps.list_title')"
    :description="$t('mcp.connected_apps.description')"
    class="mt-6"
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
      v-else-if="connections.length === 0"
      icon="SparklesIcon"
      :title="$t('mcp.connected_apps.none_title')"
      :description="$t('mcp.connected_apps.none_description')"
    />

    <template v-else>
      <ul class="border-t border-line-default divide-y divide-line-default">
        <li
          v-for="connection in connections"
          :key="connection.id"
          class="flex flex-wrap items-center justify-between gap-3 py-4"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <BaseIcon name="SparklesIcon" class="w-5 h-5 shrink-0 text-subtle" />

              <span class="text-sm font-medium truncate text-heading">
                {{ clientName(connection) }}
              </span>

              <span
                class="px-2 py-0.5 text-xs font-medium whitespace-nowrap rounded-full"
                :class="connection.access === 'write' ? 'bg-surface-tertiary text-primary-500' : 'bg-surface-tertiary text-body'"
              >
                {{ connection.access === 'write' ? $t('mcp.connected_apps.access_write') : $t('mcp.connected_apps.access_read') }}
              </span>
            </div>

            <p class="flex flex-wrap mt-1 text-sm gap-x-2 text-muted">
              <span v-if="connection.company">{{ connection.company.name }}</span>
              <span v-if="connection.company" aria-hidden="true">&middot;</span>
              <span v-if="connection.redirect_host">
                {{ $t('mcp.connected_apps.returns_to', { host: connection.redirect_host }) }}
              </span>
              <span v-if="connection.redirect_host" aria-hidden="true">&middot;</span>
              <span>{{ usedLabel(connection) }}</span>
            </p>
          </div>

          <div class="flex flex-wrap gap-2">
            <BaseButton
              v-if="connection.access === 'write'"
              variant="primary-outline"
              size="sm"
              :loading="busyId === connection.id"
              :disabled="busyId !== null"
              @click="makeReadOnly(connection)"
            >
              {{ $t('mcp.connected_apps.make_read_only') }}
            </BaseButton>

            <BaseButton
              variant="danger"
              size="sm"
              :loading="busyId === connection.id"
              :disabled="busyId !== null"
              @click="confirmDisconnect(connection)"
            >
              {{ $t('mcp.connected_apps.disconnect') }}
            </BaseButton>
          </div>
        </li>
      </ul>

      <p class="mt-4 text-sm text-muted">{{ $t('mcp.connected_apps.write_again_hint') }}</p>
    </template>
  </BaseSettingCard>
</template>
