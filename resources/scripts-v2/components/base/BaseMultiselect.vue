<template>
  <BaseContentPlaceholders v-if="contentLoading">
    <BaseContentPlaceholdersBox
      :rounded="true"
      class="w-full"
      style="height: 40px"
    />
  </BaseContentPlaceholders>

  <div
    v-else
    :id="id"
    ref="rootRef"
    v-bind="rootAttrs"
    :tabindex="rootTabIndex"
    :class="containerClass"
    @click="handleContainerClick"
    @focusin="handleFocusIn"
    @focusout="handleFocusOut"
    @keydown="handleKeydown"
  >
    <template v-if="isTagsMode">
      <div :class="classes.tags">
        <slot
          v-for="option in selectedOptions"
          :key="option.key"
          name="tag"
          :option="option.raw"
          :handle-tag-remove="handleTagRemove"
          :handleTagRemove="handleTagRemove"
          :disabled="disabled"
        >
          <span
            :class="[classes.tag, disabled ? classes.tagDisabled : '']"
          >
            {{ getOptionLabel(option.raw) }}
            <span
              v-if="!disabled"
              :class="classes.tagRemove"
              @mousedown.prevent.stop="handleTagRemove(option.raw)"
            >
              <span :class="classes.tagRemoveIcon" />
            </span>
          </span>
        </slot>

        <div :class="classes.tagsSearchWrapper">
          <span :class="classes.tagsSearchCopy">{{ search }}</span>
          <input
            v-if="searchable && !disabled"
            ref="inputRef"
            :type="inputType"
            :value="search"
            :class="classes.tagsSearch"
            :autocomplete="autocomplete"
            style="box-shadow: none !important"
            @focus="openDropdown"
            @input="handleSearchInput"
            @paste.stop
          />
        </div>
      </div>
    </template>

    <input
      v-else-if="searchable && !disabled"
      ref="inputRef"
      :type="inputType"
      :value="search"
      :class="classes.search"
      :autocomplete="autocomplete"
      @focus="openDropdown"
      @input="handleSearchInput"
      @paste.stop
    />

    <template v-if="showSingleLabel && selectedOption">
      <slot name="singlelabel" :value="selectedOption.raw">
        <div :class="classes.singleLabel">
          {{ getOptionLabel(selectedOption.raw) }}
        </div>
      </slot>
    </template>

    <template v-if="showMultipleLabel">
      <slot
        name="multiplelabel"
        :values="selectedOptions.map((option) => option.raw)"
      >
        <div :class="classes.multipleLabel">
          {{ multipleLabelText }}
        </div>
      </slot>
    </template>

    <template v-if="placeholder && !hasSelected && !search">
      <slot name="placeholder">
        <div :class="classes.placeholder">
          {{ placeholder }}
        </div>
      </slot>
    </template>

    <slot v-if="busy" name="spinner">
      <span :class="classes.spinner" />
    </slot>

    <slot
      v-if="hasSelected && !disabled && canClear && !busy"
      name="clear"
      :clear="clearSelection"
    >
      <span
        :class="classes.clear"
        @mousedown.prevent.stop="clearSelection"
      >
        <span :class="classes.clearIcon" />
      </span>
    </slot>

    <slot v-if="caret" name="caret">
      <span
        :class="caretClass"
        @mousedown.prevent.stop="toggleDropdown"
      />
    </slot>

    <div
      v-show="dropdownVisible"
      :class="dropdownClass"
      tabindex="-1"
    >
      <div class="w-full overflow-y-auto">
        <slot
          name="beforelist"
          :options="slotOptions"
        />

        <ul :class="classes.options">
          <template v-if="groups">
            <li
              v-for="group in visibleGroups"
              :key="group.key"
              :class="classes.group"
            >
              <div :class="groupLabelClass(group)">
                <slot name="grouplabel" :group="group.raw">
                  <span>{{ group.label }}</span>
                </slot>
              </div>

              <ul :class="classes.groupOptions">
                <li
                  v-for="option in group.options"
                  :key="option.key"
                  :class="optionClass(option)"
                  :data-pointed="isHighlighted(option.raw)"
                  @mouseenter="setHighlighted(option.raw)"
                  @mousedown.prevent.stop="handleOptionClick(option.raw)"
                >
                  <slot
                    name="option"
                    :option="option.raw"
                    :search="search"
                  >
                    <span>{{ getOptionLabel(option.raw) }}</span>
                  </slot>
                </li>
              </ul>
            </li>
          </template>

          <template v-else>
            <li
              v-for="option in visibleOptions"
              :key="option.key"
              :class="optionClass(option)"
              :data-pointed="isHighlighted(option.raw)"
              @mouseenter="setHighlighted(option.raw)"
              @mousedown.prevent.stop="handleOptionClick(option.raw)"
            >
              <slot
                name="option"
                :option="option.raw"
                :search="search"
              >
                <span>{{ getOptionLabel(option.raw) }}</span>
              </slot>
            </li>
          </template>
        </ul>

        <slot v-if="noOptions" name="nooptions">
          <div :class="classes.noOptions">
            {{ noOptionsText }}
          </div>
        </slot>

        <slot v-if="noResults" name="noresults">
          <div :class="classes.noResults">
            {{ noResultsText }}
          </div>
        </slot>

        <slot
          name="afterlist"
          :options="slotOptions"
        />
      </div>

      <slot name="action" />
    </div>

    <input
      v-if="required"
      :class="classes.fakeInput"
      tabindex="-1"
      :value="textValue"
      required
    />

    <template v-if="nativeSupport">
      <input
        v-if="mode === 'single'"
        type="hidden"
        :name="name"
        :value="plainValue ?? ''"
      />
      <template v-else>
        <input
          v-for="(value, index) in nativeValues"
          :key="index"
          type="hidden"
          :name="`${name}[]`"
          :value="value"
        />
      </template>
    </template>

    <div :class="classes.spacer" />
  </div>
