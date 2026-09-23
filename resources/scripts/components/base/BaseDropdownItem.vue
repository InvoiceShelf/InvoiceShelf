<template>
  <!--
    A menu item. Given `to`, the item is the link itself, so a keyboard or
    screen reader meets one element (a link with the menuitem role) rather than
    a menu item inside a link. Without `to` it is a plain element; the click
    still reaches a wrapping <router-link>, so older call sites keep working.
  -->
  <DropdownMenuItem
    v-if="to"
    as-child
    v-bind="$attrs"
    @focus="active = true"
    @blur="active = false"
  >
    <router-link :to="to" :class="itemClass">
      <slot :active="active" />
    </router-link>
  </DropdownMenuItem>

  <DropdownMenuItem
    v-else
    v-bind="$attrs"
    :class="itemClass"
    @focus="active = true"
    @blur="active = false"
  >
    <slot :active="active" />
  </DropdownMenuItem>
</template>

<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import type { Ref } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { DropdownMenuItem } from 'reka-ui'

interface Props {
  to?: RouteLocationRaw
}

withDefaults(defineProps<Props>(), {
  to: undefined,
})

defineOptions({ inheritAttrs: false })

const isSheet = inject<Ref<boolean>>('dropdownIsSheet', ref(false))

// The highlighted item holds focus, so focus is what the `active` slot prop reports
const active = ref<boolean>(false)

const itemClass = computed<string[]>(() => [
  'text-body data-highlighted:bg-hover-strong data-highlighted:text-heading',
  'data-disabled:opacity-50 data-disabled:pointer-events-none',
  isSheet.value ? 'px-3 min-h-12 text-base rounded-xl' : 'px-3 py-2 text-sm rounded-lg',
  'group flex items-center font-normal whitespace-normal cursor-pointer outline-hidden',
])
</script>
