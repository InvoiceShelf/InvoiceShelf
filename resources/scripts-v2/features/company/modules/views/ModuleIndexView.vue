<template>
  <BasePage>
    <BasePageHeader :title="$t('modules.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('modules.module', 2)" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <!-- Connected: module listing -->
    <div v-if="hasApiToken && moduleStore.modules">
      <BaseTabGroup class="-mb-5" @change="setStatusFilter">
        <BaseTab :title="$t('general.all')" filter="" />
        <BaseTab :title="$t('modules.installed')" filter="INSTALLED" />
      </BaseTabGroup>

      <!-- Placeholder -->
      <div
        v-if="isFetchingModule"
        class="grid mt-6 w-full grid-cols-1 items-start gap-6 lg:grid-cols-2 xl:grid-cols-3"
      >
        <div v-for="n in 3" :key="n" class="h-80 bg-surface-tertiary rounded-lg animate-pulse" />
      </div>

      <!-- Module Cards -->
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

    <!-- Not connected: API token form -->
    <BaseCard v-else class="mt-6">
      <h6 class="text-heading text-lg font-medium">
        {{ $t('modules.connect_installation') }}
      </h6>
      <p class="mt-1 text-sm text-muted">
        {{ $t('modules.api_token_description', { url: baseUrlDisplay }) }}
      </p>

      <div class="grid lg:grid-cols-2 mt-6">
        <form class="mt-6" @submit.prevent="submitApiToken">
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

          <div class="flex space-x-2">
            <BaseButton class="mt-6" :loading="isSaving" type="submit">
              <template #left="slotProps">
                <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
              </template>
              {{ $t('general.save') }}
            </BaseButton>

            <a
              :href="signUpUrl"
              class="mt-6 block"
              target="_blank"
            >
              <BaseButton variant="primary-outline" type="button">
                {{ $t('modules.sign_up_and_get_token') }}
              </BaseButton>
            </a>
          </div>
        </form>
      </div>
    </BaseCard>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useModuleStore } from '../store'
import ModuleCard from '../components/ModuleCard.vue'
import type { Module } from '../../../../types/domain/module'

interface Props {
  baseUrl?: string
}

const props = withDefaults(defineProps<Props>(), {
  baseUrl: '',
})

const moduleStore = useModuleStore()
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

const hasApiToken = computed<boolean>(() => !!moduleStore.apiToken)

const filteredModules = computed<Module[]>(() => {
  if (activeTab.value === 'INSTALLED') {
    return moduleStore.installedModules
  }
  return moduleStore.modules
})

const baseUrlDisplay = computed<string>(() => {
  return props.baseUrl.replace(/^http:\/\//, '')
})

const signUpUrl = computed<string>(() => {
  return `${props.baseUrl}/auth/customer/register`
})

watch(hasApiToken, (val) => {
  if (val) fetchModulesData()
}, { immediate: true })

async function fetchModulesData(): Promise<void> {
  isFetchingModule.value = true
  await moduleStore.fetchModules()
  isFetchingModule.value = false
}

async function submitApiToken(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSaving.value = true

  try {
    const response = await moduleStore.checkApiToken(
      moduleStore.currentUser.api_token ?? '',
    )
    if (response.success) {
      moduleStore.apiToken = moduleStore.currentUser.api_token
    }
  } finally {
    isSaving.value = false
  }
}

function setStatusFilter(data: { filter: string }): void {
  activeTab.value = data.filter
}
</script>
