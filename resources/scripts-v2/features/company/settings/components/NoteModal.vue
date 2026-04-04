<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { required, minLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@v2/stores/modal.store'
import { useNotificationStore } from '@v2/stores/notification.store'
import { noteService } from '@v2/api/services/note.service'
import type { CreateNotePayload } from '@v2/api/services/note.service'

interface NoteForm {
  id: number | null
  name: string
  type: string
  notes: string
  is_default: boolean
}

const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const route = useRoute()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const isEdit = ref<boolean>(false)

const currentNote = ref<NoteForm>({
  id: null,
  name: '',
  type: 'Invoice',
  notes: '',
  is_default: false,
})

const types = reactive([
  { label: t('settings.customization.notes.types.invoice'), value: 'Invoice' },
  { label: t('settings.customization.notes.types.estimate'), value: 'Estimate' },
  { label: t('settings.customization.notes.types.payment'), value: 'Payment' },
])

const fields = ref<string[]>(['customer', 'customerCustom'])

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'NoteModal'
)

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3)
    ),
  },
  notes: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  type: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, currentNote)

watch(
  () => currentNote.value.type,
  () => {
    setFields()
  }
)

onMounted(() => {
  if (route.name === 'estimates.create') {
    currentNote.value.type = 'Estimate'
  } else if (
    route.name === 'invoices.create' ||
    route.name === 'recurring-invoices.create'
  ) {
    currentNote.value.type = 'Invoice'
  } else {
    currentNote.value.type = 'Payment'
  }
})

function setFields(): void {
  fields.value = ['customer', 'customerCustom']

  if (currentNote.value.type === 'Invoice') {
    fields.value.push('invoice', 'invoiceCustom')
  }
  if (currentNote.value.type === 'Estimate') {
    fields.value.push('estimate', 'estimateCustom')
  }
  if (currentNote.value.type === 'Payment') {
    fields.value.push('payment', 'paymentCustom')
  }
}

async function setInitialData(): Promise<void> {
  if (modalStore.data && typeof modalStore.data === 'number') {
    isEdit.value = true
    const response = await noteService.get(modalStore.data)
    if (response.data) {
      const note = response.data
      currentNote.value = {
        id: note.id,
        name: note.name,
        type: note.type,
        notes: note.notes,
        is_default: note.is_default ?? false,
      }
    }
  } else {
    isEdit.value = false
    resetForm()
  }
  setFields()
}

async function submitNote(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true

  const payload: CreateNotePayload = {
    name: currentNote.value.name,
    type: currentNote.value.type,
    notes: currentNote.value.notes,
    is_default: currentNote.value.is_default,
  }

  try {
    if (isEdit.value && currentNote.value.id) {
      const res = await noteService.update(currentNote.value.id, payload)
      if (res.data) {
        notificationStore.showNotification({
          type: 'success',
          message: t('settings.customization.notes.note_updated'),
        })
      }
    } else {
      const res = await noteService.create(payload)
      if (res) {
        notificationStore.showNotification({
          type: 'success',
          message: t('settings.customization.notes.note_added'),
        })
      }
    }

    isSaving.value = false
    if (modalStore.refreshData) {
      modalStore.refreshData()
    }
    closeNoteModal()
  } catch {
    isSaving.value = false
  }
}

function resetForm(): void {
  currentNote.value = {
    id: null,
    name: '',
    type: 'Invoice',
    notes: '',
    is_default: false,
  }
}

function closeNoteModal(): void {
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
    @close="closeNoteModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-muted cursor-pointer"
          @click="closeNoteModal"
        />
      </div>
    </template>
    <form action="" @submit.prevent="submitNote">
      <div class="px-8 py-8 sm:p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('settings.customization.notes.name')"
            variant="vertical"
            :error="v$.name.$error && v$.name.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="currentNote.name"
              :invalid="v$.name.$error"
              type="text"
              @input="v$.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.customization.notes.type')"
            :error="v$.type.$error && v$.type.$errors[0].$message"
            required
          >
            <BaseMultiselect
              v-model="currentNote.type"
              :options="types"
              value-prop="value"
              class="mt-2"
            />
          </BaseInputGroup>

          <BaseSwitchSection
            v-model="currentNote.is_default"
            :title="$t('settings.customization.notes.is_default')"
            :description="
              $t('settings.customization.notes.is_default_description')
            "
          />

          <BaseInputGroup
            :label="$t('settings.customization.notes.notes')"
            :error="v$.notes.$error && v$.notes.$errors[0].$message"
            required
          >
            <BaseCustomInput
              v-model="currentNote.notes"
              :invalid="v$.notes.$error"
              :fields="fields"
              @input="v$.notes.$touch()"
            />
          </BaseInputGroup>
        </BaseInputGrid>
      </div>
      <div
        class="z-0 flex justify-end px-4 py-4 border-t border-solid border-line-default"
      >
        <BaseButton
          class="mr-2"
          variant="primary-outline"
          type="button"
          @click="closeNoteModal"
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
            <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ isEdit ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<style>
.note-modal {
  .header-editior .editor-menu-bar {
    margin-left: 0.5px;
    margin-right: 0px;
  }
}
</style>
