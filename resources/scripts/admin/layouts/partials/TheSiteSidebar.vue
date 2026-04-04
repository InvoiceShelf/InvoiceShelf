<template>
  <!-- MOBILE MENU -->
  <TransitionRoot as="template" :show="globalStore.isSidebarOpen">
    <Dialog
      as="div"
      class="fixed inset-0 z-40 flex md:hidden"
      @close="globalStore.setSidebarVisibility(false)"
    >
      <TransitionChild
        as="template"
        enter="transition-opacity ease-linear duration-300"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="transition-opacity ease-linear duration-300"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <DialogOverlay class="fixed inset-0 bg-gray-600/75" />
      </TransitionChild>

      <TransitionChild
        as="template"
        enter="transition ease-in-out duration-300"
        enter-from="-translate-x-full"
        enter-to="translate-x-0"
        leave="transition ease-in-out duration-300"
        leave-from="translate-x-0"
        leave-to="-translate-x-full"
      >
        <div class="relative flex flex-col flex-1 w-full max-w-xs bg-surface">
          <TransitionChild
            as="template"
            enter="ease-in-out duration-300"
            enter-from="opacity-0"
            enter-to="opacity-100"
            leave="ease-in-out duration-300"
            leave-from="opacity-100"
            leave-to="opacity-0"
          >
            <div class="absolute top-0 right-0 pt-2 -mr-12">
              <button
                class="
                  flex
                  items-center
                  justify-center
                  w-10
                  h-10
                  ml-1
                  rounded-full
                  focus:outline-hidden
                  focus:ring-2
                  focus:ring-inset
                  focus:ring-white
                "
                @click="globalStore.setSidebarVisibility(false)"
              >
                <span class="sr-only">Close sidebar</span>
                <BaseIcon
                  name="XMarkIcon"
                  class="w-6 h-6 text-white"
                  aria-hidden="true"
                />
              </button>
            </div>
          </TransitionChild>
          <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
            <div class="flex items-center shrink-0 px-4 mb-10">
              <MainLogo
                class="block h-auto max-w-full w-36 text-primary-400"
                alt="InvoiceShelf Logo"
              />
            </div>

            <nav
              v-for="(menu, index) in globalStore.menuGroups"
              :key="index"
              class="mt-5 space-y-1"
            >
              <div
                v-if="menu[0] && menu[0].group_label"
                class="px-4 mt-6 mb-2 text-xs font-semibold text-subtle uppercase tracking-wider"
              >
                {{ $t(menu[0].group_label) }}
              </div>
              <router-link
                v-for="item in menu"
                :key="item.name"
                :to="item.link"
                :class="[
                  hasActiveUrl(item.link)
                    ? 'text-primary-600 bg-primary-50 font-semibold'
                    : 'text-body hover:bg-hover',
                  'cursor-pointer mx-3 px-3 py-2.5 flex items-center rounded-lg text-sm not-italic font-medium transition-colors',
                ]"
                @click="globalStore.setSidebarVisibility(false)"
              >
                <BaseIcon
                  :name="item.icon"
                  :class="[
                    hasActiveUrl(item.link)
                      ? 'text-primary-500'
                      : 'text-subtle',
                    'mr-3 shrink-0 h-5 w-5',
                  ]"
                  @click="globalStore.setSidebarVisibility(false)"
                />
                {{ $t(item.title) }}
              </router-link>
            </nav>
          </div>
        </div>
      </TransitionChild>
      <div class="shrink-0 w-14">
        <!-- Force sidebar to shrink to fit close icon -->
      </div>
    </Dialog>
  </TransitionRoot>

  <!-- DESKTOP MENU -->
  <div
    :class="[
      globalStore.isSidebarCollapsed ? 'w-16' : 'w-56 xl:w-64',
    ]"
    class="
      hidden
      h-screen
      pb-0
      overflow-y-auto overflow-x-hidden
      bg-surface/80 backdrop-blur-xl
      border-r border-white/10
      md:fixed md:flex md:flex-col md:inset-y-0
      pt-16
      transition-all duration-300
    "
  >
    <div
      v-for="(menu, index) in globalStore.menuGroups"
      :key="index"
      class="p-0 m-0 mt-4 list-none"
    >
      <div
        v-if="menu[0] && menu[0].group_label && !globalStore.isSidebarCollapsed"
        class="px-6 mt-6 mb-2 text-xs font-semibold text-subtle uppercase tracking-wider whitespace-nowrap"
      >
        {{ $t(menu[0].group_label) }}
      </div>
      <div
        v-else-if="menu[0] && menu[0].group_label && globalStore.isSidebarCollapsed"
        class="mx-3 my-2 border-t border-line-light"
      />
      <router-link
        v-for="item in menu"
        :key="item"
        :to="item.link"
        v-tooltip="globalStore.isSidebarCollapsed ? { content: $t(item.title), placement: 'right' } : null"
        :class="[
          hasActiveUrl(item.link)
            ? 'text-primary-600 bg-primary-50 font-semibold'
            : 'text-body hover:bg-hover',
          globalStore.isSidebarCollapsed
            ? 'cursor-pointer mx-2 px-0 py-2.5 group flex items-center justify-center rounded-lg text-sm font-medium transition-colors'
            : 'cursor-pointer mx-3 px-3 py-2.5 group flex items-center rounded-lg text-sm not-italic font-medium transition-colors',
        ]"
      >
        <BaseIcon
          :name="item.icon"
          :class="[
            hasActiveUrl(item.link)
              ? 'text-primary-500'
              : 'text-subtle group-hover:text-body',
            globalStore.isSidebarCollapsed
              ? 'shrink-0 h-6 w-6'
              : 'mr-3 shrink-0 h-5 w-5',
          ]"
        />

        <span v-if="!globalStore.isSidebarCollapsed" class="whitespace-nowrap">
          {{ $t(item.title) }}
        </span>
      </router-link>
    </div>

    <!-- Bottom toolbar -->
    <div class="mt-auto sticky bottom-0 border-t border-white/10 bg-surface/80 backdrop-blur-xl p-2 flex flex-col items-center gap-1">
      <button
        v-tooltip="globalStore.isSidebarCollapsed ? { content: $t('general.collapse'), placement: 'right' } : null"
        :class="[
          globalStore.isSidebarCollapsed
            ? 'w-10 h-10 justify-center'
            : 'w-full px-3 h-10 justify-end',
        ]"
        class="flex items-center rounded-lg text-subtle hover:text-body hover:bg-hover transition-colors"
        @click="globalStore.toggleSidebarCollapse()"
      >
        <BaseIcon
          :name="globalStore.isSidebarCollapsed ? 'ChevronDoubleRightIcon' : 'ChevronDoubleLeftIcon'"
          class="w-4 h-4 shrink-0"
        />
      </button>
    </div>
  </div>
</template>

<script setup>
import MainLogo from '@/scripts/components/icons/MainLogo.vue'

import {
  Dialog,
  DialogOverlay,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue'

import { useRoute } from 'vue-router'
import { useGlobalStore } from '@/scripts/admin/stores/global'

const route = useRoute()
const globalStore = useGlobalStore()

function hasActiveUrl(url) {
  return route.path.indexOf(url) > -1
}
</script>
