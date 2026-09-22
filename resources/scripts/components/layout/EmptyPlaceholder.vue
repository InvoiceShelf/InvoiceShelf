<template>
  <div class="flex flex-col items-center justify-center px-6 py-14 text-center md:py-20">
    <!-- The entity's icon on a tinted glass tile, with a soft halo -->
    <div v-if="icon" class="relative mb-6" aria-hidden="true">
      <div class="absolute rounded-full -inset-6 bg-primary-500/10 blur-2xl" />
      <div
        class="
          relative flex items-center justify-center w-16 h-16 rounded-2xl
          glass border ring-1 ring-inset ring-primary-600/10
        "
      >
        <div class="absolute inset-0 rounded-2xl bg-linear-to-br from-primary-500/15 to-transparent" />
        <BaseIcon :name="icon" class="relative w-7 h-7 text-primary-600" />
      </div>
    </div>
    <div v-else class="flex flex-col items-center justify-center mb-5 [&>svg]:h-24 [&>svg]:w-auto">
      <slot></slot>
    </div>
    <p class="font-semibold text-section text-heading">{{ title }}</p>
    <p v-if="description" class="max-w-sm mt-1.5 text-sm text-muted">
      {{ description }}
    </p>
    <div v-if="$slots.actions" class="mt-6">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  title?: string
  description?: string
  /** A heroicon name for the entity; replaces the default slot's illustration */
  icon?: string
}

withDefaults(defineProps<Props>(), {
  title: '',
  description: '',
  icon: '',
})
</script>
