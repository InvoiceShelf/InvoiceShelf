<template>
  <BasePage>
    <BasePageHeader :title="$t('modules.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('modules.module', 2)" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <BaseCard class="mt-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h6 class="text-heading text-lg font-medium">Marketplace Access</h6>
          <p class="mt-1 text-sm text-muted">
            Public modules are always available. Add your marketplace token to unlock premium modules tied to your website subscription.
          </p>
        </div>

        <span
          class="inline-flex rounded-full px-3 py-1 text-sm font-medium"
          :class="statusClass"
        >
          {{ statusLabel }}
        </span>
      </div>

      <div class="grid mt-6 lg:grid-cols-2">
        <form class="space-y-4" @submit.prevent="submitApiToken">
          <BaseInputGroup
            :label="$t('modules.api_token')"
            required
            :error="v$.api_token.$error ? String(v$.api_token.$errors[0]?.$message) : undefined"
          >
            <BaseInput
              v-model="moduleStore.currentUser.api_token"
              :invalid="v$.api_token.$error"
              @input="v$.api_token.$touch()"
            />
          </BaseInputGroup>

          <div class="flex flex-wrap gap-3">
            <BaseButton :loading="isSaving" type="submit">
              <template #left="slotProps">
                <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
              </template>
              Save Token
            </BaseButton>

            <BaseButton
              v-if="moduleStore.apiToken"
              variant="primary-outline"
              type="button"
              @click="clearApiToken"
            >
              Clear Token
            </BaseButton>

            <a :href="tokenPageUrl" target="_blank" rel="noopener" class="inline-flex">
              <BaseButton variant="primary-outline" type="button">
                Manage Token
              </BaseButton>
            </a>
          </div>
        </form>
      </div>
    </BaseCard>

    <div class="mt-6">
      <BaseTabGroup @change="setStatusFilter">
        <BaseTab :title="$t('general.all')" filter="" />
        <BaseTab :title="$t('modules.installed')" filter="INSTALLED" />
      </BaseTabGroup>

      <div
        v-if="isFetchingModule"
        class="grid mt-6 w-full grid-cols-1 items-start gap-6 lg:grid-cols-2 xl:grid-cols-3"
      >
        <div v-for="n in 3" :key="n" class="h-80 bg-surface-tertiary rounded-lg animate-pulse" />
      </div>

      <div v-else>
        <div
          v-if="filteredModules.length"
          class="grid mt-6 w-full grid-cols-1 items-start gap-6 lg:grid-cols-2 xl:grid-cols-3"
        >
          <div v-for="(mod, idx) in filteredModules" :key="idx">
            <ModuleCard :data="mod" />
          </div>
        </div>
        <div v-else class="mt-24">
          <label class="flex items-center justify-center text-muted">
            {{ $t('modules.no_modules_installed') }}
          </label>
        </div>
      </div>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useModuleStore } from '../store'
import ModuleCard from '../components/ModuleCard.vue'
import type { Module } from '../../../../types/domain/module'
import { useGlobalStore } from '@/scripts/stores/global.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'

const moduleStore = useModuleStore()
const globalStore = useGlobalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const activeTab = ref<string>('')
const isSaving = ref<boolean>(false)
const isFetchingModule = ref<boolean>(false)

const rules = computed(() => ({
  api_token: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3),
    ),
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => moduleStore.currentUser),
)

const filteredModules = computed<Module[]>(() => {
  if (activeTab.value === 'INSTALLED') {
    return moduleStore.installedModules
  }
  return moduleStore.modules
})

const statusLabel = computed<string>(() => {
  if (moduleStore.marketplaceStatus.invalidToken) {
    return 'Invalid token'
  }

  if (moduleStore.marketplaceStatus.premium) {
    return 'Premium modules unlocked'
  }

  if (moduleStore.marketplaceStatus.authenticated) {
    return 'Connected'
  }

  return 'Public modules only'
})

const statusClass = computed<string>(() => {
  if (moduleStore.marketplaceStatus.invalidToken) {
    return 'bg-red-100 text-red-700'
  }

  if (moduleStore.marketplaceStatus.premium) {
    return 'bg-amber-100 text-amber-800'
  }

  if (moduleStore.marketplaceStatus.authenticated) {
    return 'bg-green-100 text-green-700'
  }

  return 'bg-surface-secondary text-muted'
})

const baseUrl = computed<string>(() => {
  return String(globalStore.config?.base_url ?? '')
})

const tokenPageUrl = computed<string>(() => {
  return `${baseUrl.value}/marketplace/token`
})

onMounted(async () => {
  const savedToken = String(globalStore.globalSettings?.api_token ?? '').trim() || null
  moduleStore.setApiToken(savedToken)

  if (savedToken) {
    const response = await moduleStore.checkApiToken(savedToken)
    if (response.error === 'invalid_token') {
      notificationStore.showNotification({
        type: 'error',
        message: 'Saved marketplace token is invalid. Public modules are shown until you update it.',
      })
    }
  } else {
    moduleStore.clearMarketplaceStatus()
  }

  await fetchModulesData()
})

async function fetchModulesData(): Promise<void> {
  isFetchingModule.value = true
  try {
    await moduleStore.fetchModules()
  } finally {
    isFetchingModule.value = false
  }
}

async function submitApiToken(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true

  try {
    const token = moduleStore.currentUser.api_token ?? ''
    const response = await moduleStore.checkApiToken(token)

    if (!response.success) {
      notificationStore.showNotification({
        type: 'error',
        message: response.error === 'invalid_token'
          ? 'Invalid marketplace token'
          : 'Unable to validate marketplace token',
      })
      return
    }

    await globalStore.updateGlobalSettings({
      data: {
        settings: {
          api_token: token,
        },
      },
      message: 'Marketplace token saved',
    })

    moduleStore.setApiToken(token)
    await fetchModulesData()
  } finally {
    isSaving.value = false
  }
}

async function clearApiToken(): Promise<void> {
  await globalStore.updateGlobalSettings({
    data: {
      settings: {
        api_token: null,
      },
    },
    message: 'Marketplace token cleared',
  })

  moduleStore.setApiToken(null)
  moduleStore.clearMarketplaceStatus()
  v$.value.$reset()
  await fetchModulesData()
}

function setStatusFilter(data: { filter: string }): void {
  activeTab.value = data.filter
}
</script>
