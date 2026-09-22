<template>
  <MenuItem v-slot="{ active }" v-bind="$attrs">
    <!--
      The anchor keeps its href for focus and cursor semantics, but the click
      must never navigate: under hash history an empty fragment is a real
      route change to "/", which the client build maps to the login screen.
    -->
    <a
      href="#"
      :class="[
        active ? 'bg-hover-strong text-heading' : 'text-body',
        'group flex items-center px-4 py-2 text-sm font-normal whitespace-normal',
      ]"
      @click.prevent
    >
      <slot :active="active" />
    </a>
  </MenuItem>
</template>

<script setup lang="ts">
import { MenuItem } from '@headlessui/vue'
</script>
