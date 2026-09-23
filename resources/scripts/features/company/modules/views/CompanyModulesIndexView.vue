<template>
  <BaseSettingCard
    :title="$t('modules.title')"
    :description="$t('modules.index.description')"
  >
    <ModuleSettingsModal />

    <!-- Loading skeleton -->
    <div
      v-if="store.isFetching && store.modules.length === 0"
      class="grid mt-8 w-full grid-cols-1 items-start gap-6 lg:grid-cols-2"
    >
      <div v-for="n in 3" :key="n" class="h-32 bg-surface-tertiary rounded-lg animate-pulse" />
    </div>

    <!-- Empty state -->
    <BaseEmptyPlaceholder
      v-else-if="store.modules.length === 0"
      class="mt-8"
      art="module"
      :title="$t('modules.index.empty_title')"
      :description="$t('modules.index.empty_description')"
    />

    <!-- Module list -->
    <div
      v-else
      class="grid mt-8 w-full grid-cols-1 items-start gap-6 lg:grid-cols-2"
    >
      <CompanyModuleCard
        v-for="mod in store.modules"
        :key="mod.slug"
        :data="mod"
        @open-settings="openSettingsModal"
      />
    </div>
  </BaseSettingCard>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useCompanyModulesStore } from '../store'
import type { CompanyModuleSummary } from '../store'
import CompanyModuleCard from '../components/CompanyModuleCard.vue'
import ModuleSettingsModal from '../components/ModuleSettingsModal.vue'

const store = useCompanyModulesStore()
const modalStore = useModalStore()

onMounted(() => {
  store.fetchModules()
})

function openSettingsModal(module: CompanyModuleSummary): void {
  modalStore.openModal({
    componentName: 'ModuleSettingsModal',
    title: module.display_name,
    data: module,
    size: 'lg',
  })
}
</script>
