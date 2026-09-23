<template>
  <BaseContentPlaceholders v-if="contentLoading">
    <BaseContentPlaceholdersBox :rounded="true" class="w-full h-10" />
  </BaseContentPlaceholders>
  <div v-else v-bind="rootAttrs">
    <label
      v-if="label"
      :for="triggerId"
      class="block text-sm not-italic font-medium text-heading mb-0.5"
    >
      {{ label }}
    </label>

    <SelectRoot v-model="selectedValue">
      <div class="relative">
        <!-- Labelled by its own label, or by the surrounding group's -->
        <SelectTrigger
          v-bind="{ ...(label ? {} : fieldAttrs), ...buttonAria }"
          :id="triggerId"
          class="
            relative
            w-full
            py-2
            ps-3
            pe-10
            text-start
            bg-surface
            border border-control-border
            rounded-lg
            cursor-default
            text-base
            leading-6
            md:text-sm
            text-heading
            focus:outline-hidden
            focus:ring-2
            focus:ring-focus
            focus:border-primary-500
          "
        >
          <span v-if="getValue(selectedValue)" class="block truncate">
            {{ getValue(selectedValue) }}
          </span>
          <span v-else-if="placeholder" class="block text-subtle truncate">
            {{ placeholder }}
          </span>
          <span v-else class="block text-subtle truncate">
            {{ $t('general.select_an_option') }}
          </span>

          <span
            class="
              absolute
              inset-y-0
              end-0
              flex
              items-center
              pe-2
              pointer-events-none
            "
          >
            <BaseIcon
              name="ChevronUpDownIcon"
              class="text-subtle"
              aria-hidden="true"
            />
          </span>
        </SelectTrigger>

        <!-- Inside a dialog the list opens within it -->
        <SelectPortal :to="dialogLayer ?? 'body'">
          <SelectContent
            position="popper"
            :side-offset="4"
            class="
              z-50
              w-(--reka-select-trigger-width)
              overflow-hidden
              text-base
              bg-surface
              rounded-xl
              shadow-lg
              border border-line-light
              focus:outline-hidden
              md:text-sm
              data-[state=open]:animate-pop-in data-[state=closed]:animate-pop-out
            "
          >
            <SelectViewport class="p-1 max-h-60">
              <SelectItem
                v-for="option in options"
                :key="option.id"
                :value="option"
                class="
                  text-heading cursor-default select-none relative py-2 ps-3 pe-9 rounded-lg outline-hidden
                  font-normal data-[state=checked]:font-semibold data-highlighted:bg-hover-strong
                "
              >
                <SelectItemText class="block truncate">
                  {{ getValue(option) }}
                </SelectItemText>

                <SelectItemIndicator class="text-primary-600 absolute inset-y-0 end-0 flex items-center pe-3">
                  <BaseIcon name="CheckIcon" aria-hidden="true" />
                </SelectItemIndicator>
              </SelectItem>
              <slot />
            </SelectViewport>
          </SelectContent>
        </SelectPortal>
      </div>
    </SelectRoot>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, ref, useAttrs, useId, watch } from 'vue'
import type { AcceptableValue } from 'reka-ui'
import {
  SelectContent,
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  SelectPortal,
  SelectRoot,
  SelectTrigger,
  SelectViewport,
} from 'reka-ui'
import { useFormField } from '@/scripts/composables/use-form-field'
import { DIALOG_LAYER } from '@/scripts/utils/dialog-layers'

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

// Named by the surrounding group's label when this select has none of its own
const { attrs: fieldAttrs } = useFormField({ labelledBy: true })

// aria-* attributes describe the button a screen reader lands on; the rest
// (class, style, data-*) stay on the wrapper
defineOptions({ inheritAttrs: false })

const attrs = useAttrs()

const rootAttrs = computed(() => Object.fromEntries(Object.entries(attrs).filter(([key]) => !key.startsWith('aria-'))))

const buttonAria = computed(() => Object.fromEntries(Object.entries(attrs).filter(([key]) => key.startsWith('aria-'))))

const triggerId = `select-${useId()}`

const dialogLayer = inject(DIALOG_LAYER, null)

interface Emits {
  (e: 'update:modelValue', value: ModelValue): void
}

const emit = defineEmits<Emits>()

// Options are objects, compared by value, so a model fetched afresh still matches
const selectedValue = ref<AcceptableValue>(props.modelValue as AcceptableValue)

function isObject(val: unknown): val is Record<string, unknown> {
  return typeof val === 'object' && val !== null
}

function getValue(val: ModelValue | AcceptableValue): string | number | boolean | unknown {
  if (isObject(val) && !Array.isArray(val)) {
    return val[props.labelKey]
  }
  return val
}

// With valueProp the model holds one field of an option: find the option it
// belongs to, now and once the options arrive, so its label shows
watch(
  [() => props.modelValue, () => props.options],
  () => {
    if (props.valueProp && props.options.length) {
      const found = props.options.find((val) => {
        if (val[props.valueProp!]) {
          return val[props.valueProp!] === props.modelValue
        }
        return false
      })
      selectedValue.value = (found ?? props.modelValue) as AcceptableValue
    } else {
      selectedValue.value = props.modelValue as AcceptableValue
    }
  },
  { immediate: true },
)

watch(selectedValue, (val) => {
  if (props.valueProp && isObject(val) && !Array.isArray(val)) {
    emit('update:modelValue', val[props.valueProp] as ModelValue)
  } else {
    emit('update:modelValue', val as ModelValue)
  }
})
</script>
