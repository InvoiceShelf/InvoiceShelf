<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDialogStore } from '@v2/stores/dialog.store'
import { useNotificationStore } from '@v2/stores/notification.store'
import { settingService } from '@v2/api/services/setting.service'
import { updateService, type UpdateRelease } from '@v2/api/services/update.service'
import {
  getErrorTranslationKey,
  handleApiError,
} from '@v2/utils/error-handling'

type UpdateStepKey =
  | 'download'
  | 'unzip'
  | 'copy'
  | 'clean'
  | 'migrate'
  | 'finish'

type UpdateStepStatus = 'pending' | 'running' | 'finished' | 'error'

interface UpdateStep {
  key: UpdateStepKey
  translationKey: string
  status: UpdateStepStatus
  time: string | null
}

const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isCheckingForUpdate = ref(false)
const isUpdating = ref(false)
const insiderChannel = ref(false)
const currentVersion = ref('')
const updateRelease = ref<UpdateRelease | null>(null)
const isMinorUpdate = ref(false)

const updateSteps = ref<UpdateStep[]>([
  {
    key: 'download',
    translationKey: 'settings.update_app.download_zip_file',
    status: 'pending',
    time: null,
  },
  {
    key: 'unzip',
    translationKey: 'settings.update_app.unzipping_package',
    status: 'pending',
    time: null,
  },
  {
    key: 'copy',
    translationKey: 'settings.update_app.copying_files',
    status: 'pending',
    time: null,
  },
  {
    key: 'clean',
    translationKey: 'settings.update_app.cleaning_stale_files',
    status: 'pending',
    time: null,
  },
  {
    key: 'migrate',
    translationKey: 'settings.update_app.running_migrations',
    status: 'pending',
    time: null,
  },
  {
    key: 'finish',
    translationKey: 'settings.update_app.finishing_update',
    status: 'pending',
    time: null,
  },
])

const isUpdateAvailable = computed<boolean>(() => {
  return Boolean(updateRelease.value)
})

const requirementEntries = computed(() => {
  return Object.entries(updateRelease.value?.extensions ?? {})
})

const allowToUpdate = computed<boolean>(() => {
  return requirementEntries.value.every(([, isAvailable]) => isAvailable)
})

onMounted(async () => {
  window.addEventListener('beforeunload', preventUnloadDuringUpdate)
  await loadCurrentVersion()
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', preventUnloadDuringUpdate)
})

async function loadCurrentVersion(): Promise<void> {
  try {
    const response = await settingService.getAppVersion()
    currentVersion.value = response.version
    insiderChannel.value = response.channel === 'insider'
  } catch (error: unknown) {
    showApiError(error)
  }
}

async function checkUpdate(): Promise<void> {
  isCheckingForUpdate.value = true

  try {
    const response = await updateService.check(
      insiderChannel.value ? 'insider' : 'stable'
    )

    if (!response.release) {
      updateRelease.value = null
      notificationStore.showNotification({
        type: 'info',
        message: t('settings.update_app.latest_message'),
      })
      return
    }

    updateRelease.value = response.release
    isMinorUpdate.value = Boolean(response.is_minor)
    resetUpdateProgress()
  } catch (error: unknown) {
    updateRelease.value = null
    showApiError(error)
  } finally {
    isCheckingForUpdate.value = false
  }
}

