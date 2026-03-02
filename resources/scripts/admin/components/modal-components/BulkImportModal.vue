<template>
  <BaseModal :show="modalActive" @close="closeModal">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-gray-500 cursor-pointer"
          @click="closeModal"
        />
      </div>
    </template>

    <form @submit.prevent="submitImport">
      <div class="p-8 sm:p-6">
        <BaseInputGroup :label="$t('expenses.upload_mutiple_receipts')">
          <BaseFileUploader
            v-model="files"
            :multiple="true"
            accept=".zip,.pdf,.jpg,.jpeg,.png"
            @change="onFileChange"
            @remove="onFileRemove"
          />
          <div class="mt-2 text-sm text-gray-500">
            {{ $t('expenses.bulk_import_description') }}
          </div>
        </BaseInputGroup>
      </div>

      <div class="flex justify-end p-4 border-t border-gray-200">
        <BaseButton
          type="button"
          variant="primary-outline"
          class="mr-3"
          @click="closeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isUploading"
          :disabled="isUploading || !files.length"
          variant="primary"
          type="submit"
        >
          {{ $t('expenses.bulk_import') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { useModalStore } from '@/scripts/stores/modal'
import { useExpenseStore } from '@/scripts/admin/stores/expense'
import { useNotificationStore } from '@/scripts/stores/notification'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

const modalStore = useModalStore()
const expenseStore = useExpenseStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const files = ref([])
const isUploading = ref(false)

const modalActive = computed(() => {
  return modalStore.active && modalStore.componentName === 'BulkImportModal'
})

function onFileChange(fieldName, fileList) {
  // Logic handled by v-model
}

function onFileRemove() {
  // Logic handled by v-model
}

async function submitImport() {
  if (files.value.length === 0) return

  isUploading.value = true
  
  const formData = new FormData()
  files.value.forEach((fileWrapper) => {
    formData.append('files[]', fileWrapper.fileObject)
  })
  
  try {
    const response = await axios.post('/api/v1/expenses/bulk-import', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    })

    notificationStore.showNotification({
      type: 'success',
      message: response.data.message,
    })

    // Refresh expenses list
    expenseStore.fetchExpenses()
    
    closeModal()
  } catch (error) {
    notificationStore.showNotification({
      type: 'error',
      message: error.response?.data?.message || t('general.something_went_wrong'),
    })
  } finally {
    isUploading.value = false
  }
}

function closeModal() {
  modalStore.closeModal()
  files.value = []
}
</script>
