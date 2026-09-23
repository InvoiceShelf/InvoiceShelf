<script setup lang="ts">

interface Props {
  title: string
  description: string
}

defineProps<Props>()
</script>

<template>
  <!-- On phones a card's Save spans the card, in thumb reach and easy to hit -->
  <BaseCard class="max-md:[&_button[type=submit]]:w-full">
    <div class="flex flex-col gap-4 mb-6 md:flex-row md:items-start md:justify-between">
      <div class="min-w-0">
        <h2 class="font-semibold text-start text-section text-heading">
          {{ title }}
        </h2>

        <p class="mt-1 text-sm text-start text-muted max-w-[680px]">
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
