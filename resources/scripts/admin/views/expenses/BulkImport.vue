<template>
  <BasePage class="relative">
    <BasePageHeader :title="$t('expenses.bulk_import')" class="mb-5">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem
          :title="$t('general.home')"
          to="/admin/dashboard"
        />
        <BaseBreadcrumbItem
          :title="$t('expenses.expense', 2)"
          to="/admin/expenses"
        />
        <BaseBreadcrumbItem :title="$t('expenses.bulk_import')" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <BaseCard>
      <div class="p-6">
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            {{ $t('expenses.upload_mutiple_receipts') }}
          </label>
          <BaseFileUploader
            v-model="files"
            :multiple="true"
            :hide-preview="true"
            :preserve-local-files="true"
            accept=".zip,.pdf,.jpg,.jpeg,.png"
            @change="onFileChange"
            @duplicates="onDuplicates"
          />
          <p class="mt-2 text-sm text-gray-500">
            {{ $t('expenses.bulk_import_description') }}
          </p>
        </div>

        <div v-if="files.length > 0" class="mt-8">
          <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
            <h3 class="text-lg font-medium text-gray-900">
              {{ $t('general.selected_files') }} ({{ selectedFiles.length }})
            </h3>

            <div class="flex items-center gap-3 w-full md:w-auto">
               <div class="w-64">
                 <BaseMultiselect
                    v-model="bulkCategory"
                    :options="categories"
                    value-prop="id"
                    label="name"
                    track-by="id"
                    :placeholder="$t('expenses.categories.select_a_category')"
                    searchable
                    class="w-full"
                 />
               </div>
               <BaseButton
                variant="primary-outline"
                @click="applyBulkCategory"
                :disabled="!bulkCategory || selectedFiles.length === 0"
              >
                {{ $t('expenses.bulk_import_apply_to_selected') }}
              </BaseButton>
            </div>

            <div class="space-x-3">
               <BaseButton
                variant="primary-outline"
                @click="selectAll"
              >
                {{ $t('general.select_all') }}
              </BaseButton>
              <BaseButton
                variant="danger-outline"
                @click="clearAll"
              >
                {{ $t('general.clear_all') }}
              </BaseButton>
            </div>
          </div>

          <div class="overflow-x-auto border rounded-lg" style="min-height: 300px;">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10">
                    <input
                      type="checkbox"
                      :checked="isAllSelected"
                      @change="toggleSelectAll"
                      class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300 rounded"
                    />
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('general.file_name') }}
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('expenses.category') }}
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('expenses.validation_status') }}
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('general.type') }}
                  </th>
                  <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ $t('general.actions') }}
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="(file, index) in files" :key="index">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <input
                      type="checkbox"
                      v-model="selectedIndices"
                      :value="index"
                      class="focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300 rounded"
                    />
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ file.name }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 w-64">
                     <BaseMultiselect
                        v-model="file.category_id"
                        :options="categories"
                        value-prop="id"
                        label="name"
                        track-by="id"
                        searchable
                        class="w-full"
                        :can-deselect="false"
                     />
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div v-if="file.isDuplicate" class="flex flex-col items-start text-yellow-500">
                        <div class="flex items-center gap-1">
                            <BaseIcon name="DocumentDuplicateIcon" class="h-5 w-5" />
                            <span class="text-xs">{{ $t('expenses.duplicate_file_name') }}</span>
                        </div>
                    </div>
                    <BaseIcon v-else name="DocumentCheckIcon" class="text-green-500 h-5 w-5" />
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ file.type || 'Unknown' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button
                      @click="removeFile(index)"
                      class="text-red-600 hover:text-red-900"
                    >
                      {{ $t('general.remove') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mt-8 flex justify-end border-t pt-6">
          <BaseButton
            variant="primary-outline"
            class="mr-3"
            @click="$router.push('/admin/expenses')"
          >
            {{ $t('general.cancel') }}
          </BaseButton>
          <BaseButton
            :loading="isUploading"
            :disabled="isUploading || selectedFiles.length === 0"
            variant="primary"
            @click="submitImport"
          >
            {{ $t('expenses.bulk_import') }}
          </BaseButton>
        </div>
      </div>
    </BaseCard>
  </BasePage>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useExpenseStore } from '@/scripts/admin/stores/expense'
import { useCategoryStore } from '@/scripts/admin/stores/category'
import { useNotificationStore } from '@/scripts/stores/notification'
import axios from 'axios'

const { t } = useI18n()
const router = useRouter()
const expenseStore = useExpenseStore()
const categoryStore = useCategoryStore()
const notificationStore = useNotificationStore()

const files = ref([])
const selectedIndices = ref([])
const isUploading = ref(false)
const categories = ref([])
const defaultCategoryId = ref(null)
const bulkCategory = ref(null)

onMounted(async () => {
  const res = await categoryStore.fetchCategories({ limit: 'all' })
  categories.value = res.data.data

  const unverified = categories.value.find(c => c.name === 'Unverified')
  if (unverified) {
    defaultCategoryId.value = unverified.id
  } else if (categories.value.length > 0) {
      // If Unverified not found, maybe use the first one or keep null
      // defaultCategoryId.value = categories.value[0].id
  }
})

const selectedFiles = computed(() => {
  return files.value.filter((_, index) => selectedIndices.value.includes(index))
})

const isAllSelected = computed(() => {
  return files.value.length > 0 && selectedIndices.value.length === files.value.length
})

watch(() => files.value.length, async (newLength, oldLength) => {
  const prevLength = oldLength || 0

  if (newLength > prevLength) {
      const start = prevLength
      const end = newLength

      // Assign category
      for (let i = start; i < end; i++) {
          if (files.value[i] && files.value[i].category_id === undefined && defaultCategoryId.value) {
              files.value[i].category_id = defaultCategoryId.value
          }
      }

      await checkDuplicates()

      // Select new non-duplicate files
      for (let i = start; i < end; i++) {
          if (files.value[i] && !files.value[i].isDuplicate) {
              if (!selectedIndices.value.includes(i)) {
                  selectedIndices.value.push(i)
              }
          }
      }
  }
})

async function checkDuplicates() {
  if (files.value.length === 0) return

  const fileNames = files.value.map(f => f.name)

  try {
    const response = await axios.post('/api/v1/expenses/check-duplicates', {
      file_names: fileNames
    })

    const duplicates = response.data.duplicates

    files.value.forEach((file, index) => {
      const isDup = duplicates.includes(file.name)
      file.isDuplicate = isDup

      if (isDup) {
          // Deselect if selected
          const selIndex = selectedIndices.value.indexOf(index)
          if (selIndex !== -1) {
              selectedIndices.value.splice(selIndex, 1)
          }
      }
    })
  } catch (error) {
    console.error(error)
  }
}

function onFileChange(fileList) {
  // files.value is updated by v-model
}

function onDuplicates(duplicates) {
  notificationStore.showNotification({
    type: 'warning',
    message: t('expenses.duplicate_files_ignored', { count: duplicates.length }),
  })
}

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedIndices.value = []
  } else {
    selectedIndices.value = files.value.map((_, index) => index)
  }
}

