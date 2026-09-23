<template>
  <MenuItem v-slot="{ active }" v-bind="$attrs">
    <!--
      A plain element rather than an anchor. Most items sit inside a
      <router-link>, and the click has to reach that link untouched: an
      href="#" here would navigate to "/" under hash history, and preventing
      that default would also stop the router link, which skips navigation
      for a default-prevented click.
    -->
    <div
      :class="[
        active ? 'bg-hover-strong text-heading' : 'text-body',
        'group flex items-center px-4 py-2 text-sm font-normal whitespace-normal cursor-pointer',
      ]"
    >
      <slot :active="active" />
    </div>
  </MenuItem>
</template>

<script setup lang="ts">
import { MenuItem } from '@headlessui/vue'
</script>
