<template>
  <!-- A floating glass bar above the home indicator; content scrolls under it -->
  <nav
    class="fixed inset-x-3 z-30 border rounded-2xl glass-strong bottom-[calc(env(safe-area-inset-bottom)+0.5rem)]"
    :aria-label="$t('navigation.menu')"
  >
    <ul class="grid h-15 px-1" :style="{ gridTemplateColumns: `repeat(${tabs.length + 1}, minmax(0, 1fr))` }">
      <li v-for="tab in tabs" :key="tab.link">
        <router-link
          :to="tab.link"
          :aria-current="hasActiveUrl(tab.link) ? 'page' : undefined"
          :class="[
            hasActiveUrl(tab.link) ? 'text-primary-600' : 'text-muted',
          ]"
          class="relative flex flex-col items-center justify-center h-full gap-0.5 px-1"
        >
          <span
            :class="hasActiveUrl(tab.link) ? 'bg-primary-600/12' : ''"
            class="flex items-center justify-center w-12 h-7 rounded-full transition-colors"
          >
            <BaseIcon :name="tab.icon" class="w-5.5 h-5.5" />
          </span>
          <span class="max-w-full text-[11px] font-medium leading-4 truncate">
            {{ $t(tab.title) }}
          </span>
        </router-link>
      </li>
      <li>
        <button
          type="button"
          :class="[isMoreActive ? 'text-primary-600' : 'text-muted']"
          class="flex flex-col items-center justify-center w-full h-full gap-0.5 px-1"
          @click="globalStore.setSidebarVisibility(true)"
        >
          <span
            :class="isMoreActive ? 'bg-primary-600/12' : ''"
            class="flex items-center justify-center w-12 h-7 rounded-full transition-colors"
          >
            <BaseIcon name="Squares2X2Icon" class="w-5.5 h-5.5" />
          </span>
          <span class="text-[11px] font-medium leading-4">{{ $t('navigation.more') }}</span>
        </button>
      </li>
    </ul>
  </nav>

  <MoreSheet />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useActiveMenuLink } from '@/scripts/composables/use-active-menu-link'
import type { MenuItem } from '@/scripts/api/services/bootstrap.service'
import MoreSheet from './MoreSheet.vue'

// The destinations people open a phone for, when the menu offers them
const PREFERRED_LINKS = [
  '/admin/dashboard',
  '/admin/invoices',
  '/admin/customers',
  '/admin/expenses',
]
const TAB_COUNT = 4

const route = useRoute()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()
const { activeMenuLink, hasActiveUrl } = useActiveMenuLink(route)

const tabs = computed<MenuItem[]>(() => {
  const items = globalStore.menuGroups.flat()

  if (companyStore.isAdminMode) {
    return items.slice(0, TAB_COUNT)
  }

  const preferred = PREFERRED_LINKS.map((link) =>
    items.find((item) => item.link === link),
  ).filter((item): item is MenuItem => !!item)

  const rest = items.filter((item) => !preferred.includes(item))

  return [...preferred, ...rest].slice(0, TAB_COUNT)
})

const isMoreActive = computed<boolean>(() => {
  return (
    globalStore.isSidebarOpen ||
    (!!activeMenuLink.value && !tabs.value.some((tab) => tab.link === activeMenuLink.value))
  )
})
</script>
