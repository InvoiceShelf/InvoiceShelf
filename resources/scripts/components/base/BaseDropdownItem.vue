<template>
  <!--
    A menu item. Given `to`, the item is the link itself, so a keyboard or
    screen reader meets one element (a link with the menuitem role) rather than
    a menu item inside a link. Without `to` it is a plain element; the click
    still reaches a wrapping <router-link>, so older call sites keep working.
  -->
  <MenuItem v-if="to" v-slot="{ active, disabled }" as="template" v-bind="$attrs">
    <router-link :to="to" :class="itemClass(active, disabled)">
      <slot :active="active" />
    </router-link>
  </MenuItem>

  <MenuItem v-else v-slot="{ active, disabled }" v-bind="$attrs">
    <div :class="itemClass(active, disabled)">
      <slot :active="active" />
    </div>
  </MenuItem>
</template>

<script setup lang="ts">
import { inject, ref } from 'vue'
import type { Ref } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { MenuItem } from '@headlessui/vue'

interface Props {
  to?: RouteLocationRaw
}

withDefaults(defineProps<Props>(), {
  to: undefined,
})

defineOptions({ inheritAttrs: false })

const isSheet = inject<Ref<boolean>>('dropdownIsSheet', ref(false))

function itemClass(active: boolean, disabled: boolean): string[] {
  return [
    active ? 'bg-hover-strong text-heading' : 'text-body',
    disabled ? 'opacity-50 pointer-events-none' : '',
    isSheet.value ? 'px-3 min-h-12 text-base rounded-xl' : 'px-3 py-2 text-sm rounded-lg',
    'group flex items-center font-normal whitespace-normal cursor-pointer',
  ]
}
</script>
