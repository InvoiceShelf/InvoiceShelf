<template>
  <div
    ref="root"
    tabindex="-1"
    :aria-label="selected ? `${label}: ${selected.name}` : label"
    class="rounded-xl focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
  >
    <BaseContentPlaceholders v-if="contentLoading"
      ><BaseContentPlaceholdersBox :rounded="true" class="h-32 w-full"
    /></BaseContentPlaceholders>
    <div
      v-else-if="selected"
      class="flex flex-col gap-4 rounded-xl border glass p-4 md:p-5"
    >
      <div class="flex items-start gap-3">
        <span
          class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-btn-primary text-sm font-semibold text-on-primary"
          aria-hidden="true"
          >{{ initials(selected.name) }}</span
        >
        <div class="min-w-0 flex-1">
          <p class="text-xs font-medium text-muted">{{ label }}</p>
          <p class="break-words text-base font-semibold text-heading">
            {{ selected.name }}
          </p>
          <p
            v-if="selected.subtitle"
            class="mt-1 break-words text-sm text-muted"
          >
            {{ selected.subtitle }}
          </p>
        </div>
        <div v-if="!disabled" class="-me-1 flex shrink-0 gap-1">
          <button
            v-if="canEdit"
            type="button"
            class="flex h-10 w-10 items-center justify-center rounded-lg text-muted hover:bg-hover-strong hover:text-heading md:h-9 md:w-9"
            :aria-label="editLabel"
            @click="edit"
          >
            <BaseIcon name="PencilSquareIcon" class="h-5 w-5" />
          </button>
          <button
            v-if="canClear"
            type="button"
            class="flex h-10 w-10 items-center justify-center rounded-lg text-muted hover:bg-hover-strong hover:text-heading md:h-9 md:w-9"
            :aria-label="$t('general.deselect')"
            @click="clear"
          >
            <BaseIcon name="XMarkIcon" class="h-5 w-5" />
          </button>
        </div>
      </div>
      <dl
        v-if="selected.addresses?.length"
        class="grid gap-4 border-t border-line-light pt-4 sm:grid-cols-2"
      >
        <div
          v-for="address in selected.addresses"
          :key="address.label"
          class="min-w-0"
        >
          <dt class="mb-1 text-xs font-medium text-muted">
            {{ address.label }}
          </dt>
          <dd
            v-for="(line, index) in address.lines"
            :key="index"
            class="break-words text-sm text-body"
          >
            {{ line }}
          </dd>
        </div>
      </dl>
    </div>
    <div v-else class="relative">
      <button
        ref="trigger"
        type="button"
        :aria-expanded="open"
        :aria-controls="id"
        aria-haspopup="dialog"
        :disabled="disabled || !canBrowse"
        :data-invalid="error ? 'true' : undefined"
        :class="
          error
            ? 'border-danger'
            : 'border-line-strong hover:border-primary-400'
        "
        class="flex w-full items-center gap-4 rounded-xl border-2 border-dashed bg-surface/50 p-4 text-start transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus disabled:cursor-default md:p-5"
        @click="openPicker"
      >
        <span
          class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600"
          aria-hidden="true"
          ><BaseIcon name="UserPlusIcon" class="h-5 w-5"
        /></span>
        <span class="min-w-0 flex-1"
          ><span class="block text-base font-semibold text-heading"
            >{{ label }}
            <span v-if="required" class="text-danger" aria-hidden="true"
              >*</span
            ></span
          ><span
            class="text-sm"
            :class="error ? 'text-danger' : 'text-muted'"
            >{{ error || placeholder }}</span
          ></span
        >
        <BaseIcon
          v-if="canBrowse && !disabled"
          name="ChevronDownIcon"
          class="h-5 w-5 shrink-0 text-subtle"
        />
      </button>
      <button
        v-if="!disabled && !canBrowse && canCreate"
        type="button"
        class="mt-3 text-sm font-medium text-primary-600"
        @click="create"
      >
        {{ createLabel }}
      </button>
      <Teleport to="body" :disabled="!isPhone">
        <component
          :is="isPhone ? FocusScope : 'div'"
          v-if="open"
          :id="id"
          ref="panel"
          v-bind="isPhone ? { trapped: true, loop: true } : {}"
          role="dialog"
          :aria-modal="isPhone ? 'true' : undefined"
          :aria-label="placeholder"
          :class="
            isPhone
              ? 'fixed inset-0 z-50 flex flex-col bg-surface'
              : 'absolute inset-x-0 z-30 mt-2 overflow-hidden rounded-xl border glass-strong'
          "
          @mount-auto-focus="leaveFocus"
          @unmount-auto-focus="leaveFocus"
        >
          <div
            v-if="isPhone"
            class="safe-header flex items-center gap-2 border-b border-line-light px-2"
          >
            <button
              type="button"
              class="flex h-11 w-11 items-center justify-center rounded-lg text-muted"
              :aria-label="$t('general.close')"
              @click="closePicker"
            >
              <BaseIcon name="XMarkIcon" class="h-6 w-6" />
            </button>
            <h2 class="flex-1 text-base font-semibold text-heading">
              {{ placeholder }}
            </h2>
          </div>
          <div ref="searchField" class="p-3">
            <BaseInput
              v-model="query"
              type="search"
              :aria-label="$t('general.search')"
              :placeholder="$t('general.search')"
              @update:model-value="search"
            />
          </div>
          <ul
            class="flex flex-col overflow-y-auto overscroll-contain border-t border-line-light"
            :class="isPhone ? 'flex-1' : 'max-h-80'"
          >
            <li v-for="choice in choices" :key="choice.id">
              <button
                type="button"
                class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-hover-strong focus:outline-hidden focus-visible:bg-hover-strong"
                @click="select(choice.id)"
              >
                <span
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-sm font-semibold text-primary-700"
                  aria-hidden="true"
                  >{{ initials(choice.name) }}</span
                ><span class="min-w-0"
                  ><span
                    class="block truncate text-sm font-medium text-heading"
                    >{{ choice.name }}</span
                  ><span
                    v-if="choice.subtitle"
                    class="block truncate text-sm text-muted"
                    >{{ choice.subtitle }}</span
                  ></span
                >
              </button>
            </li>
            <li
              v-if="loading"
              role="status"
              class="px-4 py-8 text-center text-sm text-muted"
            >
              {{ $t('general.loading') }}
            </li>
            <li
              v-else-if="!choices.length"
              role="status"
              class="px-4 py-8 text-center text-sm text-muted"
            >
              {{ emptyText }}
            </li>
          </ul>
          <button
            v-if="canCreate"
            type="button"
            class="flex min-h-12 w-full items-center justify-center gap-2 border-t border-line-light px-4 py-3 text-sm font-medium text-primary-600 hover:bg-primary-50/60"
            :class="isPhone ? 'safe-drawer' : ''"
            @click="create"
          >
            <BaseIcon name="UserPlusIcon" class="h-5 w-5" />{{ createLabel }}
          </button>
        </component>
      </Teleport>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, nextTick, watch, useId, type ComponentPublicInstance } from 'vue'
