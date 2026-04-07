<template>
  <li class="pr-2 text-sm">
    <router-link
      class="
        m-0
        mr-2
        text-sm
        font-medium
        leading-5
        text-heading
        outline-hidden
        focus:ring-2 focus:ring-offset-2 focus:ring-primary-400
      "
      :to="to"
    >
      {{ title }}
    </router-link>

    <span v-if="!active" class="px-1">/</span>
  </li>
</template>

<script setup lang="ts">
interface Props {
  title?: string
  to?: string
  active?: boolean
}

withDefaults(defineProps<Props>(), {
  title: '',
  to: '#',
  active: false,
})
</script>
