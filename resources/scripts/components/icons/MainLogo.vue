<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  darkColor?: string
  lightColor?: string
}

const props = withDefaults(defineProps<Props>(), {
  darkColor: 'var(--color-primary-500)',
  lightColor: 'var(--color-primary-500)',
})

// Single render colour (mono lockup). Parents pass `light-color="white"` etc.
const color = computed(() => props.lightColor)
</script>

<template>
  <!-- Redesigned brand lockup: "receipt + check" mark + InvoiceShelf wordmark, monochrome. -->
  <svg
    viewBox="0 0 235 48"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    :style="{ color }"
    role="img"
    aria-label="InvoiceShelf"
  >
    <g stroke="currentColor" fill="none">
      <path
        d="M12,11 a4,4 0 0 1 4,-4 h16 a4,4 0 0 1 4,4 v22 l-2,4 l-2,-4 l-2,4 l-2,-4 l-2,4 l-2,-4 l-2,4 l-2,-4 l-2,4 l-2,-4 l-2,4 l-2,-4 Z"
        stroke-width="2.6"
        stroke-linejoin="round"
      />
      <line x1="17" y1="16" x2="31" y2="16" stroke-width="2.6" stroke-linecap="round" opacity="0.45" />
      <line x1="17" y1="21.5" x2="26" y2="21.5" stroke-width="2.6" stroke-linecap="round" opacity="0.45" />
      <circle cx="33" cy="33" r="9" stroke-width="2.6" />
      <path
        d="M29.2 33.2 l2.6 2.7 l4.6 -5.6"
        stroke-width="2.6"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </g>
    <text
      x="56"
      y="32"
      font-family="Poppins, ui-sans-serif, system-ui, sans-serif"
      font-weight="700"
      font-size="27"
      letter-spacing="-0.7"
      fill="currentColor"
    >InvoiceShelf</text>
  </svg>
</template>
