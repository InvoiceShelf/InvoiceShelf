<template>
  <router-link
    :to="to"
    :aria-current="active ? 'page' : undefined"
    :class="active ? 'bg-primary-50' : 'hover:bg-hover'"
    class="flex items-start justify-between gap-3 px-4 py-3 transition-colors border-b border-line-light"
  >
    <div class="min-w-0">
      <p class="text-sm font-medium truncate text-heading">{{ title }}</p>
      <p v-if="subtitle" class="mt-0.5 text-xs truncate text-muted">{{ subtitle }}</p>
      <div v-if="$slots.badges" class="flex flex-wrap gap-1 mt-2">
        <slot name="badges" />
      </div>
    </div>

    <div class="text-end shrink-0">
      <div v-if="$slots.amount" class="text-sm font-semibold text-heading">
        <slot name="amount" />
      </div>
      <p v-if="meta" class="mt-0.5 text-xs text-muted">{{ meta }}</p>
    </div>
  </router-link>
</template>

<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'

/** One row in a RecordListPane: who or what, a quiet line, badges, an amount and a date */
interface Props {
  to: RouteLocationRaw
  title: string
  subtitle?: string
  meta?: string
  active?: boolean
}

withDefaults(defineProps<Props>(), {
  subtitle: '',
  meta: '',
  active: false,
})
</script>
