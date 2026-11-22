<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { reminderService } from '@/scripts/api/services/reminder.service'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { handleApiError } from '@/scripts/utils/error-handling'
import type { ReminderSettings } from '@/scripts/types/domain/reminder'

/** Kept in step with ReminderSettings::MAX_OFFSET and MAX_OFFSETS. */
const MAX_OFFSET = 365
const MAX_OFFSETS = 10

const { t } = useI18n()
const notificationStore = useNotificationStore()

const form = ref<ReminderSettings | null>(null)
const isFetching = ref<boolean>(true)
const isSaving = ref<boolean>(false)
const newOffset = ref<string>('')

const hours = computed(() =>
  Array.from({ length: 24 }, (_, hour) => ({
    label: `${String(hour).padStart(2, '0')}:00`,
    value: hour,
  })),
)

const newOffsetValid = computed<boolean>(() => {
  const value = Number(newOffset.value)
  return (
    newOffset.value.trim() !== '' &&
    Number.isInteger(value) &&
    Math.abs(value) <= MAX_OFFSET &&
    !!form.value &&
    !form.value.offsets.includes(value) &&
    form.value.offsets.length < MAX_OFFSETS
  )
})

onMounted(async () => {
  try {
    form.value = await reminderService.settings()
  } catch (err: unknown) {
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    isFetching.value = false
  }
})

function offsetLabel(offset: number): string {
  if (offset === 0) {
    return t('reminders.on_due')
  }
  return offset < 0
    ? t('reminders.before_due', { count: -offset }, -offset)
    : t('reminders.after_due', { count: offset }, offset)
}

function addOffset(): void {
  if (!form.value || !newOffsetValid.value) {
    return
  }
  form.value.offsets = [...form.value.offsets, Number(newOffset.value)].sort((a, b) => a - b)
  newOffset.value = ''
}

function removeOffset(offset: number): void {
  if (form.value) {
    form.value.offsets = form.value.offsets.filter((o) => o !== offset)
  }
}

async function save(): Promise<void> {
  if (!form.value) {
    return
  }
  isSaving.value = true
  try {
    form.value = await reminderService.updateSettings(form.value)
    notificationStore.showNotification({ type: 'success', message: 'reminders.saved' })
  } catch (err: unknown) {
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseSettingCard :title="$t('reminders.title')" :description="$t('reminders.description')">
    <BaseContentPlaceholders v-if="isFetching" rounded>
      <BaseContentPlaceholdersBox v-for="n in 3" :key="n" class="w-full mt-4 h-14" rounded />
    </BaseContentPlaceholders>

    <form v-else-if="form" class="space-y-6" @submit.prevent="save">
      <BaseSwitchSection
        v-model="form.enabled"
        :title="$t('reminders.enabled')"
        :description="$t('reminders.enabled_desc')"
      />

      <BaseDivider />

      <section class="space-y-4">
        <h3 class="text-sm font-semibold text-heading">{{ $t('reminders.schedule') }}</h3>

        <BaseInputGroup :label="$t('reminders.days')" :help-text="$t('reminders.days_help')">
          <ul v-if="form.offsets.length" class="flex flex-wrap gap-2 mb-3">
            <li
              v-for="offset in form.offsets"
              :key="offset"
              class="inline-flex items-center gap-1 py-1 text-sm rounded-full ps-3 pe-1 bg-surface-tertiary text-body"
            >
              {{ offsetLabel(offset) }}
              <BaseIconButton
                icon="XMarkIcon"
                size="sm"
                :label="`${$t('general.remove')}: ${offsetLabel(offset)}`"
                class="w-6! h-6!"
                @click="removeOffset(offset)"
              />
            </li>
          </ul>
          <div class="flex items-center gap-2 max-w-xs">
            <BaseInput
              v-model="newOffset"
              type="number"
              :min="-MAX_OFFSET"
              :max="MAX_OFFSET"
              :aria-label="$t('reminders.days')"
              @keydown.enter.prevent="addOffset"
            />
            <BaseButton variant="primary-outline" type="button" :disabled="!newOffsetValid" @click="addOffset">
              {{ $t('reminders.add_day') }}
            </BaseButton>
          </div>
        </BaseInputGroup>

        <BaseInputGroup :label="$t('reminders.send_hour')" :help-text="$t('reminders.send_hour_help')" class="max-w-xs">
          <BaseMultiselect
            v-model="form.send_hour"
            :options="hours"
            label="label"
            value-prop="value"
            :can-deselect="false"
          />
        </BaseInputGroup>
      </section>

      <BaseDivider />

      <section class="space-y-4">
        <h3 class="text-sm font-semibold text-heading">{{ $t('reminders.email') }}</h3>

        <BaseInputGroup :label="$t('reminders.subject')" required>
          <BaseInput v-model="form.subject" type="text" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('reminders.body')" required>
          <BaseCustomInput v-model="form.body" :fields="['customer', 'company', 'invoice']" />
        </BaseInputGroup>

        <BaseSwitchSection v-model="form.attach_pdf" :title="$t('reminders.attach_pdf')" />
      </section>

      <BaseButton :loading="isSaving" :disabled="isSaving" variant="primary" type="submit">
        <template #left="slotProps">
          <BaseIcon v-if="!isSaving" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('general.save') }}
      </BaseButton>
    </form>
  </BaseSettingCard>
</template>