async function startUpdate(): Promise<void> {
  if (!updateRelease.value) {
    return
  }

  const confirmed = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: t('settings.update_app.update_warning'),
    yesLabel: t('general.ok'),
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  })

  if (!confirmed) {
    return
  }

  if (!allowToUpdate.value) {
    notificationStore.showNotification({
      type: 'error',
      message: t('settings.update_app.requirements_not_met'),
    })
    return
  }

  resetUpdateProgress()
  isUpdating.value = true

  let updatePath: string | null = null

  try {
    for (const step of updateSteps.value) {
      step.status = 'running'

      switch (step.key) {
        case 'download': {
          const response = await updateService.download({
            version: updateRelease.value.version,
          })

          updatePath = extractPath(response.path)
          break
        }

        case 'unzip': {
          if (!updatePath) {
            throw new Error('Missing update package path.')
          }

          const response = await updateService.unzip({ path: updatePath })
          updatePath = extractPath(response.path) ?? updatePath
          break
        }

        case 'copy': {
          if (!updatePath) {
            throw new Error('Missing extracted update path.')
          }

          const response = await updateService.copy({ path: updatePath })
          updatePath = extractPath(response.path) ?? updatePath
          break
        }

        case 'clean':
          await updateService.clean({
            deleted_files: updateRelease.value.deleted_files ?? null,
          })
          break

        case 'migrate':
          await updateService.migrate()
          break

        case 'finish':
          await updateService.finish({
            installed: currentVersion.value,
            version: updateRelease.value.version,
          })
          break
      }

      step.status = 'finished'
      step.time = new Date().toLocaleTimeString()
    }

    notificationStore.showNotification({
      type: 'success',
      message: t('settings.update_app.update_success'),
    })

    setTimeout(() => {
      window.location.reload()
    }, 3000)
  } catch (error: unknown) {
    const currentStep = updateSteps.value.find((step) => step.status === 'running')

    if (currentStep) {
      currentStep.status = 'error'
      currentStep.time = new Date().toLocaleTimeString()
    }

    showApiError(error)
  } finally {
    isUpdating.value = false
  }
}

function resetUpdateProgress(): void {
  updateSteps.value = updateSteps.value.map((step) => ({
    ...step,
    status: 'pending',
    time: null,
  }))
}

function statusClass(step: UpdateStep): string {
  if (step.status === 'finished') {
    return 'text-status-green bg-success'
  }

  if (step.status === 'running') {
    return 'text-primary-700 bg-primary-100'
  }

  if (step.status === 'error') {
    return 'text-danger bg-red-200'
  }

  return 'text-muted bg-surface-muted'
}

function preventUnloadDuringUpdate(event: BeforeUnloadEvent): void {
  if (!isUpdating.value) {
    return
  }

  event.preventDefault()
  event.returnValue = 'Update is in progress!'
}

function extractPath(value: unknown): string | null {
  if (typeof value === 'string') {
    return value
  }

  return null
}

function showApiError(error: unknown): void {
  const normalizedError = handleApiError(error)
  const translationKey = getErrorTranslationKey(normalizedError.message)

  notificationStore.showNotification({
    type: 'error',
    message: translationKey ? t(translationKey) : normalizedError.message,
  })
}
</script>

