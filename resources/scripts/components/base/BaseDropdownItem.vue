<template>
  <MenuItem v-slot="{ active, disabled }" v-bind="$attrs">
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
        disabled ? 'opacity-50 pointer-events-none' : '',
        isSheet
          ? 'px-3 min-h-12 text-base rounded-xl'
          : 'px-3 py-2 text-sm rounded-lg',
        'group flex items-center font-normal whitespace-normal cursor-pointer',
      ]"
    >
      <slot :active="active" />
    </div>
  </MenuItem>
</template>

<script setup lang="ts">
import { inject, ref } from 'vue'
import type { Ref } from 'vue'
import { MenuItem } from '@headlessui/vue'

const isSheet = inject<Ref<boolean>>('dropdownIsSheet', ref(false))
</script>
