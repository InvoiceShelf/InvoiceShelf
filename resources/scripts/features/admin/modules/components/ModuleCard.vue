<template>
  <div
    class="group relative rounded-xl border border-line-default bg-surface cursor-pointer overflow-hidden transition-shadow hover:shadow-lg"
    @click="$router.push(`/admin/administration/modules/${data.slug}`)"
  >
    <!-- Cover -->
    <div class="relative h-36 overflow-hidden">
      <img
        v-if="data.cover"
        class="w-full h-full object-cover object-center transition-transform group-hover:scale-105"
        :src="data.cover"
        alt=""
      />
      <div
        v-else
        class="w-full h-full bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center"
      >
        <BaseIcon name="PuzzlePieceIcon" class="h-10 w-10 text-primary-400" />
      </div>

      <!-- Badges -->
      <div class="absolute top-2.5 right-2.5 flex gap-1.5">
        <span class="bg-white/85 backdrop-blur-sm text-xs px-2 py-0.5 font-medium rounded-md text-heading">
          {{ data.access_tier === 'premium' ? 'Premium' : 'Public' }}
        </span>
        <span
          v-if="data.installed"
          class="text-xs px-2 py-0.5 font-medium rounded-md"
          :class="data.update_available
            ? 'bg-amber-100/90 text-amber-700'
            : 'bg-green-100/90 text-green-700'"
        >
          {{ data.update_available ? $t('modules.update_available') : $t('modules.installed') }}
        </span>
      </div>
    </div>

    <!-- Info -->
    <div class="p-4">
      <h3 class="text-base font-semibold text-heading truncate">
        {{ data.name }}
      </h3>

      <div class="flex items-center gap-1.5 mt-1 text-xs text-muted">
        <img
          v-if="data.author_avatar"
          class="h-4 w-4 rounded-full"
          :src="data.author_avatar"
          alt=""
        />
        <span>{{ data.author_name }}</span>
        <span v-if="data.latest_module_version" class="ml-auto font-medium text-body">
          v{{ data.latest_module_version }}
        </span>
      </div>

      <p class="mt-2.5 text-xs text-muted leading-relaxed line-clamp-2">
        {{ data.short_description }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Module } from '../../../../types/domain/module'

interface Props {
  data: Module
}

defineProps<Props>()
</script>
