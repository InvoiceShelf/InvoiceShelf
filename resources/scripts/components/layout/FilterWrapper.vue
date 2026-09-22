<template>
  <transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div
      v-show="show"
      class="relative z-10 p-4 pt-11 mb-5 border md:p-5 md:pt-5 md:pr-28 bg-surface rounded-xl border-line-light shadow-card"
    >
      <slot name="filter-header" />

      <button
        class="
          absolute top-3 right-3
          flex items-center gap-1
          text-xs font-medium
          text-muted hover:text-heading
          px-2 py-1
          rounded-md
          hover:bg-hover-strong
          transition-colors
        "
        @click="emit('clear')"
      >
        <BaseIcon name="XMarkIcon" class="w-3.5 h-3.5" />
        {{ $t('general.clear_all') }}
      </button>

      <div
        class="flex flex-col gap-4"
        :class="rowOnXl ? 'xl:flex-row xl:items-end' : 'lg:flex-row lg:items-end'"
      >
        <slot />
      </div>
    </div>
  </transition>
</template>

<script setup lang="ts">
interface Props {
  show?: boolean
  rowOnXl?: boolean
}

interface Emits {
  (e: 'clear'): void
}

withDefaults(defineProps<Props>(), {
  show: false,
  rowOnXl: false,
})

const emit = defineEmits<Emits>()
</script>
