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
            <BaseIcon name="ServerIcon" class="w-5 h-5 mr-2 text-gray-400" />
            <span class="font-medium text-gray-700">{{ $t('general.app_version') }}</span>
          </div>
        </template>
        <p class="text-2xl font-semibold text-gray-900">
          {{ data.app_version }}
        </p>
      </BaseCard>

      <!-- PHP Version -->
      <BaseCard>
        <template #header>
          <div class="flex items-center">
            <BaseIcon name="CodeBracketIcon" class="w-5 h-5 mr-2 text-gray-400" />
            <span class="font-medium text-gray-700">PHP</span>
          </div>
        </template>
        <p class="text-2xl font-semibold text-gray-900">
          {{ data.php_version }}
        </p>
      </BaseCard>

      <!-- Database -->
      <BaseCard>
        <template #header>
          <div class="flex items-center">
            <BaseIcon name="CircleStackIcon" class="w-5 h-5 mr-2 text-gray-400" />
            <span class="font-medium text-gray-700">{{ $t('general.database') }}</span>
          </div>
        </template>
        <p class="text-2xl font-semibold text-gray-900">
          {{ data.database?.driver?.toUpperCase() }}
        </p>
        <p class="text-sm text-gray-500 mt-1">
          {{ data.database?.version }}
        </p>
      </BaseCard>

      <!-- Companies -->
      <BaseCard>
        <template #header>
          <div class="flex items-center">
            <BaseIcon name="BuildingOfficeIcon" class="w-5 h-5 mr-2 text-gray-400" />
            <span class="font-medium text-gray-700">{{ $t('navigation.companies') }}</span>
          </div>
        </template>
        <p class="text-2xl font-semibold text-gray-900">
          {{ data.counts?.companies }}
        </p>
      </BaseCard>

      <!-- Users -->
      <BaseCard>
        <template #header>
          <div class="flex items-center">
            <BaseIcon name="UsersIcon" class="w-5 h-5 mr-2 text-gray-400" />
            <span class="font-medium text-gray-700">{{ $t('navigation.all_users') }}</span>
          </div>
        </template>
        <p class="text-2xl font-semibold text-gray-900">
          {{ data.counts?.users }}
        </p>
      </BaseCard>
    </div>
  </BasePage>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import http from '@/scripts/http'

const isLoading = ref(true)
const data = ref({})

onMounted(async () => {
  try {
    const response = await http.get('/api/v1/super-admin/dashboard')
    data.value = response.data
  } finally {
    isLoading.value = false
  }
})
</script>