function selectAll() {
  selectedIndices.value = files.value.map((_, index) => index)
}

function clearAll() {
  files.value = []
  selectedIndices.value = []
}

function removeFile(index) {
  files.value.splice(index, 1)
  // Update selected indices
  selectedIndices.value = selectedIndices.value
    .filter(i => i !== index)
    .map(i => i > index ? i - 1 : i)
}

function applyBulkCategory() {
    if (!bulkCategory.value) return

    // Update selected files
    // Since selectedFiles returns references to objects in files array, this works
    selectedFiles.value.forEach(file => {
        file.category_id = bulkCategory.value
    })
}

async function submitImport() {
  if (selectedFiles.value.length === 0) return

  isUploading.value = true

  const formData = new FormData()
  selectedFiles.value.forEach((fileWrapper) => {
    formData.append('files[]', fileWrapper.fileObject || fileWrapper)
    if (fileWrapper.category_id) {
        formData.append('categories[]', fileWrapper.category_id)
    } else {
        formData.append('categories[]', '') // Send empty string to maintain index alignment
    }
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

    router.push('/admin/expenses')
  } catch (error) {
    notificationStore.showNotification({
      type: 'error',
      message: error.response?.data?.message || t('general.something_went_wrong'),
    })
  } finally {
    isUploading.value = false
  }
}
</script>
