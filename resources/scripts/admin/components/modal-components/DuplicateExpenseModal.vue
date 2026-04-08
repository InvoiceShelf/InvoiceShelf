<template>
  <BaseModal
    :show="modalActive"
    :initial-focus="initialFocusRef"
    @close="closeModal"
    @open="onModalOpen"
  >
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

    <form action="" @submit.prevent="submitDuplicate">
      <div
        ref="initialFocusRef"
        class="sr-only outline-none focus:outline-none"
        tabindex="-1"
        aria-hidden="true"
      />

      <div class="px-8 py-6 sm:p-6">
        <p class="mb-6 text-sm text-gray-600">
          {{ $t('expenses.duplicate_expense_modal_hint') }}
        </p>

        <BaseInputGroup
          :label="$t('expenses.expense_date')"
          variant="vertical"
          required
        >
          <BaseDatePicker
            v-model="selectedExpenseDate"
            :calendar-button="true"
            calendar-button-icon="calendar"
          />
        </BaseInputGroup>
      </div>

      <div
        class="
          z-0
          flex
          justify-end
          px-4
          py-4
          border-t border-gray-200 border-solid
        "
      >
        <BaseButton
          class="mr-2"
          variant="primary-outline"
          type="button"
          @click="closeModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton
          :loading="isDuplicating"
          :disabled="isDuplicating || !selectedExpenseDate"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isDuplicating"
              name="DocumentDuplicateIcon"
              :class="slotProps.class"
            />
          </template>
          {{ $t('expenses.duplicate_expense') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import moment from 'moment'
import { useRouter } from 'vue-router'
import { useModalStore } from '@/scripts/stores/modal'
import { useExpenseStore } from '@/scripts/admin/stores/expense'

const modalStore = useModalStore()
const expenseStore = useExpenseStore()
const router = useRouter()

const selectedExpenseDate = ref('')
const isDuplicating = ref(false)
const initialFocusRef = ref(null)

const modalActive = computed(
  () => modalStore.active && modalStore.componentName === 'DuplicateExpenseModal'
)

function toYmd(value) {
  if (!value) {
    return moment().format('YYYY-MM-DD')
  }

  const str = String(value)

  if (str.length >= 10 && /^\d{4}-\d{2}-\d{2}/.test(str)) {
    return str.slice(0, 10)
  }

  return moment(value).format('YYYY-MM-DD')
}

function onModalOpen() {
  selectedExpenseDate.value = toYmd(modalStore.data?.expense_date)
}

async function submitDuplicate() {
  if (!modalStore.data?.id || !selectedExpenseDate.value) {
    return
  }

  isDuplicating.value = true

  try {
    await expenseStore.duplicateExpense({
      id: modalStore.data.id,
      expense_date: selectedExpenseDate.value,
    })

    modalStore.refreshData && modalStore.refreshData()
    closeModal()
    router.push('/admin/expenses')
  } finally {
    isDuplicating.value = false
  }
}

function closeModal() {
  modalStore.closeModal()
  selectedExpenseDate.value = ''
}
</script>
