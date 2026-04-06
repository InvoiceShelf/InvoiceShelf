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
      class="relative z-10 p-5 md:p-6 bg-surface rounded-xl border border-line-default shadow-sm mb-4"
    >
      <slot name="filter-header" />

      <button
        class="
          absolute top-4 right-4
          flex items-center gap-1
          text-xs font-medium
          text-muted hover:text-heading
          px-2 py-1
          rounded-md
          hover:bg-surface-secondary
          transition-colors
        "
        @click="emit('clear')"
      >
        <BaseIcon name="XMarkIcon" class="w-3.5 h-3.5" />
        {{ $t('general.clear_all') }}
      </button>

      <div
        class="flex flex-col space-y-3"
        :class="
          rowOnXl
            ? 'xl:flex-row xl:space-x-4 xl:space-y-0 xl:items-end'
            : 'lg:flex-row lg:space-x-4 lg:space-y-0 lg:items-end'
        "
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