import { FocusScope } from 'reka-ui'
import { onClickOutside, onKeyStroke } from '@vueuse/core'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
export interface ContactChoice {
  id: number
  name: string
  subtitle?: string
  addresses?: Array<{ label: string; lines: string[] }>
}
const props = withDefaults(
  defineProps<{
    label: string
    placeholder: string
    selected?: ContactChoice | null
    choices: ContactChoice[]
    loading?: boolean
    contentLoading?: boolean
    required?: boolean
    disabled?: boolean
    canBrowse?: boolean
    canCreate?: boolean
    canEdit?: boolean
    canClear?: boolean
    createLabel: string
    editLabel: string
    emptyText: string
    error?: string
  }>(),
  {
    selected: null,
    loading: false,
    contentLoading: false,
    required: false,
    disabled: false,
    canBrowse: true,
    canCreate: false,
    canEdit: false,
    canClear: true,
    error: '',
  },
)
const emit = defineEmits<{
  search: [query: string]
  select: [id: number]
  create: [query: string]
  edit: []
  clear: []
}>()
const { isPhone } = useBreakpoints(),
  id = useId()
const root = ref<HTMLElement | null>(null),
  trigger = ref<HTMLElement | null>(null),
  panel = ref<HTMLElement | ComponentPublicInstance | null>(null),
  searchField = ref<HTMLElement | null>(null)
const open = ref(false),
  query = ref('')
let focusAfterChange = false
function focus() {
  root.value?.focus({ preventScroll: true })
}
function leaveFocus(event: Event) {
  event.preventDefault()
}
async function openPicker() {
  if (props.disabled || !props.canBrowse) return
  open.value = true
  emit('search', query.value)
  await nextTick()
  searchField.value?.querySelector('input')?.focus()
}
async function closePicker() {
  open.value = false
  await nextTick()
  focus()
}
function search() {
  emit('search', query.value)
}
function select(id: number) {
  focusAfterChange = true
  emit('select', id)
  void closePicker()
  query.value = ''
}
async function create() {
  focusAfterChange = true
  await closePicker()
  emit('create', query.value)
}
function edit() {
  focus()
  emit('edit')
}
function clear() {
  focusAfterChange = true
  emit('clear')
}
function initials(name: string) {
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
}
watch(
  () => props.selected?.id,
  async () => {
    if (focusAfterChange) {
      focusAfterChange = false
      await nextTick()
      focus()
    }
  },
)
onClickOutside(
  panel,
  () => {
    if (open.value && !isPhone.value) void closePicker()
  },
  { ignore: [trigger] },
)
onKeyStroke('Escape', () => {
  if (open.value) void closePicker()
})
defineExpose({ focus })
</script>
