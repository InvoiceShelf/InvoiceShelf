<template>
  <BasePage>
    <BasePageHeader :title="$t('modules.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('modules.module', 2)" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <ModulesSecurityNotice />

    <BaseCard v-if="catalogError" class="mt-6 border-amber-200 bg-amber-50">
      <p class="text-sm text-amber-900">
        {{ $t('modules.extensions_catalog_unavailable') }}
      </p>
      <BaseButton class="mt-4" type="button" @click="retryFetch">
        {{ $t('general.retry') }}
      </BaseButton>
    </BaseCard>

    <div
      v-else-if="moduleStore.modules === null && isFetchingModule"
      class="
        mt-6
        grid w-full
        grid-cols-[repeat(auto-fill,minmax(280px,1fr))]
        gap-4
      "
    >
      <ModuleCardPlaceholder />
      <ModuleCardPlaceholder />
      <ModuleCardPlaceholder />
    </div>

    <div v-else-if="moduleStore.modules !== null">
      <div
        class="
          mt-6
          flex w-full min-w-0
          flex-row flex-nowrap items-center justify-between gap-3
          rounded-sm
          border border-gray-200
          bg-gray-50
          p-4
          sm:gap-6
        "
      >
        <nav
          class="flex min-w-0 flex-nowrap items-center gap-x-1 text-sm"
          :aria-label="$t('modules.title')"
        >
          <button
            type="button"
            :class="filterLinkClass('')"
            @click="setFilter('')"
          >
            {{ $t('general.all') }}
            <span class="font-normal text-gray-500">({{ totalCount }})</span>
          </button>
          <span class="px-1 text-gray-300" aria-hidden="true">|</span>
          <button
            type="button"
            :class="filterLinkClass('INSTALLED')"
            @click="setFilter('INSTALLED')"
          >
            {{ $t('modules.installed') }}
            <span class="font-normal text-gray-500">({{ installedCount }})</span>
          </button>
        </nav>
        <div class="w-52 shrink-0 sm:w-72 lg:w-80">
          <BaseInput
            v-model="searchQuery"
            type="search"
            :placeholder="$t('general.search')"
            variant="gray"
            autocomplete="off"
          >
            <template #right>
              <BaseIcon name="MagnifyingGlassIcon" class="h-5 text-gray-400" />
            </template>
          </BaseInput>
        </div>
      </div>

      <div
        v-if="isFetchingModule"
        class="
          mt-6
          grid w-full
          grid-cols-[repeat(auto-fill,minmax(280px,1fr))]
          gap-4
        "
      >
        <ModuleCardPlaceholder />
        <ModuleCardPlaceholder />
        <ModuleCardPlaceholder />
      </div>

      <div v-else>
        <p
          v-if="modules.length && moduleStore.modules?.length"
          class="mt-4 text-xs text-gray-500"
        >
          {{ $t('modules.showing_extensions', { count: modules.length }) }}
        </p>
        <div
          v-if="modules && modules.length"
          class="
            mt-2
            grid w-full
            grid-cols-[repeat(auto-fill,minmax(280px,1fr))]
            gap-4
          "
        >
          <div v-for="moduleData in modules" :key="moduleData.slug || moduleData.name">
            <ModuleCard :data="moduleData" />
          </div>
        </div>
        <div
          v-else
          class="
            mt-16
            rounded-sm
            border border-dashed border-gray-300
            bg-gray-50/80
            px-6
            py-16
            text-center
          "
        >
          <p class="text-sm text-gray-600">
            <template
              v-if="
                searchQuery.trim() &&
                moduleStore.modules &&
                moduleStore.modules.length > 0
              "
            >
              {{ $t('modules.no_search_results') }}
            </template>
            <template v-else>
              {{ $t('modules.no_extensions_in_catalog') }}
            </template>
          </p>
        </div>
      </div>
    </div>
  </BasePage>
</template>

<script setup>
import { useModuleStore } from '@/scripts/admin/stores/module'
import { computed, ref } from 'vue'

import ModuleCard from './partials/ModuleCard.vue'
import ModuleCardPlaceholder from './partials/ModuleCardPlaceholder.vue'
import ModulesSecurityNotice from './partials/ModulesSecurityNotice.vue'

const moduleStore = useModuleStore()
const activeTab = ref('')
const searchQuery = ref('')
const isFetchingModule = ref(true)
const catalogError = ref(false)

const totalCount = computed(() => moduleStore.modules?.length ?? 0)

const installedCount = computed(
  () => moduleStore.modules?.filter((m) => m.installed).length ?? 0,
)

const modules = computed(() => {
  if (! moduleStore.modules) {
    return []
  }

  let list = moduleStore.modules

  if (activeTab.value === 'INSTALLED') {
    list = list.filter((_m) => _m.installed)
  }

  const q = searchQuery.value.trim().toLowerCase()
  if (! q) {
    return list
  }

  return list.filter((m) => {
    const name = (m.name || '').toLowerCase()
    const desc = (m.short_description || '').toLowerCase()
    const author = (m.author_name || '').toLowerCase()
    const slug = (m.slug || '').toLowerCase()

    return (
      name.includes(q) ||
      desc.includes(q) ||
      author.includes(q) ||
      slug.includes(q)
    )
  })
})

function filterLinkClass(filter) {
  const active = activeTab.value === filter

  return [
    'rounded px-1.5 py-0.5 text-left transition-colors',
    active
      ? 'cursor-default font-semibold text-gray-900'
      : 'font-medium text-primary-600 hover:text-primary-800 hover:underline',
  ]
}

async function fetchModulesData() {
  isFetchingModule.value = true
  catalogError.value = false

  try {
    await moduleStore.fetchModules()
  } catch (err) {
    if (err.response?.status === 503) {
      catalogError.value = true
    }
  } finally {
    isFetchingModule.value = false
  }
}

fetchModulesData()

function retryFetch() {
  fetchModulesData()
}

function setFilter(filter) {
  activeTab.value = filter
}
</script>
