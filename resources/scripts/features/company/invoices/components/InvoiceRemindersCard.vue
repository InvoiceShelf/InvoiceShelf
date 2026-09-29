<template>
  <BaseCard v-if="reminders && (reminders.remindable || reminders.history.length)">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
      <h2 class="font-semibold text-section text-heading">{{ $t('reminders.card_title') }}</h2>
      <BaseButton
        v-if="canSend && reminders.remindable && reminders.has_email"
        variant="primary-outline"
        size="sm"
        :loading="sending"
        :disabled="sending"
        @click="sendNow"
      >
        <template #left="slotProps">
          <BaseIcon name="PaperAirplaneIcon" :class="slotProps.class" aria-hidden="true" />
        </template>
        {{ $t('reminders.send_now') }}
      </BaseButton>
    </div>

    <div v-if="reminders.remindable" class="pb-3 space-y-2 text-sm">
      <p v-if="!reminders.enabled" class="text-muted">
        {{ $t('reminders.company_off') }}
        <router-link
          v-if="userStore.currentUser?.is_owner"
          to="/admin/settings/payment-reminders"
          class="font-medium text-primary-600 hover:text-primary-700"
        >
          {{ $t('reminders.title') }}
        </router-link>
      </p>
      <p v-else-if="reminders.customer_paused" class="text-muted">{{ $t('reminders.customer_paused') }}</p>
      <p v-else-if="!reminders.has_email" class="text-muted">{{ $t('reminders.no_email') }}</p>
      <template v-else>
        <BaseSwitchSection
          :model-value="reminders.paused"
          :title="$t('reminders.paused')"
          :disabled="!canSend || saving"
          @update:model-value="setPaused"
        />
        <p v-if="!reminders.paused" class="text-muted">
          {{ reminders.next ? $t('reminders.next', { date: formatDay(reminders.next.date) }) : $t('reminders.none_next') }}
        </p>
      </template>
    </div>

    <p v-if="!reminders.history.length" class="py-2 text-sm text-muted">{{ $t('reminders.history_empty') }}</p>
    <ul v-else class="divide-y divide-line-light">
      <li
        v-for="entry in reminders.history"
        :key="entry.id"
        class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"
      >
        <div class="min-w-0">
          <span class="font-medium text-heading">{{ describe(entry) }}</span>
          <span v-if="entry.created_at" class="block mt-0.5 text-xs text-muted">{{ formatDay(entry.created_at) }}</span>
          <span v-if="entry.status !== 'sent' && entry.error" class="block mt-0.5 text-xs text-muted">{{ errorText(entry.error) }}</span>
        </div>
        <span
          class="text-xs font-medium"
          :class="entry.status === 'sent' ? 'text-status-green' : entry.status === 'failed' ? 'text-status-red' : 'text-status-yellow'"
        >
          {{ $t(`reminders.status_${entry.status}`) }}
        </span>
      </li>
    </ul>
  </BaseCard>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { reminderService } from '@/scripts/api/services/reminder.service'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { handleApiError } from '@/scripts/utils/error-handling'
import type { InvoiceReminderEntry, InvoiceReminders } from '@/scripts/types/domain/reminder'

const props = defineProps<{
  invoiceId: number
  /** Changes when the invoice's status does, so the card re-reads */
  invoiceStatus?: string
  canSend: boolean
}>()

const { t, te, locale } = useI18n()
const notificationStore = useNotificationStore()
const userStore = useUserStore()

const reminders = ref<InvoiceReminders | null>(null)
const sending = ref<boolean>(false)
const saving = ref<boolean>(false)

function showError(err: unknown): void {
  notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
}

async function load(): Promise<void> {
  try {
    reminders.value = await reminderService.forInvoice(props.invoiceId)
  } catch {
    reminders.value = null
  }
}

async function sendNow(): Promise<void> {
  sending.value = true
  try {
    reminders.value = await reminderService.sendNow(props.invoiceId)
    notificationStore.showNotification({ type: 'success', message: 'reminders.sent_now' })
  } catch (err: unknown) {
    showError(err)
  } finally {
    sending.value = false
  }
}

async function setPaused(paused: boolean): Promise<void> {
  saving.value = true
  try {
    reminders.value = await reminderService.setPaused(props.invoiceId, paused)
  } catch (err: unknown) {
    showError(err)
  } finally {
    saving.value = false
  }
}

function offsetLabel(offset: number): string {
  if (offset === 0) {
    return t('reminders.on_due')
  }
  return offset < 0
    ? t('reminders.before_due', { count: -offset }, -offset)
    : t('reminders.after_due', { count: offset }, offset)
}

function describe(entry: InvoiceReminderEntry): string {
  return entry.offset_days === null ? t('reminders.manual') : offsetLabel(entry.offset_days)
}

function errorText(error: string): string {
  return te(`errors.${error}`) ? t(`errors.${error}`) : error
}

function formatDay(value: string): string {
  const date = new Date(value.length === 10 ? `${value}T00:00:00` : value)
  return new Intl.DateTimeFormat(String(locale.value).replace('_', '-'), { dateStyle: 'medium' }).format(date)
}

watch(() => [props.invoiceId, props.invoiceStatus], () => void load(), { immediate: true })
</script>
