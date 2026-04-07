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
import {
  getErrorTranslationKey,
  handleApiError,
} from '@v2/utils/error-handling'

type BackupOption = CreateBackupPayload['option']

interface BackupTypeOption {
  id: BackupOption
  label: string
}

interface BackupForm {
  option: BackupOption | ''
  file_disk_id: number | null
}

const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isSaving = ref(false)

const form = reactive<BackupForm>({
  option: 'full',
  file_disk_id: null,
})

const backupTypeOptions: BackupTypeOption[] = [
  { id: 'full', label: 'full' },
  { id: 'only-db', label: 'only-db' },
  { id: 'only-files', label: 'only-files' },
]

const modalActive = computed<boolean>(() => {
  return modalStore.active && modalStore.componentName === 'AdminBackupModal'
})

const rules = computed(() => ({
  option: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, form)

function setInitialData(): void {
  resetForm()

  const modalData = modalStore.data as Record<string, unknown> | null
  if (modalData?.file_disk_id) {
    form.file_disk_id = Number(modalData.file_disk_id)
  }
}

async function createBackup(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid || !form.file_disk_id) {
    return
  }

  isSaving.value = true

  try {
    const response = await backupService.create({
      option: form.option as BackupOption,
      file_disk_id: form.file_disk_id,
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
  form.file_disk_id = null
  v$.value.$reset()
}

function closeModal(): void {
  modalStore.closeModal()

  setTimeout(() => {
    resetForm()
  }, 300)
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