<template>
  <BaseSettingCard
    :title="$t('settings.update_app.title')"
    :description="$t('settings.update_app.description')"
  >
    <div class="pb-8">
      <label class="text-sm font-medium input-label">
        {{ $t('settings.update_app.current_version') }}
      </label>

      <div class="w-full border-b-2 border-line-light border-solid pb-4">
        <div
          class="my-2 inline-block rounded-md border border-line-default bg-surface-muted p-3 text-sm text-body"
        >
          {{ currentVersion }}
        </div>
      </div>

      <div class="pt-4">
        <BaseCheckbox
          v-model="insiderChannel"
          :label="$t('settings.update_app.insider_consent')"
        />
      </div>

      <BaseButton
        :loading="isCheckingForUpdate"
        :disabled="isCheckingForUpdate || isUpdating"
        variant="primary-outline"
        class="mt-6"
        @click="checkUpdate"
      >
        {{ $t('settings.update_app.check_update') }}
      </BaseButton>

      <BaseDivider v-if="isUpdateAvailable" class="mt-6 mb-4" />

      <div v-if="isUpdateAvailable && updateRelease && !isUpdating" class="mt-4">
        <BaseHeading type="heading-title" class="mb-2">
          {{ $t('settings.update_app.avail_update') }}
        </BaseHeading>

        <div class="mb-3 rounded-md bg-primary-50 p-4">
          <div class="flex">
            <div class="shrink-0">
              <BaseIcon
                name="InformationCircleIcon"
                class="h-5 w-5 text-primary-400"
              />
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-primary-800">
                {{ $t('general.note') }}
              </h3>
              <div class="mt-2 text-sm text-primary-700">
                <p>{{ $t('settings.update_app.update_warning') }}</p>
              </div>
            </div>
          </div>
        </div>

        <label class="text-sm font-medium input-label">
          {{ $t('settings.update_app.next_version') }}
        </label>
        <br />
        <div
          class="my-2 inline-block rounded-md border border-line-default bg-surface-muted p-3 text-sm text-body"
        >
          {{ updateRelease.version }}
          <span v-if="isMinorUpdate" class="ml-2 text-xs text-muted">
            (minor)
          </span>
        </div>

        <div
          v-if="updateRelease.description"
          class="update-rich-text mt-4 max-w-[680px] text-sm leading-snug text-muted"
          v-html="updateRelease.description"
        />

        <div
          v-if="updateRelease.changelog"
          class="update-rich-text mt-4 max-w-[680px] text-sm leading-snug text-muted"
          v-html="updateRelease.changelog"
        />

        <div v-if="requirementEntries.length" class="mt-6">
          <label class="text-sm font-medium input-label">
            {{ $t('settings.update_app.requirements') }}
          </label>

          <table class="mt-2 w-full max-w-xl border border-line-default">
            <tbody>
              <tr
                v-for="([extension, available], index) in requirementEntries"
                :key="extension"
                :class="index === requirementEntries.length - 1 ? '' : 'border-b border-line-default'"
              >
                <td class="p-3 text-sm">
                  {{ extension }}
                </td>
                <td class="p-3 text-right text-sm">
                  <span
                    :class="available ? 'bg-success' : 'bg-red-500'"
                    class="inline-block h-4 w-4 rounded-full"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="!allowToUpdate"
          class="mt-6 rounded-md bg-red-50 p-4 text-sm text-red-700"
        >
          {{ $t('settings.update_app.requirements_not_met') }}
        </div>

        <BaseButton
          class="mt-10"
          variant="primary"
          :disabled="!allowToUpdate"
          @click="startUpdate"
        >
          {{ $t('settings.update_app.update') }}
        </BaseButton>
      </div>

      <div v-if="isUpdating" class="mt-4">
        <div class="mb-6 flex items-start justify-between">
          <div>
            <BaseHeading type="heading-title" class="mb-2">
              {{ $t('settings.update_app.update_progress') }}
            </BaseHeading>
            <p class="max-w-[480px] text-sm leading-snug text-muted">
              {{ $t('settings.update_app.progress_text') }}
            </p>
          </div>

          <BaseIcon
            name="ArrowPathIcon"
            class="h-6 w-6 animate-spin text-primary-400"
          />
        </div>

        <ul class="w-full list-none p-0">
          <li
            v-for="step in updateSteps"
            :key="step.key"
            class="flex w-full justify-between border-b border-line-default py-3 last:border-b-0"
          >
            <p class="m-0 text-sm leading-8">{{ $t(step.translationKey) }}</p>
            <div class="flex items-center">
              <span v-if="step.time" class="mr-3 text-xs text-muted">
                {{ step.time }}
              </span>
              <span
                :class="statusClass(step)"
                class="block rounded-full px-3 py-1 text-sm uppercase"
              >
                {{ step.status }}
              </span>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </BaseSettingCard>
</template>

<style scoped>
.update-rich-text :deep(ul) {
  list-style: disc;
  margin-left: 1.5rem;
}

.update-rich-text :deep(li) {
  margin-bottom: 0.25rem;
}
</style>
