<template>
  <div class="flex flex-wrap items-end justify-between gap-3 md:flex-nowrap md:gap-6">
    <div class="flex flex-col min-w-0 grow">
      <h1 v-if="title" class="font-semibold text-left break-words text-title text-heading">
        {{ title }}
      </h1>
      <slot />
    </div>
    <!--
      Actions share the title's row whenever they fit, and move below it only
      when they don't. On phones, buttons that carry an icon show only the
      icon (BaseButton reads pageHeaderCompact), so a list page reads
      "Invoices [filter] [+]" instead of stacking rows.
    -->
    <div
      v-if="$slots.actions"
      class="flex flex-wrap items-center justify-end gap-2 ml-auto shrink-0 md:gap-3 *:ml-0"
    >
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { provide } from 'vue'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'

interface Props {
  title?: string
}

withDefaults(defineProps<Props>(), {
  title: '',
})

const { isPhone } = useBreakpoints()

provide('pageHeaderCompact', isPhone)
</script>