</template>

<script setup lang="ts">
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  useAttrs,
  watch,
} from 'vue'

type MultiselectMode = 'single' | 'multiple' | 'tags'
type OpenDirection = 'top' | 'bottom'
type OptionRecord = Record<string, unknown>

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
  options?: unknown[] | Record<string, unknown> | ((query: string) => Promise<unknown[]> | unknown[])
  id?: string | number
  name?: string | number
  disabled?: boolean
  label?: string
  labelValue?: string
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
  multipleLabel?: ((values: unknown[]) => string) | null
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
  allowEmpty?: boolean
  showLabels?: boolean
}

interface NormalizedOption {
  key: string
  raw: OptionRecord
}

interface NormalizedGroup {
  key: string
  label: string
  raw: OptionRecord
  options: NormalizedOption[]
}

const defaultClasses: Required<MultiselectClasses> = {
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
  labelValue: '',
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
  multipleLabel: null,
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
  classes: () => ({}),
  strict: true,
  closeOnSelect: true,
  autocomplete: undefined,
  groups: false,
  groupLabel: 'label',
  groupOptions: 'options',
  groupHideEmpty: false,
  groupSelect: true,
  inputType: 'text',
  allowEmpty: undefined,
  showLabels: true,
})

const emit = defineEmits<{
  (e: 'open'): void
  (e: 'close'): void
  (e: 'select', value: unknown, option?: unknown): void
  (e: 'deselect', value: unknown, option?: unknown): void
  (e: 'remove', value: unknown, option?: unknown): void
  (e: 'input', value: unknown): void
  (e: 'search-change', query: string): void
  (e: 'searchChange', query: string): void
  (e: 'tag', query: string): void
  (e: 'update:modelValue', value: unknown): void
  (e: 'change', value: unknown): void
  (e: 'clear'): void
}>()

defineOptions({
  inheritAttrs: false,
})

const attrs = useAttrs()
const rootRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)
const isOpen = ref(false)
const isFocused = ref(false)
const search = ref(props.initialSearch ?? '')
const resolvedOptions = ref<unknown[]>([])
const appendedOptions = ref<OptionRecord[]>([])
const isResolving = ref(false)
const highlightedKey = ref<string | null>(null)
let searchTimeout: ReturnType<typeof window.setTimeout> | null = null
let requestId = 0

