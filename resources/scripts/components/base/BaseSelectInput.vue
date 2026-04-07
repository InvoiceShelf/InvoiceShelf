<template>
  <BaseContentPlaceholders v-if="contentLoading">
    <BaseContentPlaceholdersBox :rounded="true" class="w-full h-10" />
  </BaseContentPlaceholders>
  <Listbox
    v-else
    v-model="selectedValue"
    as="div"
    v-bind="{
      ...$attrs,
    }"
  >
    <ListboxLabel
      v-if="label"
      class="block text-sm not-italic font-medium text-heading mb-0.5"
    >
      {{ label }}
    </ListboxLabel>

    <div class="relative">
      <!-- Select Input button -->
      <ListboxButton
        class="
          relative
          w-full
          py-2
          pl-3
          pr-10
          text-left
          bg-surface
          border border-line-default
          rounded-md
          shadow-xs
          cursor-default
          focus:outline-hidden
          focus:ring-1
          focus:ring-primary-500
          focus:border-primary-500
          sm:text-sm
        "
      >
        <span v-if="getValue(selectedValue)" class="block truncate">
          {{ getValue(selectedValue) }}
        </span>
        <span v-else-if="placeholder" class="block text-subtle truncate">
          {{ placeholder }}
        </span>
        <span v-else class="block text-subtle truncate">
          Please select an option
        </span>

        <span
          class="
            absolute
            inset-y-0
            right-0
            flex
            items-center
            pr-2
            pointer-events-none
          "
        >
          <BaseIcon
            name="SelectorIcon"
            class="text-subtle"
            aria-hidden="true"
          />
        </span>
      </ListboxButton>

      <transition
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <ListboxOptions
          class="
            absolute
            z-10
            w-full
            py-1
            mt-1
            overflow-auto
            text-base
            bg-surface
            rounded-md
            shadow-lg
            max-h-60
            ring-1 ring-black/5
            focus:outline-hidden
            sm:text-sm
          "
        >
          <ListboxOption
            v-for="option in options"
            v-slot="{ active, selected }"
            :key="option.id"
            :value="option"
            as="template"
          >
            <li
              :class="[
                active ? 'text-white bg-primary-600' : 'text-heading',
                'cursor-default select-none relative py-2 pl-3 pr-9',
              ]"
            >
              <span
                :class="[
                  selected ? 'font-semibold' : 'font-normal',
                  'block truncate',
                ]"
              >
                {{ getValue(option) }}
              </span>

              <span
                v-if="selected"
                :class="[
                  active ? 'text-white' : 'text-primary-600',
                  'absolute inset-y-0 right-0 flex items-center pr-4',
                ]"
              >
                <BaseIcon name="CheckIcon" aria-hidden="true" />
              </span>
            </li>
          </ListboxOption>
          <slot />
        </ListboxOptions>
      </transition>
    </div>
  </Listbox>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  Listbox,
  ListboxButton,
  ListboxLabel,
  ListboxOption,
  ListboxOptions,
} from '@headlessui/vue'

interface SelectOption {
  id: string | number
  [key: string]: unknown
}

type ModelValue = string | number | boolean | SelectOption | SelectOption[]

interface Props {
  contentLoading?: boolean
  modelValue?: ModelValue
  options: SelectOption[]
  label?: string
  placeholder?: string
  labelKey?: string
  valueProp?: string | null
  multiple?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  contentLoading: false,
  modelValue: '',
  label: '',
  placeholder: '',
  labelKey: 'label',
  valueProp: null,
  multiple: false,
})

interface Emits {
  (e: 'update:modelValue', value: ModelValue): void
}

const emit = defineEmits<Emits>()

const selectedValue = ref<ModelValue>(props.modelValue)

function isObject(val: unknown): val is Record<string, unknown> {
  return typeof val === 'object' && val !== null
}

function getValue(val: ModelValue): string | number | boolean | unknown {
  if (isObject(val) && !Array.isArray(val)) {
    return val[props.labelKey]
  }
  return val
}

watch(
  () => props.modelValue,
  () => {
    if (props.valueProp && props.options.length) {
      const found = props.options.find((val) => {
        if (val[props.valueProp!]) {
          return val[props.valueProp!] === props.modelValue
        }
        return false
      })
      selectedValue.value = found ?? props.modelValue
    } else {
      selectedValue.value = props.modelValue
    }
  }
)

watch(selectedValue, (val) => {
  if (props.valueProp && isObject(val) && !Array.isArray(val)) {
    emit('update:modelValue', val[props.valueProp] as ModelValue)
  } else {
    emit('update:modelValue', val)
  }
})
</script>
