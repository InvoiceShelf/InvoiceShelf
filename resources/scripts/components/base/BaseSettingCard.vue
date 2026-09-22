<script setup lang="ts">

interface Props {
  title: string
  description: string
}

defineProps<Props>()
</script>

<template>
  <BaseCard>
    <div class="flex flex-col gap-4 mb-6 md:flex-row md:items-start md:justify-between">
      <div class="min-w-0">
        <h2 class="font-semibold text-left text-section text-heading">
          {{ title }}
        </h2>

        <p class="mt-1 text-sm text-left text-muted max-w-[680px]">
          {{ description }}
        </p>
      </div>

      <div v-if="$slots.action" class="shrink-0">
        <slot name="action" />
      </div>
    </div>

    <slot />
  </BaseCard>
</template>