const classes = computed<Required<MultiselectClasses>>(() => ({
  ...defaultClasses,
  ...(props.classes ?? {}),
}))

const rootAttrs = computed<Record<string, unknown>>(() => {
  const { tabindex, ...rest } = attrs as Record<string, unknown>

  return rest
})

const rootTabIndex = computed<number>(() => {
  const rawTabIndex = (attrs as Record<string, unknown>).tabindex

  if (props.disabled) {
    return -1
  }

  if (
    typeof rawTabIndex === 'number' ||
    (typeof rawTabIndex === 'string' && rawTabIndex.length > 0)
  ) {
    return Number(rawTabIndex)
  }

  return 0
})

const isMultiMode = computed<boolean>(() => {
  return props.mode === 'multiple' || props.mode === 'tags'
})

const isTagsMode = computed<boolean>(() => props.mode === 'tags')

const effectiveCanDeselect = computed<boolean>(() => {
  return props.allowEmpty ?? props.canDeselect
})

const modelSource = computed(() => {
  return props.modelValue !== undefined ? props.modelValue : props.value
})

const busy = computed<boolean>(() => {
  return props.loading || isResolving.value
})

const containerClass = computed(() => {
  return [
    classes.value.container,
    props.disabled ? classes.value.containerDisabled : '',
    isOpen.value ? classes.value.containerOpen : '',
    isOpen.value && props.openDirection === 'top'
      ? classes.value.containerOpenTop
      : '',
    isFocused.value && props.invalid
      ? classes.value.containerInvalidActive
      : '',
    isFocused.value && !props.invalid ? classes.value.containerActive : '',
    props.invalid ? classes.value.containerInvalid : '',
  ]
})

const caretClass = computed(() => {
  return [
    classes.value.caret,
    isOpen.value ? classes.value.caretOpen : '',
  ]
})

const dropdownVisible = computed<boolean>(() => {
  return isOpen.value && props.showOptions
})

const dropdownClass = computed(() => {
  return [
    classes.value.dropdown,
    props.openDirection === 'top'
      ? classes.value.dropdownTop
      : classes.value.dropdownBottom,
    dropdownVisible.value ? '' : classes.value.dropdownHidden,
  ]
})

const optionSource = computed<unknown>(() => {
  return typeof props.options === 'function' ? resolvedOptions.value : props.options
})

const normalizedOptions = computed<NormalizedOption[]>(() => {
  if (props.groups) {
    return []
  }

  return normalizeOptionList(optionSource.value).concat(
    appendedOptions.value.map((option, index) =>
      normalizeOption(option, `appended-${index}`)
    )
  )
})

const normalizedGroups = computed<NormalizedGroup[]>(() => {
  if (!props.groups) {
    return []
  }

  return normalizeGroups(optionSource.value)
})

const allSelectableOptions = computed<NormalizedOption[]>(() => {
  if (props.groups) {
    return normalizedGroups.value.flatMap((group) => group.options)
  }

  return normalizedOptions.value
})

const selectedOptions = computed<NormalizedOption[]>(() => {
  const value = modelSource.value

  if (isMultiMode.value) {
    const values = Array.isArray(value) ? value : []

    return values
      .map((entry, index) => resolveSelectedOption(entry, `selected-${index}`))
      .filter((option): option is NormalizedOption => option !== null)
  }

  if (value === null || value === undefined) {
    return []
  }

  if (typeof value === 'string' && value.length === 0) {
    return []
  }

  const option = resolveSelectedOption(value, 'selected-single')

  return option ? [option] : []
})

const selectedOption = computed<NormalizedOption | null>(() => {
  return selectedOptions.value[0] ?? null
})

const selectedRawOptions = computed<OptionRecord[]>(() => {
  return selectedOptions.value.map((option) => option.raw)
})

const hasSelected = computed<boolean>(() => {
  return selectedOptions.value.length > 0
})

