<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@v2/stores/modal.store'
import { useNotificationStore } from '@v2/stores/notification.store'
import {
  backupService,
  type CreateBackupPayload,
} from '@v2/api/services/backup.service'
import { diskService, type Disk } from '@v2/api/services/disk.service'
import {
  getErrorTranslationKey,
  handleApiError,
} from '@v2/utils/error-handling'

type BackupOption = CreateBackupPayload['option']

interface DiskOption extends Disk {
  display_name: string
}

interface BackupTypeOption {
  id: BackupOption
  label: string
}

interface BackupModalData {
  disks?: DiskOption[]
  selectedDiskId?: number | null
}

interface BackupForm {
  option: BackupOption | ''
  selectedDiskId: number | null
}

const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isSaving = ref(false)
const isFetchingInitialData = ref(false)
const disks = ref<DiskOption[]>([])

const form = reactive<BackupForm>({
  option: 'full',
  selectedDiskId: null,
})

const backupTypeOptions: BackupTypeOption[] = [
  {
    id: 'full',
    label: 'full',
  },
  {
    id: 'only-db',
    label: 'only-db',
  },
  {
    id: 'only-files',
    label: 'only-files',
  },
]

const modalActive = computed<boolean>(() => {
  return modalStore.active && modalStore.componentName === 'AdminBackupModal'
})

const rules = computed(() => ({
  option: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  selectedDiskId: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, form)

async function setInitialData(): Promise<void> {
  resetForm()
  isFetchingInitialData.value = true

  try {
    const modalData = isBackupModalData(modalStore.data) ? modalStore.data : null

    if (modalData?.disks?.length) {
      disks.value = modalData.disks
      form.selectedDiskId =
        modalData.selectedDiskId ??
        modalData.disks.find((disk) => disk.set_as_default)?.id ??
        modalData.disks[0]?.id ??
        null

      return
    }

    const response = await diskService.list({ limit: 'all' })

    disks.value = response.data.map((disk) => ({
      ...disk,
      display_name: `${disk.name} - [${disk.driver}]`,
    }))

    const selectedDiskId =
      modalStore.data &&
      typeof modalStore.data === 'object' &&
      'id' in (modalStore.data as Record<string, unknown>)
        ? Number((modalStore.data as Record<string, unknown>).id)
        : null

    form.selectedDiskId =
      disks.value.find((disk) => disk.id === selectedDiskId)?.id ??
      disks.value.find((disk) => disk.set_as_default)?.id ??
      disks.value[0]?.id ??
      null
  } catch (error: unknown) {
    showApiError(error)
  } finally {
    isFetchingInitialData.value = false
  }
}

async function createBackup(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid || !form.selectedDiskId) {
    return
  }

  isSaving.value = true

  try {
    const response = await backupService.create({
      option: form.option as BackupOption,
      file_disk_id: form.selectedDiskId,
    })

    if (response.success) {
      notificationStore.showNotification({
        type: 'success',
        message: t('settings.backup.created_message'),
      })
      modalStore.refreshData?.()
      closeModal()
    }
  } catch (error: unknown) {
    showApiError(error)
  } finally {
    isSaving.value = false
  }
}

function showApiError(error: unknown): void {
  const normalizedError = handleApiError(error)
  const translationKey = getErrorTranslationKey(normalizedError.message)

  notificationStore.showNotification({
    type: 'error',
    message: translationKey ? t(translationKey) : normalizedError.message,
  })
}

function resetForm(): void {
  form.option = 'full'
  form.selectedDiskId = null
  v$.value.$reset()
}

function closeModal(): void {
  modalStore.closeModal()

  setTimeout(() => {
    resetForm()
    disks.value = []
  }, 300)
}

function isBackupModalData(value: unknown): value is BackupModalData {
  return Boolean(value && typeof value === 'object')
}
</script>

<template>
  <BaseModal :show="modalActive" @close="closeModal" @open="setInitialData">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>

    <form @submit.prevent="createBackup">
      <div class="p-4 md:p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('settings.backup.select_backup_type')"
            :error="v$.option.$error && v$.option.$errors[0]?.$message"
            required
          >
            <BaseSelectInput
              v-model="form.option"
              :options="backupTypeOptions"
              :placeholder="$t('settings.backup.select_backup_type')"
              value-prop="id"
              @update:model-value="v$.option.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.disk.select_disk')"
            :error="
              v$.selectedDiskId.$error && v$.selectedDiskId.$errors[0]?.$message
            "
            required
          >
            <BaseMultiselect
              v-model="form.selectedDiskId"
              :options="disks"
              :content-loading="isFetchingInitialData"
              :can-deselect="false"
              :invalid="v$.selectedDiskId.$error"
              label="display_name"
              track-by="id"
              value-prop="id"
              searchable
              :placeholder="$t('settings.disk.select_disk')"
              @update:model-value="v$.selectedDiskId.$touch()"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          type="button"
          variant="primary-outline"
          class="mr-3"
          @click="closeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              name="ArrowDownOnSquareIcon"
              :class="slotProps.class"
            />
          </template>
          {{ $t('general.create') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
