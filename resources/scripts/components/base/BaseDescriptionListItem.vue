<script setup lang="ts">
interface Props {
  label: string
  value?: string | number
  contentLoading?: boolean
}

withDefaults(defineProps<Props>(), {
  value: '',
  contentLoading: false,
})
</script>

<template>
  <div class="min-w-0">
    <BaseContentPlaceholders v-if="contentLoading">
      <BaseContentPlaceholdersBox class="w-20 h-5 mb-1" />
      <BaseContentPlaceholdersBox class="w-40 h-5" />
    </BaseContentPlaceholders>

    <template v-else>
      <p class="mb-1 text-sm text-muted">
        {{ label }}
      </p>

      <!-- Isolated: an email or phone number keeps its order in right-to-left text -->
      <div class="text-sm font-medium leading-5 break-words text-heading">
        <bdi>{{ value }}</bdi>

        <slot />
      </div>
    </template>
  </div>
</template>