const visibleOptions = computed<NormalizedOption[]>(() => {
  let options = normalizedOptions.value.filter((option) => matchesSearch(option.raw))

  if (props.hideSelected && isMultiMode.value) {
    options = options.filter((option) => !isSelected(option.raw))
  }

  if (props.limit > -1) {
    options = options.slice(0, props.limit)
  }

  return options
})

const visibleGroups = computed<NormalizedGroup[]>(() => {
  return normalizedGroups.value
    .map((group) => {
      let options = group.options.filter((option) => matchesSearch(option.raw))

      if (props.hideSelected && isMultiMode.value) {
        options = options.filter((option) => !isSelected(option.raw))
      }

      if (props.limit > -1) {
        options = options.slice(0, props.limit)
      }

      return {
        ...group,
        options,
      }
    })
    .filter((group) => {
      if (!props.groupHideEmpty) {
        return search.value ? group.options.length > 0 : true
      }

      return group.options.length > 0
    })
})

const selectableOptions = computed<NormalizedOption[]>(() => {
  if (props.groups) {
    return visibleGroups.value.flatMap((group) => group.options)
  }

  return visibleOptions.value
})

const slotOptions = computed<unknown[]>(() => {
  if (props.groups) {
    return visibleGroups.value.map((group) => group.raw)
  }

  return visibleOptions.value.map((option) => option.raw)
})

const multipleLabelText = computed<string>(() => {
  if (props.multipleLabel) {
    return props.multipleLabel(selectedOptions.value.map((option) => option.raw))
  }

  return selectedOptions.value.length > 1
    ? `${selectedOptions.value.length} options selected`
    : '1 option selected'
})

const showSingleLabel = computed<boolean>(() => {
  return props.mode === 'single' && hasSelected.value && !search.value
})

const showMultipleLabel = computed<boolean>(() => {
  return props.mode === 'multiple' && hasSelected.value && !search.value
})

const noOptions = computed<boolean>(() => {
  return !busy.value && allSelectableOptions.value.length === 0 && !showCreateTagOption.value
})

const noResults = computed<boolean>(() => {
  return (
    !busy.value &&
    allSelectableOptions.value.length > 0 &&
    selectableOptions.value.length === 0 &&
    search.value.length > 0
  )
})

const showCreateTagOption = computed<boolean>(() => {
  return Boolean(createTagOption.value)
})

const createTagOption = computed<OptionRecord | null>(() => {
  if (!props.createTag || !search.value.trim()) {
    return null
  }

  const value = search.value.trim()

  if (allSelectableOptions.value.some((option) => matchesValue(option.raw, value))) {
    return null
  }

  return {
    [props.valueProp]: value,
    [props.label]: value,
    [props.trackBy]: value,
  }
})

const plainValue = computed<unknown>(() => {
  if (props.mode === 'single') {
    return selectedOption.value
      ? getOptionValue(selectedOption.value.raw)
      : null
  }

  return selectedOptions.value.map((option) => getOptionValue(option.raw))
})

const nativeValues = computed<unknown[]>(() => {
  return Array.isArray(plainValue.value) ? plainValue.value : []
})

const textValue = computed<string>(() => {
  if (props.mode === 'single') {
    const value = plainValue.value
    return value == null ? '' : String(value)
  }

  return nativeValues.value.map((value) => String(value)).join(',')
})

watch(
  search,
  (query) => {
    emit('search-change', query)
    emit('searchChange', query)

    if (typeof props.options === 'function' && props.delay > -1) {
      if (query.length < props.minChars) {
        if (props.clearOnSearch) {
          resolvedOptions.value = []
        }
        return
      }

      if (props.clearOnSearch) {
        resolvedOptions.value = []
      }

      if (searchTimeout) {
        window.clearTimeout(searchTimeout)
      }

      searchTimeout = window.setTimeout(() => {
        if (query === search.value) {
          void resolveOptions(query)
        }
      }, props.delay)
    }
  },
  { flush: 'sync' }
)

