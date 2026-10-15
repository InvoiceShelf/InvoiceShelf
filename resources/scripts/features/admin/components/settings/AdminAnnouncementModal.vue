<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { announcementService } from '@/scripts/api/services/announcement.service'
import { getErrorTranslationKey, handleApiError } from '@/scripts/utils/error-handling'
import type {
  Announcement,
  AnnouncementPayload,
  AnnouncementTranslation,
} from '@/scripts/types/domain/announcement'

/**
 * Writes an announcement for everyone on this install: plain text and one
 * optional link, with the text per language.
 */
const props = defineProps<{
  show: boolean
  announcement: Announcement | null
}>()

const emit = defineEmits<{ close: []; saved: [] }>()

const { t } = useI18n()
const globalStore = useGlobalStore()
const notificationStore = useNotificationStore()

const form = ref<AnnouncementPayload>(blank())
const errors = ref<Record<string, string>>({})
const isSaving = ref<boolean>(false)
const newLocale = ref<string | null>(null)

const levels = computed(() => [
  { value: 'info', label: t('announcements.levels.info') },
  { value: 'warning', label: t('announcements.levels.warning') },
  { value: 'critical', label: t('announcements.levels.critical') },
])

const audiences = computed(() => [
  { value: 'everyone', label: t('announcements.audiences.everyone') },
  { value: 'admins', label: t('announcements.audiences.admins') },
])

const languages = computed(() =>
  ((globalStore.config?.languages as Array<{ code: string; name: string }>) ?? []).filter(
    (language) => !(language.code in form.value.translations),
  ),
)

function languageName(code: string): string {
  const all = (globalStore.config?.languages as Array<{ code: string; name: string }>) ?? []
  return all.find((language) => language.code === code)?.name ?? code
}

function blank(): AnnouncementPayload {
  return {
    title: '',
    body: '',
    link_url: null,
    link_label: null,
    level: 'info',
    audience: 'everyone',
    starts_at: null,
    ends_at: null,
    translations: {},
  }
}

watch(
  () => [props.show, props.announcement] as const,
  ([show]) => {
    if (!show) return
    const a = props.announcement
    form.value = a
      ? {
          title: a.title,
          body: a.body,
          link_url: a.link_url,
          link_label: a.link_label,
          level: a.level,
          audience: a.audience,
          starts_at: a.starts_at,
          ends_at: a.ends_at,
          translations: JSON.parse(JSON.stringify(a.translations ?? {})) as Record<string, AnnouncementTranslation>,
        }
      : blank()
    errors.value = {}
  },
  { immediate: true },
)

function addLanguage(): void {
  if (!newLocale.value) return
  form.value.translations = { ...form.value.translations, [newLocale.value]: { title: '', body: '', link_label: '' } }
  newLocale.value = null
}

function removeLanguage(code: string): void {
  const next = { ...form.value.translations }
  delete next[code]
  form.value.translations = next
}

