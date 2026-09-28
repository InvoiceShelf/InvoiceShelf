<template>
  <BaseDropdown
    v-if="selected && options.length > 1"
    data-view-switcher
    position="bottom-start"
    width-class="w-64 max-w-[calc(100vw-2rem)]"
    wrapper-class="inline-block max-w-full text-start [&>span]:max-w-full [&_button]:max-w-full"
    :label="
      $t('view_switcher.context_label', {
        context: label,
        view: selected.label,
      })
    "
  >
    <template #activator>
      <span
        class="inline-flex h-10 max-w-full items-center gap-2 rounded-lg border border-line-default bg-surface px-3 text-sm font-medium text-body shadow-xs transition-colors hover:bg-hover hover:text-heading md:h-9"
      >
        <BaseIcon
          :name="selected.icon"
          class="h-4.5 w-4.5 shrink-0 text-muted"
          aria-hidden="true"
        />
        <span class="min-w-0 truncate">{{ selected.label }}</span>
        <BaseIcon
          name="ChevronDownIcon"
          class="h-4 w-4 shrink-0 text-muted"
          aria-hidden="true"
        />
      </span>
    </template>
    <DropdownMenuLabel class="px-3 pb-1.5 pt-2 text-xs font-medium text-muted">
      {{ $t('view_switcher.view') }}
    </DropdownMenuLabel>
    <DropdownMenuRadioGroup
      :model-value="modelValue"
      @update:model-value="select"
    >
      <DropdownMenuRadioItem
        v-for="option in options"
        :key="option.value"
        :value="option.value"
        class="flex min-h-12 cursor-pointer select-none items-center gap-3 rounded-lg px-3 text-base text-body outline-hidden data-highlighted:bg-hover-strong data-highlighted:text-heading data-[state=checked]:bg-primary-50 data-[state=checked]:font-medium data-[state=checked]:text-primary-700 md:min-h-10 md:text-sm"
      >
        <BaseIcon
          :name="option.icon"
          :class="
            option.value === modelValue ? 'text-primary-600' : 'text-muted'
          "
          class="h-5 w-5 shrink-0"
          aria-hidden="true"
        />
        <span class="min-w-0 flex-1">{{ option.label }}</span>
        <span class="flex h-5 w-5 shrink-0 items-center justify-center">
          <DropdownMenuItemIndicator
            ><BaseIcon
              name="CheckIcon"
              class="h-4 w-4 text-primary-600"
              aria-hidden="true"
          /></DropdownMenuItemIndicator>
        </span>
      </DropdownMenuRadioItem>
    </DropdownMenuRadioGroup>
  </BaseDropdown>
  <span
    v-else-if="selected && selected.value !== primaryValue"
    data-view-switcher
    class="inline-flex h-10 items-center gap-2 text-sm font-medium text-muted md:h-9"
  >
    <BaseIcon :name="selected.icon" class="h-4.5 w-4.5" aria-hidden="true" />
    {{ selected.label }}
  </span>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import {
  DropdownMenuItemIndicator,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
} from 'reka-ui'
import BaseDropdown from './BaseDropdown.vue'

export interface ViewSwitcherOption {
  value: string
  label: string
  icon: string
}
const props = defineProps<{
  modelValue: string
  primaryValue: string
  label: string
  options: ViewSwitcherOption[]
}>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const selected = computed(() =>
  props.options.find((option) => option.value === props.modelValue),
)
function select(value: unknown) {
  if (
    typeof value === 'string' &&
    value !== props.modelValue &&
    props.options.some((option) => option.value === value)
  )
    emit('update:modelValue', value)
}
</script>
