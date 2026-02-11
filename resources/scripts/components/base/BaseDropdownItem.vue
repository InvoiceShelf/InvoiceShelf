<template>
  <MenuItem v-slot="{ active }" :disabled="disabled" v-bind="$attrs">
    <a
      href="#"
      :aria-disabled="disabled ? 'true' : 'false'"
      :class="[
        active && !disabled ? 'bg-gray-100 text-gray-900' : 'text-gray-700',
        disabled ? 'text-gray-400 cursor-not-allowed pointer-events-none' : '',
        'group flex items-center px-4 py-2 text-sm font-normal whitespace-normal',
      ]"
    >
      <slot :active="active" />
    </a>
  </MenuItem>
</template>

<script setup>
import { MenuItem } from '@headlessui/vue'

defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
})
</script>
