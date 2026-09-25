<script setup lang="ts">
import { computed } from 'vue'
import { poweredBy } from '@/scripts/utils/branding'

/**
 * "Powered by" and the host's name, linked, or nothing on a white-label
 * install. With `logo`, the InvoiceShelf mark stands in for the name, but
 * only for InvoiceShelf itself: a host with its own name gets it in text.
 */
const props = withDefaults(defineProps<{ logo?: string | null }>(), { logo: null })

const brand = poweredBy()
const showLogo = computed<boolean>(() => props.logo !== null && brand?.name === 'InvoiceShelf')
</script>

<template>
  <span v-if="brand" class="inline-flex items-center gap-1">
    Powered by
    <a
      :href="brand.url"
      target="_blank"
      rel="noopener noreferrer"
      class="font-medium text-primary-500 transition-colors hover:text-primary-600"
    >
      <img v-if="showLogo" :src="logo ?? undefined" :alt="brand.name" class="h-4" />
      <template v-else>{{ brand.name }}</template>
    </a>
  </span>
</template>
