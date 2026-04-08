<template>
  <BasePage>
    <BasePageHeader :title="$t('modules.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('modules.module', 2)" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <p class="mt-4 text-sm text-muted max-w-3xl">
      {{ $t('modules.index.description') }}
    </p>

    <!-- Loading skeleton -->
    <div
      v-if="store.isFetching && store.modules.length === 0"
      class="grid mt-8 w-full grid-cols-1 items-start gap-6 lg:grid-cols-2 xl:grid-cols-3"
    >
      <div v-for="n in 3" :key="n" class="h-32 bg-surface-tertiary rounded-lg animate-pulse" />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="store.modules.length === 0"
      class="mt-16 flex flex-col items-center justify-center text-center"
    >
      <div class="
        h-16 w-16 rounded-full bg-surface-tertiary
        flex items-center justify-center mb-4
      ">
        <BaseIcon name="PuzzlePieceIcon" class="h-8 w-8 text-subtle" />
      </div>
      <h3 class="text-lg font-medium text-heading">
        {{ $t('modules.index.empty_title') }}
      </h3>
      <p class="text-sm text-muted mt-2 max-w-md">
        {{ $t('modules.index.empty_description') }}
      </p>
    </div>

    <!-- Module list -->
    <div
      v-else
      class="grid mt-8 w-full grid-cols-1 items-start gap-6 lg:grid-cols-2 xl:grid-cols-3"
    >
      <CompanyModuleCard
        v-for="mod in store.modules"
        :key="mod.slug"
        :data="mod"
      />
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useCompanyModulesStore } from '../store'
import CompanyModuleCard from '../components/CompanyModuleCard.vue'

const store = useCompanyModulesStore()

onMounted(() => {
  store.fetchModules()
})
</script>
