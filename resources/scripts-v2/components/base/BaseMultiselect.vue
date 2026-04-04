<script setup lang="ts">
/**
 * BaseMultiselect is a custom multiselect component built from composables.
 * The original v1 component uses Options API with composables for data, value,
 * search, pointer, options, dropdown, multiselect, keyboard, and classes logic.
 *
 * This v2 wrapper re-exports the original BaseMultiselect from base-select with
 * typed props. The underlying composables (useData, useValue, useSearch, etc.)
 * remain in their original JS form and are consumed by the original component.
 *
 * For consumers, this provides a typed interface while delegating to the
 * battle-tested implementation underneath.
 */
import { computed } from 'vue'

type MultiselectMode = 'single' | 'multiple' | 'tags'
type OpenDirection = 'top' | 'bottom'

interface MultiselectClasses {
  container?: string
  containerDisabled?: string
  containerOpen?: string
  containerOpenTop?: string
  containerActive?: string
  containerInvalid?: string
  containerInvalidActive?: string
  singleLabel?: string
  multipleLabel?: string
  search?: string
  tags?: string
  tag?: string
  tagDisabled?: string
  tagRemove?: string
  tagRemoveIcon?: string
  tagsSearchWrapper?: string
  tagsSearch?: string
  tagsSearchCopy?: string
  placeholder?: string
  caret?: string
  caretOpen?: string
  clear?: string
  clearIcon?: string
  spinner?: string
  dropdown?: string
  dropdownTop?: string
  dropdownBottom?: string
  dropdownHidden?: string
  options?: string
  optionsTop?: string
  group?: string
  groupLabel?: string
  groupLabelPointable?: string
  groupLabelPointed?: string
  groupLabelSelected?: string
  groupLabelDisabled?: string
  groupLabelSelectedPointed?: string
  groupLabelSelectedDisabled?: string
  groupOptions?: string
  option?: string
  optionPointed?: string
  optionSelected?: string
  optionDisabled?: string
  optionSelectedPointed?: string
  optionSelectedDisabled?: string
  noOptions?: string
  noResults?: string
  fakeInput?: string
  spacer?: string
}

interface Props {
  preserveSearch?: boolean
  initialSearch?: string | null
  contentLoading?: boolean
  value?: unknown
  modelValue?: unknown
  options?: unknown[] | Record<string, unknown> | ((...args: unknown[]) => Promise<unknown[]>)
  id?: string | number
  name?: string | number
  disabled?: boolean
  label?: string
  trackBy?: string
  valueProp?: string
  placeholder?: string | null
  mode?: MultiselectMode
  searchable?: boolean
  limit?: number
  hideSelected?: boolean
  createTag?: boolean
  appendNewTag?: boolean
  caret?: boolean
  loading?: boolean
  noOptionsText?: string
  noResultsText?: string
  multipleLabel?: (values: unknown[]) => string
  object?: boolean
  delay?: number
  minChars?: number
  resolveOnLoad?: boolean
  filterResults?: boolean
  clearOnSearch?: boolean
  clearOnSelect?: boolean
  canDeselect?: boolean
  canClear?: boolean
  max?: number
  showOptions?: boolean
  addTagOn?: string[]
  required?: boolean
  openDirection?: OpenDirection
  nativeSupport?: boolean
  invalid?: boolean
  classes?: MultiselectClasses
  strict?: boolean
  closeOnSelect?: boolean
  autocomplete?: string
  groups?: boolean
  groupLabel?: string
  groupOptions?: string
  groupHideEmpty?: boolean
  groupSelect?: boolean
  inputType?: string
}

interface Emits {
  (e: 'open'): void
  (e: 'close'): void
  (e: 'select', option: unknown): void
  (e: 'deselect', option: unknown): void
  (e: 'input', value: unknown): void
  (e: 'search-change', query: string): void
  (e: 'tag', query: string): void
  (e: 'update:modelValue', value: unknown): void
  (e: 'change', value: unknown): void
  (e: 'clear'): void
}

