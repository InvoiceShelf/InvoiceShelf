<template>
  <!--
    The InvoiceShelf mark in its indigo tile, drawn into a 48 x 48 area of the
    parent <svg>: the receipt in white, and the check on a solid white badge.
    The same art as the phone app icon and the favicon.
  -->
  <defs>
    <linearGradient :id="gradientId" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" style="stop-color: var(--color-hero-to)" />
      <stop offset="1" style="stop-color: var(--color-hero-from)" />
    </linearGradient>
  </defs>
  <rect width="48" height="48" rx="11" :fill="`url(#${gradientId})`" />
  <g transform="translate(23 24) scale(0.8) translate(-24 -24)" fill="none">
    <path
      d="M12,11 a4,4 0 0 1 4,-4 h16 a4,4 0 0 1 4,4 v22 l-2,4 l-2,-4 l-2,4 l-2,-4 l-2,4 l-2,-4 l-2,4 l-2,-4 l-2,4 l-2,-4 l-2,4 l-2,-4 Z"
      stroke="#fff"
      stroke-width="3"
      stroke-linejoin="round"
    />
    <path d="M17 16h14M17 21.5h9" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity="0.7" />
    <!-- A ring in the tile's own colour keeps the badge clear of the receipt -->
    <circle cx="33" cy="33" r="11.4" style="fill: color-mix(in srgb, var(--color-hero-from) 63%, var(--color-hero-to))" />
    <circle cx="33" cy="33" r="9" fill="#fff" />
    <path
      d="M29.2 33.2 l2.6 2.7 l4.6 -5.6"
      stroke-width="3"
      stroke-linecap="round"
      stroke-linejoin="round"
      style="stroke: var(--color-hero-to)"
    />
  </g>
</template>

<script setup lang="ts">
import { useId } from 'vue'

// Each logo on the page gets its own gradient, so hiding one never blanks another
const gradientId = `logo-tile-${useId()}`
</script>
