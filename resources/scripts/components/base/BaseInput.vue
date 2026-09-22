<template>
  <BaseContentPlaceholders v-if="contentLoading">
    <BaseContentPlaceholdersBox
      :rounded="true"
      :class="`w-full ${contentLoadClass}`"
      style="height: 38px"
    />
  </BaseContentPlaceholders>

  <div
    v-else
    :class="[containerClass, computedContainerClass]"
    class="relative rounded-lg font-base"
  >
    <div
      v-if="loading && loadingPosition === 'left'"
      class="
        absolute
        inset-y-0
        left-0
        flex
        items-center
        pl-3
        pointer-events-none
      "
    >
      <svg
        class="animate-spin !text-subtle"
        :class="[iconLeftClass]"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle
          class="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="4"
        ></circle>
        <path
          class="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        ></path>
      </svg>
    </div>

    <div
      v-else-if="hasLeftIconSlot"
      class="absolute inset-y-0 left-0 flex items-center pl-3"
    >
      <slot name="left" :class="iconLeftClass" />
    </div>

    <span
      v-if="addon"
      class="
        inline-flex
        items-center
        px-3
        text-muted
        border border-r-0 border-line-default
        rounded-l-lg
        bg-surface-secondary
        text-sm
      "
    >
      {{ addon }}
    </span>

    <div
      v-if="inlineAddon"
      class="
        absolute
        inset-y-0
        left-0
        flex
        items-center
        pl-3
        pointer-events-none
      "
    >
      <span class="text-muted text-base md:text-sm">
        {{ inlineAddon }}
      </span>
    </div>

    <input
      v-bind="$attrs"
      :type="type"
      :value="modelValue"
      :disabled="disabled"
      :class="[
        defaultInputClass,
        inputPaddingClass,
        inputAddonClass,
        inputInvalidClass,
        inputDisabledClass,
      ]"
      @input="emitValue"
    />

    <div
      v-if="loading && loadingPosition === 'right'"
      class="
        absolute
        inset-y-0
        right-0
        flex
        items-center
        pr-3
        pointer-events-none
      "
    >
      <svg
        class="animate-spin !text-subtle"
        :class="[iconRightClass]"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle
          class="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="4"
        ></circle>
        <path
          class="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        ></path>
      </svg>
    </div>

    <div
      v-if="hasRightIconSlot"
      class="absolute inset-y-0 right-0 flex items-center pr-3"
    >
      <slot name="right" :class="iconRightClass" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useSlots } from 'vue'

interface ModelModifiers {
  uppercase?: boolean
}

interface Props {
  contentLoading?: boolean
  type?: number | string
  modelValue?: string | number
  loading?: boolean
  loadingPosition?: 'left' | 'right'
  addon?: string | null
  inlineAddon?: string
  invalid?: boolean
  disabled?: boolean
  containerClass?: string
  contentLoadClass?: string
  defaultInputClass?: string
  iconLeftClass?: string
  iconRightClass?: string
  modelModifiers?: ModelModifiers
}

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<Props>(), {
  contentLoading: false,
  type: 'text',
  modelValue: '',
  loading: false,
  loadingPosition: 'left',
  addon: null,
  inlineAddon: '',
  invalid: false,
  disabled: false,
  containerClass: '',
  contentLoadClass: '',
  defaultInputClass:
    'font-base block w-full md:text-sm border-line-default rounded-lg text-heading',
  iconLeftClass: 'h-5 w-5 text-subtle',
  iconRightClass: 'h-5 w-5 text-subtle',
  modelModifiers: () => ({}),
})

const slots = useSlots()

interface Emits {
  (e: 'update:modelValue', value: string | number): void
}

const emit = defineEmits<Emits>()

const hasLeftIconSlot = computed<boolean>(() => {
  return !!slots.left || (props.loading && props.loadingPosition === 'left')
})

const hasRightIconSlot = computed<boolean>(() => {
  return !!slots.right || (props.loading && props.loadingPosition === 'right')
})

const inputPaddingClass = computed<string>(() => {
  if (hasLeftIconSlot.value && hasRightIconSlot.value) {
    return 'px-10'
  } else if (hasLeftIconSlot.value) {
    return 'pl-10'
  } else if (hasRightIconSlot.value) {
    return 'pr-10'
  }

  return ''
})

const inputAddonClass = computed<string>(() => {
  if (props.addon) {
    return 'flex-1 min-w-0 block w-full px-3 py-2 !rounded-none !rounded-r-lg'
  } else if (props.inlineAddon) {
    return 'pl-7'
  }

  return ''
})

const inputInvalidClass = computed<string>(() => {
  if (props.invalid) {
    return 'border-danger focus:border-danger focus:ring-danger/20'
  }

  return ''
})

const inputDisabledClass = computed<string>(() => {
  if (props.disabled) {
    return `border-line-light bg-surface-secondary !text-muted cursor-not-allowed`
  }

  return ''
})

const computedContainerClass = computed<string>(() => {
  let cls = `${props.containerClass} `

  if (props.addon) {
    return `${props.containerClass} flex`
  }

  return cls
})

function emitValue(e: Event): void {
  const target = e.target as HTMLInputElement
  let val: string = target.value
  if (props.modelModifiers.uppercase) {
    val = val.toUpperCase()
  }

  emit('update:modelValue', val)
}
</script>
