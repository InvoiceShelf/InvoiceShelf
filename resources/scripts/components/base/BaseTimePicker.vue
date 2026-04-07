<template>
  <BaseContentPlaceholders v-if="contentLoading">
    <BaseContentPlaceholdersBox
      :rounded="true"
      :class="`w-full ${computedContainerClass}`"
      style="height: 38px"
    />
  </BaseContentPlaceholders>

  <div v-else :class="computedContainerClass" class="relative flex flex-row">
    <svg
      v-if="clockIcon && !hasIconSlot"
      xmlns="http://www.w3.org/2000/svg"
      class="
        absolute
        top-px
        w-4
        h-4
        mx-2
        my-2.5
        text-sm
        not-italic
        font-black
        text-subtle
        cursor-pointer
      "
      viewBox="0 0 20 20"
      fill="currentColor"
      @click="onClickPicker"
    >
      <path
        fill-rule="evenodd"
        d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
        clip-rule="evenodd"
      />
    </svg>

    <slot v-if="clockIcon && hasIconSlot" name="icon" />

    <FlatPickr
      ref="dpt"
      v-model="time"
      v-bind="$attrs"
      :disabled="disabled"
      :config="config"
      :class="[defaultInputClass, inputInvalidClass, inputDisabledClass]"
    />
  </div>
</template>

<script setup lang="ts">
import FlatPickr from 'vue-flatpickr-component'
import 'flatpickr/dist/flatpickr.css'
import { computed, reactive, useSlots, ref } from 'vue'

interface FlatPickrInstance {
  fp: { open: () => void }
}

const dpt = ref<FlatPickrInstance | null>(null)

interface Props {
  modelValue?: string | Date
  contentLoading?: boolean
  placeholder?: string | null
  invalid?: boolean
  disabled?: boolean
  containerClass?: string
  clockIcon?: boolean
  defaultInputClass?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: () => new Date(),
  contentLoading: false,
  placeholder: null,
  invalid: false,
  disabled: false,
  containerClass: '',
  clockIcon: true,
  defaultInputClass:
    'font-base pl-8 py-2 outline-hidden focus:ring-primary-400 focus:outline-hidden focus:border-primary-400 block w-full sm:text-sm border-line-strong rounded-md text-heading',
})

interface Emits {
  (e: 'update:modelValue', value: string | Date): void
}

const emit = defineEmits<Emits>()

const slots = useSlots()

interface TimePickerConfig {
  enableTime: boolean
  noCalendar: boolean
  dateFormat: string
  time_24hr: boolean
}

const config = reactive<TimePickerConfig>({
  enableTime: true,
  noCalendar: true,
  dateFormat: 'H:i',
  time_24hr: true,
})

const time = computed<string | Date>({
  get: () => props.modelValue,
  set: (value: string | Date) => emit('update:modelValue', value),
})

const hasIconSlot = computed<boolean>(() => {
  return !!slots.icon
})

function onClickPicker(): void {
  dpt.value?.fp.open()
}

const computedContainerClass = computed<string>(() => {
  const containerClass = `${props.containerClass} `

  return containerClass
})

const inputInvalidClass = computed<string>(() => {
  if (props.invalid) {
    return 'border-red-400 ring-red-400 focus:ring-red-400 focus:border-red-400'
  }

  return ''
})

const inputDisabledClass = computed<string>(() => {
  if (props.disabled) {
    return 'border border-solid rounded-md outline-hidden input-field box-border-2 base-date-picker-input placeholder-subtle bg-surface-muted text-body border-line-strong'
  }

  return ''
})
</script>
