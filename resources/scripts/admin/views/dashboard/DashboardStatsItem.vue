<template>
  <router-link
    v-if="!loading"
    class="
      relative
      flex
      flex-col
      p-3
      mt-3
      rounded
      xl:p-4
      lg:col-span-2
      transition-colors
      duration-200
    "
    :class="{ 'lg:!col-span-4': large }"
    :to="route"
  >
    <div class="flex items-center mb-4">
      <div class="flex items-center mr-3">
        <component :is="iconComponent" class="w-8 h-8 xl:w-10 xl:h-10 text-gray-400" />
      </div>
      <span class="text-sm leading-tight text-gray-500 dark:text-gray-400 xl:text-base">
        {{ label }}
      </span>
    </div>
    <div>
      <span class="text-xl font-semibold leading-tight text-gray-900 dark:text-white xl:text-3xl">
        <slot />
      </span>
    </div>
  </router-link>

  <StatsCardPlaceholder v-else-if="large" />

  <StatsCardSmPlaceholder v-else />
</template>


<script setup>
import StatsCardPlaceholder from './DashboardStatsPlaceholder.vue'
import StatsCardSmPlaceholder from './DashboardStatsSmPlaceholder.vue'

defineProps({
  iconComponent: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  route: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    required: true,
  },
  large: {
    type: Boolean,
    default: false,
  },
})
</script>