async function save(): Promise<void> {
  isSaving.value = true
  errors.value = {}
  try {
    const payload = { ...form.value, link_url: form.value.link_url || null, link_label: form.value.link_label || null }
    if (props.announcement) {
      await announcementService.update(props.announcement.id, payload)
    } else {
      await announcementService.create(payload)
    }
    notificationStore.showNotification({ type: 'success', message: t('announcements.saved') })
    emit('saved')
    emit('close')
  } catch (err: unknown) {
    const error = handleApiError(err)
    if (error.isValidationError) {
      errors.value = Object.fromEntries(
        Object.entries(error.validationErrors).map(([field, messages]) => [field, messages[0]]),
      )
    } else {
      notificationStore.showNotification({ type: 'error', message: getErrorTranslationKey(error.message) ?? error.message })
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal :show="show" closable size="lg" @close="emit('close')">
    <template #header>
      {{ announcement ? $t('announcements.edit') : $t('announcements.add') }}
    </template>

    <form @submit.prevent="save">
      <div class="px-4 py-4 space-y-4 md:px-8 md:py-6">
        <BaseInputGroup :label="$t('announcements.fields.title')" :error="errors.title" required>
          <BaseInput v-model="form.title" type="text" :invalid="!!errors.title" maxlength="160" />
        </BaseInputGroup>

        <BaseInputGroup :label="$t('announcements.fields.body')" :error="errors.body" required>
          <BaseTextarea v-model="form.body" rows="3" :invalid="!!errors.body" maxlength="2000" />
        </BaseInputGroup>

        <div class="grid gap-4 md:grid-cols-2">
          <BaseInputGroup :label="$t('announcements.fields.link_url')" :error="errors.link_url">
            <BaseInput v-model="form.link_url" type="url" placeholder="https://" :invalid="!!errors.link_url" />
          </BaseInputGroup>
          <BaseInputGroup :label="$t('announcements.fields.link_label')" :error="errors.link_label">
            <BaseInput v-model="form.link_label" type="text" :invalid="!!errors.link_label" maxlength="60" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('announcements.fields.level')" :help-text="$t('announcements.level_help')">
            <BaseMultiselect v-model="form.level" :options="levels" label="label" value-prop="value" :can-deselect="false" />
          </BaseInputGroup>
          <BaseInputGroup :label="$t('announcements.fields.audience')">
            <BaseMultiselect v-model="form.audience" :options="audiences" label="label" value-prop="value" :can-deselect="false" />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('announcements.fields.starts_at')" :error="errors.starts_at">
            <BaseDatePicker v-model="form.starts_at" enable-time />
          </BaseInputGroup>
          <BaseInputGroup :label="$t('announcements.fields.ends_at')" :error="errors.ends_at">
            <BaseDatePicker v-model="form.ends_at" enable-time />
          </BaseInputGroup>
        </div>

        <div class="pt-2 space-y-3">
          <h3 class="text-sm font-semibold text-heading">{{ $t('announcements.translations') }}</h3>
          <p class="text-sm text-muted">{{ $t('announcements.translations_help') }}</p>
          <p v-if="errors.translations" class="text-sm text-danger">{{ errors.translations }}</p>

          <div
            v-for="(translation, code) in form.translations"
            :key="code"
            class="p-3 space-y-2 border rounded-lg border-line-default"
          >
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-heading">{{ languageName(String(code)) }}</span>
              <BaseIconButton
                icon="XMarkIcon"
                size="sm"
                :label="`${$t('general.remove')}: ${languageName(String(code))}`"
                @click="removeLanguage(String(code))"
              />
            </div>
            <BaseInput v-model="translation.title" type="text" :placeholder="$t('announcements.fields.title')" :aria-label="`${$t('announcements.fields.title')} (${code})`" />
            <BaseTextarea v-model="translation.body" rows="2" :placeholder="$t('announcements.fields.body')" :aria-label="`${$t('announcements.fields.body')} (${code})`" />
            <BaseInput v-model="translation.link_label" type="text" :placeholder="$t('announcements.fields.link_label')" :aria-label="`${$t('announcements.fields.link_label')} (${code})`" />
          </div>

          <div class="flex items-center gap-2 max-w-sm">
            <BaseMultiselect
              v-model="newLocale"
              :options="languages"
              label="name"
              value-prop="code"
              searchable
              :placeholder="$t('announcements.add_language')"
            />
            <BaseButton type="button" variant="primary-outline" :disabled="!newLocale" @click="addLanguage">
              {{ $t('announcements.add_button') }}
            </BaseButton>
          </div>
        </div>
      </div>

      <div class="z-0 flex justify-end p-4 border-t border-solid border-line-default">
        <BaseButton class="text-sm me-3" variant="primary-outline" type="button" @click="emit('close')">
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton :loading="isSaving" :disabled="isSaving" variant="primary" type="submit">
          <template #left="slotProps">
            <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
