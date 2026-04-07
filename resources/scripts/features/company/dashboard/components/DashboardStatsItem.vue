<script setup lang="ts">
import type { Component } from 'vue'

interface Props {
  iconComponent: Component
  loading?: boolean
  route: string
  label: string
  large?: boolean
}

withDefaults(defineProps<Props>(), {
  loading: false,
  large: false,
})
</script>

<template>
  <router-link
    v-if="!loading"
    class="
      relative
      flex
      justify-between
      p-5
      bg-surface
      rounded-xl
      shadow
      border border-line-light
      hover:shadow-md
      transition-shadow
      xl:p-6
      lg:col-span-2
    "
    :class="{ 'lg:!col-span-3': large }"
    :to="route"
  >
    <div>
      <span class="text-2xl font-bold leading-tight text-heading xl:text-3xl">
        <slot />
      </span>
      <span class="block mt-1 text-sm font-medium leading-tight text-muted xl:text-base">
        {{ label }}
      </span>
    </div>
    <div class="flex items-center">
      <component :is="iconComponent" class="w-10 h-10 xl:w-12 xl:h-12" />
    </div>
  </router-link>

  <!-- Large placeholder -->
  <BaseContentPlaceholders
    v-else-if="large"
    :rounded="true"
    class="relative flex justify-between w-full p-3 bg-surface rounded shadow lg:col-span-3 xl:p-4"
  >
    <div>
      <BaseContentPlaceholdersText
        class="h-5 -mb-1 w-14 xl:mb-6 xl:h-7"
        :lines="1"
      />
      <BaseContentPlaceholdersText class="h-3 w-28 xl:h-4" :lines="1" />
    </div>
    <div class="flex items-center">
      <BaseContentPlaceholdersBox
        :circle="true"
        class="w-10 h-10 xl:w-12 xl:h-12"
      />
    </div>
  </BaseContentPlaceholders>

  <!-- Small placeholder -->
  <BaseContentPlaceholders
    v-else
    :rounded="true"
    class="
      relative
      flex
      justify-between
      w-full
      p-3
      bg-surface
      rounded
      shadow
      lg:col-span-2
      xl:p-4
    "
  >
    <div>
      <BaseContentPlaceholdersText
        class="w-12 h-5 -mb-1 xl:mb-6 xl:h-7"
        :lines="1"
      />
      <BaseContentPlaceholdersText class="w-20 h-3 xl:h-4" :lines="1" />
    </div>
    <div class="flex items-center">
      <BaseContentPlaceholdersBox
        :circle="true"
        class="w-10 h-10 xl:w-12 xl:h-12"
      />
    </div>
  </BaseContentPlaceholders>
</template>