watch(
  selectableOptions,
  (options) => {
    if (!options.length) {
      highlightedKey.value = null
      return
    }

    if (!options.some((option) => option.key === highlightedKey.value)) {
      highlightedKey.value = options.find((option) => !isOptionDisabled(option.raw))?.key ?? null
    }
  },
  { immediate: true }
)

watch(
  () => props.initialSearch,
  (value) => {
    search.value = value ?? ''
  }
)

onMounted(() => {
  document.addEventListener('mousedown', handleDocumentMousedown)

  if (typeof props.options === 'function' && props.resolveOnLoad) {
    void resolveOptions(search.value)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleDocumentMousedown)

  if (searchTimeout) {
    window.clearTimeout(searchTimeout)
  }
})

function isPlainObject(value: unknown): value is OptionRecord {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function normalizeOptionList(source: unknown): NormalizedOption[] {
  if (Array.isArray(source)) {
    return source.map((option, index) => normalizeOption(option, index))
  }

  if (isPlainObject(source)) {
    return Object.entries(source).map(([key, value], index) =>
      normalizeOption(
        {
          [props.valueProp]: key,
          [props.trackBy]: value,
          [props.label]: value,
        },
        index
      )
    )
  }

  return []
}

function normalizeGroups(source: unknown): NormalizedGroup[] {
  if (!Array.isArray(source)) {
    return []
  }

  return source.map((group, index) => {
    const rawGroup = isPlainObject(group) ? group : {}
    const options = normalizeOptionList(rawGroup[props.groupOptions]).map(
      (option) => {
        if (Boolean(rawGroup.disabled)) {
          return {
            ...option,
            raw: {
              ...option.raw,
              disabled: true,
            },
          }
        }

        return option
      }
    )

    return {
      key: `group-${index}-${String(rawGroup[props.groupLabel] ?? '')}`,
      label: String(rawGroup[props.groupLabel] ?? ''),
      raw: rawGroup,
      options,
    }
  })
}

function normalizeOption(option: unknown, fallbackKey: string | number): NormalizedOption {
  const raw = normalizeOptionRecord(option)

  return {
    key: `${String(getOptionValue(raw) ?? '')}-${String(fallbackKey)}`,
    raw,
  }
}

function normalizeOptionRecord(option: unknown): OptionRecord {
  if (isPlainObject(option)) {
    return option
  }

  return {
    [props.valueProp]: option,
    [props.trackBy]: option,
    [props.label]: option,
  }
}

function resolveSelectedOption(
  value: unknown,
  fallbackKey: string | number
): NormalizedOption | null {
  if (value === null || value === undefined) {
    return null
  }

  const matched = allSelectableOptions.value.find((option) =>
    matchesExternal(option.raw, value)
  )

  if (matched) {
    return matched
  }

  return normalizeOption(value, fallbackKey)
}

function getOptionValue(option: OptionRecord): unknown {
  return option[props.valueProp]
}

function getOptionLabel(option: OptionRecord): string {
  const labelKey = props.labelValue || props.label
  const value = option[labelKey] ?? option[props.label] ?? getOptionValue(option)

  return value == null ? '' : String(value)
}

function getTrackValue(option: OptionRecord): string {
  const value = option[props.trackBy] ?? option[props.label] ?? getOptionValue(option)

  return value == null ? '' : String(value)
}

function normalizeSearch(value: unknown): string {
  return String(value ?? '').trim().toLowerCase()
}

function matchesSearch(option: OptionRecord): boolean {
  if (!search.value || !props.filterResults) {
    return true
  }

  const query = normalizeSearch(search.value)

  return (
    normalizeSearch(getTrackValue(option)).includes(query) ||
    normalizeSearch(getOptionLabel(option)).includes(query)
  )
}

function matchesValue(option: OptionRecord, value: unknown): boolean {
  return String(getOptionValue(option)) === String(value)
}

function matchesExternal(option: OptionRecord, value: unknown): boolean {
  if (isPlainObject(value)) {
    if (props.object) {
      return String(getOptionValue(option)) === String(getOptionValue(value))
    }

    const candidate = value[props.valueProp]

    if (candidate !== undefined) {
      return String(getOptionValue(option)) === String(candidate)
    }

    return normalizeSearch(getTrackValue(option)) === normalizeSearch(getTrackValue(value))
  }

  return String(getOptionValue(option)) === String(value)
}

function isOptionDisabled(option: OptionRecord): boolean {
  return Boolean(option.disabled)
}

function isSelected(option: OptionRecord): boolean {
  return selectedRawOptions.value.some((selected) => matchesExternal(option, selected))
}

function isHighlighted(option: OptionRecord): boolean {
  const key = normalizeOption(option, 'highlight').key

  return highlightedKey.value === key
}

function setHighlighted(option: OptionRecord): void {
  highlightedKey.value = normalizeOption(option, 'highlight').key
}

function optionClass(option: NormalizedOption): string[] {
  const selected = isSelected(option.raw)
  const disabled = isOptionDisabled(option.raw)
  const highlighted = isHighlighted(option.raw)

  return [
    classes.value.option,
    highlighted ? classes.value.optionPointed : '',
    selected ? classes.value.optionSelected : '',
    disabled ? classes.value.optionDisabled : '',
    selected && highlighted ? classes.value.optionSelectedPointed : '',
    selected && disabled ? classes.value.optionSelectedDisabled : '',
  ]
}

function groupLabelClass(group: NormalizedGroup): string[] {
  return [
    classes.value.groupLabel,
    Boolean(group.raw.disabled) ? classes.value.groupLabelDisabled : '',
  ]
}

function publicValue(option: OptionRecord): unknown {
  return props.object ? option : getOptionValue(option)
}

function emitModel(value: OptionRecord | OptionRecord[] | null): void {
  const externalValue = makeExternal(value)

  emit('change', externalValue)
  emit('input', externalValue)
  emit('update:modelValue', externalValue)
}

function makeExternal(value: OptionRecord | OptionRecord[] | null): unknown {
  if (props.object) {
    return value
  }

  if (value === null) {
    return null
  }

  if (Array.isArray(value)) {
    return value.map((option) => getOptionValue(option))
  }

  return getOptionValue(value)
}

function clearSearch(): void {
  if (!props.preserveSearch) {
    search.value = ''
  }
}

function openDropdown(): void {
  if (props.disabled || isOpen.value) {
    return
  }

  isOpen.value = true
  emit('open')

  if (typeof props.options === 'function' && props.delay === -1) {
    void resolveOptions(search.value)
  }

  void nextTick(() => {
    inputRef.value?.focus()
  })
}

function closeDropdown(): void {
  if (!isOpen.value) {
    return
  }

  isOpen.value = false
  emit('close')

  if (!props.preserveSearch && !isTagsMode.value) {
    search.value = ''
  }
}

function toggleDropdown(): void {
  if (dropdownVisible.value) {
    closeDropdown()
    return
  }

  openDropdown()
}

function selectOption(option: OptionRecord): void {
  if (props.mode === 'single') {
    emitModel(option)
    emit('select', publicValue(option), option)

    if (props.clearOnSelect) {
      clearSearch()
    }

    if (props.closeOnSelect) {
      closeDropdown()
    }

    return
  }

  const next = selectedRawOptions.value.concat(option)

  emitModel(next)
  emit('select', publicValue(option), option)

  if (props.clearOnSelect) {
    clearSearch()
  }

  if (props.closeOnSelect) {
    closeDropdown()
  }
}

function deselectOption(option: OptionRecord): void {
  if (props.mode === 'single') {
    emitModel(null)
    emit('deselect', publicValue(option), option)
    emit('remove', publicValue(option), option)
    return
  }

  const next = selectedRawOptions.value.filter(
    (selected) => !matchesExternal(option, selected)
  )

  emitModel(next)
  emit('deselect', publicValue(option), option)
  emit('remove', publicValue(option), option)
}

function clearSelection(): void {
  emit('clear')
  emitModel(props.mode === 'single' ? null : [])
}

function handleOptionClick(option: OptionRecord): void {
  if (isOptionDisabled(option)) {
    return
  }

  if (props.mode === 'single') {
    if (isSelected(option)) {
      if (effectiveCanDeselect.value) {
        deselectOption(option)
      }

      if (props.closeOnSelect) {
        closeDropdown()
      }

      return
    }

    selectOption(option)
    return
  }

  if (isSelected(option)) {
    deselectOption(option)
    return
  }

  if (props.max > -1 && selectedOptions.value.length >= props.max) {
    return
  }

  selectOption(option)
}

function handleTagRemove(option: OptionRecord): void {
  deselectOption(option)
}

function handleSearchInput(event: Event): void {
  const target = event.target as HTMLInputElement

  search.value = target.value

  if (props.clearOnSearch && hasSelected.value && props.mode === 'single') {
    emitModel(null)
  }

  if (!dropdownVisible.value) {
    openDropdown()
  }
}

function handleContainerClick(): void {
  if (props.disabled) {
    return
  }

  if (props.searchable) {
    openDropdown()
    return
  }

  toggleDropdown()
}

function handleFocusIn(): void {
  isFocused.value = true
}

function handleFocusOut(event: FocusEvent): void {
  const relatedTarget = event.relatedTarget as Node | null

  if (!rootRef.value?.contains(relatedTarget)) {
    isFocused.value = false
  }
}

function handleDocumentMousedown(event: MouseEvent): void {
  if (!rootRef.value?.contains(event.target as Node)) {
    isFocused.value = false
    closeDropdown()
  }
}

function moveHighlight(direction: 1 | -1): void {
  const options = selectableOptions.value.filter(
    (option) => !isOptionDisabled(option.raw)
  )

  if (!options.length) {
    highlightedKey.value = null
    return
  }

  const currentIndex = options.findIndex(
    (option) => option.key === highlightedKey.value
  )

  if (currentIndex === -1) {
    highlightedKey.value = options[0].key
    return
  }

  const nextIndex =
    (currentIndex + direction + options.length) % options.length

  highlightedKey.value = options[nextIndex].key
}

function selectHighlighted(): void {
  const option = selectableOptions.value.find(
    (candidate) => candidate.key === highlightedKey.value
  )

  if (option) {
    handleOptionClick(option.raw)
    return
  }

  if (createTagOption.value) {
    emit('tag', String(getOptionValue(createTagOption.value)))

    if (props.appendNewTag) {
      appendedOptions.value = appendedOptions.value.concat(createTagOption.value)
    }

    handleOptionClick(createTagOption.value)
  }
}

function handleKeydown(event: KeyboardEvent): void {
  if (props.disabled) {
    return
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    openDropdown()
    moveHighlight(1)
    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    openDropdown()
    moveHighlight(-1)
    return
  }

  if (event.key === 'Escape') {
    closeDropdown()
    return
  }

  if (event.key === 'Tab') {
    closeDropdown()
    return
  }

  if (
    event.key === 'Backspace' &&
    !search.value &&
    effectiveCanDeselect.value
  ) {
    if (props.mode === 'single' && selectedOption.value) {
      event.preventDefault()
      deselectOption(selectedOption.value.raw)
      return
    }

    if (isTagsMode.value && selectedOptions.value.length > 0) {
      event.preventDefault()
      deselectOption(selectedOptions.value[selectedOptions.value.length - 1].raw)
    }
  }

  if (props.addTagOn.includes(event.key.toLowerCase()) || event.key === 'Enter') {
    if (!dropdownVisible.value && !createTagOption.value) {
      return
    }

    event.preventDefault()
    selectHighlighted()
  }
}

async function resolveOptions(query: string): Promise<void> {
  if (typeof props.options !== 'function') {
    return
  }

  const nextRequestId = ++requestId

  isResolving.value = true

  try {
    const response = await props.options(query)

    if (nextRequestId !== requestId) {
      return
    }

    resolvedOptions.value = Array.isArray(response) ? response : []
  } finally {
    if (nextRequestId === requestId) {
      isResolving.value = false
    }
  }
}
</script>
