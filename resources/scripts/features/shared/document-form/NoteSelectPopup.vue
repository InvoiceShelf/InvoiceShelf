<template>
  <div>
    <NoteModal />
    <div class="w-full">
    <Popover v-slot="{ open: isOpen }">
      <PopoverButton
        v-if="canViewNotes"
        class="flex items-center z-10 font-medium text-primary-400 focus:outline-hidden focus:border-none"
        @click="fetchInitialData"
      >
        <BaseIcon name="PlusIcon" class="w-4 h-4 font-medium text-primary-400" />
        {{ $t('general.insert_note') }}
      </PopoverButton>

      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-1 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-1 opacity-0"
      >
        <PopoverPanel
          v-slot="{ close }"
          class="absolute z-20 px-4 mt-3 sm:px-0 w-screen max-w-full left-0 top-3"
        >
          <div class="overflow-hidden rounded-md shadow-lg ring-1 ring-black/5">
            <div class="relative grid bg-surface">
              <div class="relative p-4">
                <BaseInput
                  v-model="textSearch"
                  :placeholder="$t('general.search')"
                  type="text"
                  class="text-heading"
                />
              </div>

              <div
                v-if="filteredNotes.length > 0"
                class="relative flex flex-col overflow-auto list max-h-36"
              >
                <div
                  v-for="(note, idx) in filteredNotes"
                  :key="idx"
                  tabindex="2"
                  class="px-6 py-4 border-b border-line-default border-solid cursor-pointer hover:bg-surface-tertiary hover:cursor-pointer last:border-b-0"
                  @click="selectNote(idx, close)"
                >
                  <div class="flex justify-between px-2">
                    <label
                      class="m-0 text-base font-semibold leading-tight text-body cursor-pointer"
                    >
                      {{ note.name }}
                    </label>
                  </div>
                </div>
              </div>
              <div v-else class="flex justify-center p-5 text-subtle">
                <label class="text-base text-muted">
                  {{ $t('general.no_note_found') }}
                </label>
              </div>
            </div>

            <button
              v-if="canManageNotes"
              type="button"
              class="h-10 flex items-center justify-center w-full px-2 py-3 bg-surface-muted border-none outline-hidden"
              @click="openNoteModal"
            >
              <BaseIcon name="CheckCircleIcon" class="text-primary-400" />
              <label
                class="m-0 ml-3 text-sm leading-none cursor-pointer font-base text-primary-400"
              >
                {{ $t('settings.customization.notes.add_new_note') }}
              </label>
            </button>
          </div>
        </PopoverPanel>
      </transition>
    </Popover>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
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

function selectNote(index: number, close: () => void): void {
  emit('select', { ...notes.value[index] })
  textSearch.value = null
  close()
}

function openNoteModal(): void {
  modalStore.openModal({
    title: t('settings.customization.notes.add_note'),
    componentName: 'NoteModal',
    size: 'lg',
    data: props.type,
    refreshData: () => fetchInitialData(),
  })
}
</script>
