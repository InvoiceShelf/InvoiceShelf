<template>
  <router-link
    v-if="!loading"
    class="
      relative
      flex
      justify-between
      p-5
      bg-white
      rounded-xl
      shadow-sm
      border border-gray-100
      hover:shadow-md
      transition-shadow
      xl:p-6
      lg:col-span-2
    "
    :class="{ 'lg:!col-span-3': large }"
    :to="route"
  >
    <div>
      <span class="text-2xl font-bold leading-tight text-gray-900 xl:text-3xl">
        <slot />
      </span>
      <span class="block mt-1 text-sm font-medium leading-tight text-gray-500 xl:text-base">
        {{ label }}
      </span>
    </div>
    <div class="flex items-center">
      <component :is="iconComponent" class="w-10 h-10 xl:w-12 xl:h-12" />
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
