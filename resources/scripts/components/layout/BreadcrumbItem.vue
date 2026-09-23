<template>
  <li
    v-if="!isHidden"
    class="crumb flex items-center [&+li.crumb]:before:content-['/'] [&+li.crumb]:before:px-1.5 [&+li.crumb]:before:text-subtle"
  >
    <router-link
      class="font-medium rounded-sm text-muted hover:text-heading focus-visible:outline-2"
      :to="to"
    >
      {{ title }}
    </router-link>
  </li>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  title?: string
  to?: string
  active?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  to: '#',
  active: false,
})

// The current page is already the title, and "Home" is one tap away in every shell
const isHidden = computed<boolean>(() => {
  return props.active || props.to === 'dashboard' || props.to.endsWith('/dashboard')
})
</script>
