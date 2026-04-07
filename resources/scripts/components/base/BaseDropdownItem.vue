<template>
  <MenuItem v-slot="{ active }" v-bind="$attrs">
    <a
      href="#"
      :class="[
        active ? 'bg-hover-strong text-heading' : 'text-body',
        'group flex items-center px-4 py-2 text-sm font-normal whitespace-normal',
      ]"
    >
      <slot :active="active" />
    </a>
  </MenuItem>
</template>

<script setup lang="ts">
import { MenuItem } from '@headlessui/vue'
</script>
