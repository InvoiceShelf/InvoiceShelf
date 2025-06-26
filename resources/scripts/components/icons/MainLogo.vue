<template>
  <img :src="adminLogo" alt="Main Logo" class="w-10 h-10" />
</template>

<script setup>

import {ref, computed} from 'vue'

const adminLogo = computed(() => {
     const imgUrl = new URL('$images/logo.png', import.meta.url)
     return imgUrl
   })

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
