<template>
  <BaseSheet
    :show="globalStore.isSidebarOpen"
    :label="$t('navigation.more')"
    @close="globalStore.setSidebarVisibility(false)"
  >
    <nav class="pb-1">
      <div
        v-for="(menu, index) in globalStore.menuGroups"
        :key="index"
        :class="index === 0 ? '' : 'mt-3'"
      >
        <p
          v-if="menu[0] && menu[0].group_label"
          class="px-3 pt-1 pb-1 text-xs font-medium text-muted"
        >
          {{ $t(menu[0].group_label) }}
        </p>
        <router-link
          v-for="item in menu"
          :key="item.name"
          :to="item.link"
          :aria-current="hasActiveUrl(item.link) ? 'page' : undefined"
          :class="[
            hasActiveUrl(item.link)
              ? 'bg-primary-50 text-primary-700'
              : 'text-heading active:bg-hover-strong',
          ]"
          class="flex items-center gap-3.5 px-3 h-12 text-base font-medium rounded-xl"
          @click="globalStore.setSidebarVisibility(false)"
        >
          <BaseIcon
            :name="item.icon"
            :class="hasActiveUrl(item.link) ? 'text-primary-600' : 'text-muted'"
            class="w-5.5 h-5.5 shrink-0"
          />
          <span class="truncate">{{ $t(item.title) }}</span>
        </router-link>
      </div>
    </nav>
  </BaseSheet>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { useRoute } from 'vue-router'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useActiveMenuLink } from '@/scripts/composables/use-active-menu-link'

const route = useRoute()
const globalStore = useGlobalStore()
const { hasActiveUrl } = useActiveMenuLink(route)

watch(
  () => route.fullPath,
  () => globalStore.setSidebarVisibility(false),
)
</script>
