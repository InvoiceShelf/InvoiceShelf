<template>
  <div>
    <NoteModal />
    <div class="w-full">
    <PopoverRoot v-slot="{ close }">
      <PopoverTrigger
        v-if="canViewNotes"
        class="z-10 flex items-center gap-1 font-medium rounded-md text-primary-600 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
        @click="fetchInitialData"
      >
        <BaseIcon name="PlusIcon" class="w-4 h-4" />
        {{ $t('general.insert_note') }}
      </PopoverTrigger>

      <PopoverPortal>
        <!-- As wide as the notes field it sits over, and ending with it -->
        <PopoverContent
          side="bottom"
          align="end"
          :side-offset="4"
          :collision-padding="16"
          class="
            z-20 w-[min(35rem,calc(100vw-2rem))] text-sm font-semibold leading-5 focus:outline-hidden
            data-[state=open]:animate-rise-in data-[state=closed]:animate-rise-out
          "
        >
          <div class="overflow-hidden rounded-md shadow-lg ring-1 ring-black/5">
            <div class="relative grid bg-surface">
              <div class="relative p-4">
                <BaseInput
                  v-model="textSearch"
                  :placeholder="$t('general.search')"
                  :aria-label="$t('general.search')"
                  type="search"
                  class="text-heading"
                />
              </div>

              <div
                v-if="filteredNotes.length > 0"
                class="relative flex flex-col overflow-auto list max-h-36"
              >
                <button
                  v-for="(note, idx) in filteredNotes"
                  :key="idx"
                  type="button"
                  class="
                    w-full px-6 py-4 text-start border-b border-line-default border-solid last:border-b-0
                    hover:bg-surface-tertiary focus:outline-hidden focus-visible:bg-surface-tertiary
                    focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-focus
                  "
                  @click="selectNote(note, close)"
                >
                  <span class="flex justify-between px-2">
                    <span class="m-0 text-base font-semibold leading-tight text-body">
                      {{ note.name }}
                    </span>
                  </span>
                </button>
              </div>
              <div v-else class="flex justify-center p-5" role="status">
                <span class="text-base text-muted">
                  {{ $t('general.no_note_found') }}
                </span>
              </div>
            </div>

            <button
              v-if="canManageNotes"
              type="button"
              class="flex items-center justify-center w-full h-10 px-2 py-3 border-none bg-surface-muted text-primary-600 outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-focus"
              @click="openNoteModal"
            >
              <BaseIcon name="CheckCircleIcon" />
              <span class="m-0 ms-3 text-sm leading-none font-base">
                {{ $t('settings.customization.notes.add_new_note') }}
              </span>
            </button>
          </div>
        </PopoverContent>
      </PopoverPortal>
    </PopoverRoot>
    </div>
  </div>
</template>

<script setup lang="ts">
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../stores/modal.store'
import { useUserStore } from '../../../stores/user.store'
import { ABILITIES } from '../../../config/abilities'
import NoteModal from '../../company/settings/components/NoteModal.vue'
import type { Note } from '../../../types/domain/note'
import { noteService } from '../../../api/services/note.service'

interface Props {
  type?: string | null
}

interface Emits {
  (e: 'select', data: Note): void
}

const props = withDefaults(defineProps<Props>(), {
  type: null,
})

const emit = defineEmits<Emits>()

const { t } = useI18n()
const modalStore = useModalStore()
const userStore = useUserStore()
const textSearch = ref<string | null>(null)
const notes = ref<Note[]>([])

const canViewNotes = computed<boolean>(() =>
  userStore.hasAbilities(ABILITIES.VIEW_NOTE),
)

const canManageNotes = computed<boolean>(() =>
  userStore.hasAbilities(ABILITIES.MANAGE_NOTE),
)

const filteredNotes = computed<Note[]>(() => {
  if (textSearch.value) {
    return notes.value.filter((el) =>
      el.name.toLowerCase().includes(textSearch.value!.toLowerCase()),
    )
  }
  return notes.value
})

async function fetchInitialData(): Promise<void> {
  try {
    const response = await noteService.list({
      search: '',
      orderByField: '',
      orderBy: 'asc',
    })
    notes.value = (response as unknown as { data: Note[] }).data ?? []
  } catch {
    // Silently fail
  }
}

// By note, not position: the list may be filtered by a search
function selectNote(note: Note, close?: () => void): void {
  emit('select', { ...note })
  textSearch.value = null
  close?.()
}

// Loaded up front, so the list does not open empty and then fill
if (canViewNotes.value) {
  void fetchInitialData()
}

function openNoteModal(): void {
  modalStore.openModal({
    title: t('settings.customization.notes.add_note'),
    componentName: 'NoteModal',
    size: 'lg',
    data: props.type,
    // A note created from here is inserted straight away
    refreshData: async (note: unknown) => {
      await fetchInitialData()

      if ((note as Note | undefined)?.id) {
        selectNote(note as Note)
      }
    },
  })
}
</script>
