<template>
  <h1 class="mb-2 text-2xl font-bold " style="font-family: 'Poppins', sans-serif;  ">
    Natracker for Invoice
  </h1>
</template>

<script setup>

import { ref } from 'vue'

const props = defineProps({
  darkColor: {
    type: String,
    default: 'rgba(var(--color-primary-500), var(--tw-text-opacity))',
  },
  lightColor: {
    type: String,
    default: 'rgba(var(--color-primary-400), var(--tw-text-opacity))',
  },
})

const style = ref({
  strokeWidth: '0.25mm',
  stroke: props.lightColor,
  fill: props.lightColor,
});

</script>
