<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, maxLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@v2/stores/modal.store'
import { expenseService } from '@v2/api/services/expense.service'

interface CategoryForm {
  id: number | null
  name: string
  description: string
}

const modalStore = useModalStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const isEdit = ref<boolean>(false)

const currentCategory = ref<CategoryForm>({
  id: null,
  name: '',
  description: '',
})

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'CategoryModal'
)

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3)
    ),
  },
  description: {
    maxLength: helpers.withMessage(
      t('validation.description_maxlength', { count: 255 }),
      maxLength(255)
    ),
  },
}))

const v$ = useVuelidate(rules, currentCategory)

async function setInitialData(): Promise<void> {
  if (modalStore.data && typeof modalStore.data === 'number') {
    isEdit.value = true
    const response = await expenseService.getCategory(modalStore.data)
    if (response.data) {
      currentCategory.value = {
        id: response.data.id,
        name: response.data.name,
        description: response.data.description ?? '',
      }
    }
  } else {
    isEdit.value = false
    resetForm()
  }
}

async function submitCategoryData(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true

  try {
    if (isEdit.value && currentCategory.value.id) {
      await expenseService.updateCategory(currentCategory.value.id, {
        name: currentCategory.value.name,
        description: currentCategory.value.description || null,
      })
    } else {
      await expenseService.createCategory({
        name: currentCategory.value.name,
        description: currentCategory.value.description || null,
      })
    }

    isSaving.value = false
    if (modalStore.refreshData) {
      modalStore.refreshData()
    }
    closeCategoryModal()
  } catch {
    isSaving.value = false
  }
}

function resetForm(): void {
  currentCategory.value = {
    id: null,
    name: '',
    description: '',
  }
}

function closeCategoryModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    resetForm()
    v$.value.$reset()
  }, 300)
}
</script>

<template>
  <BaseModal
    :show="modalActive"
    @close="closeCategoryModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeCategoryModal"
        />
      </div>
    </template>

    <form action="" @submit.prevent="submitCategoryData">
      <div class="p-8 sm:p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('expenses.category')"
            :error="v$.name.$error && v$.name.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="currentCategory.name"
              :invalid="v$.name.$error"
              type="text"
              @input="v$.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('expenses.description')"
            :error="
              v$.description.$error && v$.description.$errors[0].$message
            "
          >
            <BaseTextarea
              v-model="currentCategory.description"
              rows="4"
              cols="50"
              @input="v$.description.$touch()"
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
          class="mr-3 text-sm"
          @click="closeCategoryModal"
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
          {{ isEdit ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