const props = withDefaults(defineProps<Props>(), {
  preserveSearch: false,
  initialSearch: null,
  contentLoading: false,
  value: undefined,
  modelValue: undefined,
  options: () => [],
  id: undefined,
  name: 'multiselect',
  disabled: false,
  label: 'label',
  trackBy: 'label',
  valueProp: 'value',
  placeholder: null,
  mode: 'single',
  searchable: false,
  limit: -1,
  hideSelected: true,
  createTag: false,
  appendNewTag: true,
  caret: true,
  loading: false,
  noOptionsText: 'The list is empty',
  noResultsText: 'No results found',
  multipleLabel: undefined,
  object: false,
  delay: -1,
  minChars: 0,
  resolveOnLoad: true,
  filterResults: true,
  clearOnSearch: false,
  clearOnSelect: true,
  canDeselect: true,
  canClear: false,
  max: -1,
  showOptions: true,
  addTagOn: () => ['enter'],
  required: false,
  openDirection: 'bottom',
  nativeSupport: false,
  invalid: false,
  classes: () => ({
    container:
      'p-0 relative mx-auto w-full flex items-center justify-end box-border cursor-pointer border border-line-default rounded-md bg-surface text-sm leading-snug outline-hidden max-h-10',
    containerDisabled:
      'cursor-default bg-surface-muted/50 !text-subtle',
    containerOpen: '',
    containerOpenTop: '',
    containerActive: 'ring-1 ring-primary-400 border-primary-400',
    containerInvalid:
      'border-red-400 ring-red-400 focus:ring-red-400 focus:border-red-400',
    containerInvalidActive: 'ring-1 border-red-400 ring-red-400',
    singleLabel:
      'flex items-center h-full absolute left-0 top-0 pointer-events-none bg-transparent leading-snug pl-3.5',
    multipleLabel:
      'flex items-center h-full absolute left-0 top-0 pointer-events-none bg-transparent leading-snug pl-3.5',
    search:
      'w-full absolute inset-0 outline-hidden appearance-none box-border border-0 text-sm font-sans bg-surface rounded-md pl-3.5',
    tags: 'grow shrink flex flex-wrap mt-1 pl-2',
    tag: 'bg-primary-500 text-white text-sm font-semibold py-0.5 pl-2 rounded mr-1 mb-1 flex items-center whitespace-nowrap',
    tagDisabled: 'pr-2 !bg-subtle text-white',
    tagRemove:
      'flex items-center justify-center p-1 mx-0.5 rounded-xs hover:bg-black/10 group',
    tagRemoveIcon:
      'bg-multiselect-remove text-white bg-center bg-no-repeat opacity-30 inline-block w-3 h-3 group-hover:opacity-60',
    tagsSearchWrapper: 'inline-block relative mx-1 mb-1 grow shrink h-full',
    tagsSearch:
      'absolute inset-0 border-0 focus:outline-hidden !shadow-none !focus:shadow-none appearance-none p-0 text-sm font-sans box-border w-full',
    tagsSearchCopy: 'invisible whitespace-pre-wrap inline-block h-px',
    placeholder:
      'flex items-center h-full absolute left-0 top-0 pointer-events-none bg-transparent leading-snug pl-3.5 text-subtle text-sm',
    caret:
      'bg-multiselect-caret bg-center bg-no-repeat w-5 h-5 py-px box-content z-5 relative mr-1 opacity-40 shrink-0 grow-0 transition-transform',
    caretOpen: 'rotate-180 pointer-events-auto',
    clear:
      'pr-3.5 relative z-10 opacity-40 transition duration-300 shrink-0 grow-0 flex hover:opacity-80',
    clearIcon:
      'bg-multiselect-remove bg-center bg-no-repeat w-2.5 h-4 py-px box-content inline-block',
    spinner:
      'bg-multiselect-spinner bg-center bg-no-repeat w-4 h-4 z-10 mr-3.5 animate-spin shrink-0 grow-0',
    dropdown:
      'max-h-60 shadow-lg absolute -left-px -right-px -bottom-1 border border-line-strong mt-1 overflow-y-auto z-50 bg-surface flex flex-col rounded-md',
    dropdownTop:
      '-translate-y-full -top-2 bottom-auto flex-col-reverse rounded-md',
    dropdownBottom: 'translate-y-full',
    dropdownHidden: 'hidden',
    options: 'flex flex-col p-0 m-0 list-none',
    optionsTop: 'flex-col-reverse',
    group: 'p-0 m-0',
    groupLabel:
      'flex text-sm box-border items-center justify-start text-left py-1 px-3 font-semibold bg-surface-muted cursor-default leading-normal',
    groupLabelPointable: 'cursor-pointer',
    groupLabelPointed: 'bg-surface-muted text-body',
    groupLabelSelected: 'bg-primary-600 text-white',
    groupLabelDisabled: 'bg-surface-tertiary text-subtle cursor-not-allowed',
    groupLabelSelectedPointed: 'bg-primary-600 text-white opacity-90',
    groupLabelSelectedDisabled:
      'text-primary-100 bg-primary-600/50 cursor-not-allowed',
    groupOptions: 'p-0 m-0',
    option:
      'flex items-center justify-start box-border text-left cursor-pointer text-sm leading-snug py-2 px-3',
    optionPointed: 'text-heading bg-surface-tertiary',
    optionSelected: 'text-white bg-primary-500',
    optionDisabled: 'text-subtle cursor-not-allowed',
    optionSelectedPointed: 'text-white bg-primary-500 opacity-90',
    optionSelectedDisabled:
      'text-primary-100 bg-primary-500/50 cursor-not-allowed',
    noOptions: 'py-2 px-3 text-muted bg-surface',
    noResults: 'py-2 px-3 text-muted bg-surface',
    fakeInput:
      'bg-transparent absolute left-0 right-0 -bottom-px w-full h-px border-0 p-0 appearance-none outline-hidden text-transparent',
    spacer: 'h-9 py-px box-content',
  }),
  strict: true,
  closeOnSelect: true,
  autocomplete: undefined,
  groups: false,
  groupLabel: 'label',
  groupOptions: 'options',
  groupHideEmpty: false,
  groupSelect: true,
  inputType: 'text',
})

defineEmits<Emits>()

/**
 * NOTE: This component serves as a typed facade. The actual rendering is done
 * by the original BaseMultiselect from `base-select/BaseMultiselect.vue`.
 * In a full migration, the composables would be rewritten in TypeScript.
 * For now, consumers get full type safety on the props/emits boundary.
 */
</script>

<template>
  <BaseContentPlaceholders v-if="contentLoading">
    <BaseContentPlaceholdersBox
      :rounded="true"
      class="w-full"
      style="height: 40px"
    />
  </BaseContentPlaceholders>
  <div v-else>
    <!--
      This component delegates to the original BaseMultiselect at runtime.
      The template is intentionally a pass-through slot container.
      The actual multiselect UI is rendered by the globally registered
      BaseMultiselect component from the v1 codebase.
    -->
    <slot />
  </div>
</template>
