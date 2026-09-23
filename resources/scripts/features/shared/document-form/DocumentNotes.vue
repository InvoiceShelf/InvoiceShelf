<template>
  <!-- The group names the editor; the note picker sits on the label row -->
  <BaseInputGroup :label="$t('invoices.notes')" class="mb-6">
    <template #labelRight>
      <div class="z-20 text-sm font-semibold leading-5">
        <NoteSelectPopup :type="type" @select="onSelectNote" />
      </div>
    </template>
    <BaseCustomInput
      v-model="notes"
      :content-loading="contentLoading"
      :fields="fields"
    />
  </BaseInputGroup>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import NoteSelectPopup from './NoteSelectPopup.vue'
import type { DocumentFormData } from './use-document-calculations'
import type { NoteType } from '../../../types/domain/note'

interface Props {
  store: Record<string, unknown>
  storeProp: string
  fields: Record<string, unknown> | null
  type: NoteType | string | null
  contentLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  fields: null,
  type: null,
  contentLoading: false,
})

const formData = computed<DocumentFormData>(() => {
  return props.store[props.storeProp] as DocumentFormData
})

const notes = computed<string | null>({
  get: () => formData.value.notes,
  set: (value: string | null) => {
    formData.value.notes = value
  },
})

function onSelectNote(data: { notes: string }): void {
  formData.value.notes = String(data.notes)
}
</script>
