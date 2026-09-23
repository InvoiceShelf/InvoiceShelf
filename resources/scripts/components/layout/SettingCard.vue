<template>
  <BaseCard>
    <div class="flex flex-wrap justify-between lg:flex-nowrap mb-5">
      <div>
        <h2 class="font-medium text-lg text-start">
          {{ title }}
        </h2>

        <p
          class="
            mt-2
            text-sm
            leading-snug
            text-start text-muted
            max-w-[680px]
          "
        >
          {{ description }}
        </p>
      </div>

      <div class="mt-4 lg:mt-0 lg:ms-2">
        <slot name="action" />
      </div>
    </div>

    <slot />
  </BaseCard>
</template>

<script setup lang="ts">
interface Props {
  title: string
  description: string
}

defineProps<Props>()
</script>
