<template>
  <BasePage>
    <BasePageHeader :title="$t('navigation.dashboard')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem
          :title="$t('navigation.administration')"
          to="/admin/administration/dashboard"
          active
        />
      </BaseBreadcrumb>
    </BasePageHeader>

    <div v-if="isLoading" class="flex justify-center py-16">
      <BaseGlobalLoader />
    </div>

    <div v-else class="grid grid-cols-1 gap-6 mt-6 md:grid-cols-2 lg:grid-cols-3">
      <!-- App Version -->
      <BaseCard>
        <template #header>
          <div class="flex items-center">
            <BaseIcon name="ServerIcon" class="w-5 h-5 mr-2 text-subtle" />
            <span class="font-medium text-body">{{ $t('general.app_version') }}</span>
          </div>
        </template>
        <p class="text-2xl font-semibold text-heading">
          {{ data.app_version }}
        </p>
      </BaseCard>

      <!-- PHP Version -->
      <BaseCard>
        <template #header>
          <div class="flex items-center">
            <BaseIcon name="CodeBracketIcon" class="w-5 h-5 mr-2 text-subtle" />
            <span class="font-medium text-body">PHP</span>
          </div>
        </template>
        <p class="text-2xl font-semibold text-heading">
          {{ data.php_version }}
        </p>
      </BaseCard>

      <!-- Database -->
      <BaseCard>
        <template #header>
          <div class="flex items-center">
            <BaseIcon name="CircleStackIcon" class="w-5 h-5 mr-2 text-subtle" />
            <span class="font-medium text-body">{{ $t('general.database') }}</span>
          </div>
        </template>
        <p class="text-2xl font-semibold text-heading">
          {{ data.database?.driver?.toUpperCase() }}
        </p>
        <p class="text-sm text-muted mt-1">
          {{ data.database?.version }}
        </p>
      </BaseCard>

      <!-- Companies -->
      <BaseCard>
        <template #header>
          <div class="flex items-center">
            <BaseIcon name="BuildingOfficeIcon" class="w-5 h-5 mr-2 text-subtle" />
            <span class="font-medium text-body">{{ $t('navigation.companies') }}</span>
          </div>
        </template>
        <p class="text-2xl font-semibold text-heading">
          {{ data.counts?.companies }}
        </p>
      </BaseCard>

      <!-- Users -->
      <BaseCard>
        <template #header>
          <div class="flex items-center">
            <BaseIcon name="UsersIcon" class="w-5 h-5 mr-2 text-subtle" />
            <span class="font-medium text-body">{{ $t('navigation.all_users') }}</span>
          </div>
        </template>
        <p class="text-2xl font-semibold text-heading">
          {{ data.counts?.users }}
        </p>
      </BaseCard>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAdminStore } from '../stores/admin.store'
import type { AdminDashboardData } from '../stores/admin.store'

const adminStore = useAdminStore()
const isLoading = ref<boolean>(true)
const data = ref<Partial<AdminDashboardData>>({})

onMounted(async () => {
  try {
    data.value = await adminStore.fetchDashboard()
  } finally {
    isLoading.value = false
  }
})
</script>
