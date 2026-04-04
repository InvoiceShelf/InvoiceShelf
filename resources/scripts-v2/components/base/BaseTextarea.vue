<template>
  <BaseContentPlaceholders v-if="contentLoading">
    <BaseContentPlaceholdersBox
      :rounded="true"
      class="w-full"
      :style="`height: ${loadingPlaceholderSize}px`"
    />
  </BaseContentPlaceholders>

  <textarea
    v-else
    v-bind="$attrs"
    ref="textarea"
    :value="modelValue"
    :class="[defaultInputClass, inputBorderClass]"
    :disabled="disabled"
    @input="onInput"
  />
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

interface Props {
  contentLoading?: boolean
  row?: number | null
  invalid?: boolean
  disabled?: boolean
  modelValue?: string | number
  defaultInputClass?: string
  autosize?: boolean
  borderless?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  contentLoading: false,
  row: null,
  invalid: false,
  disabled: false,
  modelValue: '',
  defaultInputClass:
    'box-border w-full px-3 py-2 text-sm not-italic font-normal leading-snug text-left text-heading placeholder-subtle bg-surface border border-line-default border-solid rounded outline-hidden',
  autosize: false,
  borderless: false,
})

const textarea = ref<HTMLTextAreaElement | null>(null)

const inputBorderClass = computed<string>(() => {
  if (props.invalid && !props.borderless) {
    return 'border-red-400 ring-red-400 focus:ring-red-400 focus:border-red-400'
  } else if (!props.borderless) {
    return 'focus:ring-primary-400 focus:border-primary-400'
  }

  return 'border-none outline-hidden focus:ring-primary-400 focus:border focus:border-primary-400'
})

const loadingPlaceholderSize = computed<string>(() => {
  switch (props.row) {
    case 2:
      return '56'
    case 4:
      return '94'
    default:
      return '56'
  }
})

interface Emits {
  (e: 'update:modelValue', value: string): void
}

const emit = defineEmits<Emits>()

function onInput(e: Event): void {
  const target = e.target as HTMLTextAreaElement
  emit('update:modelValue', target.value)

  if (props.autosize) {
    target.style.height = 'auto'
    target.style.height = `${target.scrollHeight}px`
  }
}

onMounted(() => {
  if (textarea.value && props.autosize) {
    textarea.value.style.height = textarea.value.scrollHeight + 'px'
    textarea.value.style.overflowY = 'hidden'
    textarea.value.style.resize = 'none'
  }
})
</script>
