<template>
  <component :is="heroIcons[name]" v-if="isLoaded" class="h-5 w-5" />
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import type { Component } from 'vue'
import * as heroIcons from '@heroicons/vue/24/outline'

const isLoaded = ref<boolean>(false)

interface Props {
  name: string
}

defineProps<Props>()

onMounted(() => {
  isLoaded.value = true
})
</script>
