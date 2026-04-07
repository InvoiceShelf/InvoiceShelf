<template>
  <ModulePlaceholder v-if="isFetchingInitialData" />
  <BasePage v-else class="bg-white">
    <BasePageHeader :title="moduleData.name">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('modules.title')" to="/admin/modules" />
        <BaseBreadcrumbItem :title="moduleData.name" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <ModulesSecurityNotice />

    <div
      class="
        lg:grid lg:grid-rows-1 lg:grid-cols-7 lg:gap-x-8 lg:gap-y-10
        xl:gap-x-16
        mt-6
      "
    >
      <div class="lg:row-end-1 lg:col-span-4">
        <div
          class="aspect-w-4 aspect-h-3 rounded-lg bg-gradient-to-br from-primary-100 to-gray-100 overflow-hidden flex items-center justify-center min-h-[200px]"
        >
          <img
            v-if="moduleData.cover"
            :src="displayImage"
            alt=""
            class="h-full w-full object-contain object-center sm:rounded-lg"
          />
          <BaseIcon
            v-else
            name="PuzzlePieceIcon"
            class="h-32 w-32 text-primary-400 opacity-80"
          />
        </div>
      </div>

      <div
        class="
          max-w-2xl
          mx-auto
          mt-10
          lg:max-w-none lg:mt-0 lg:row-end-2 lg:row-span-2 lg:col-span-3
          w-full
        "
      >
        <div class="flex flex-wrap items-center gap-3">
          <h1
            class="
              text-2xl
              font-extrabold
              tracking-tight
              text-gray-900
              sm:text-3xl
            "
          >
            {{ moduleData.name }}
          </h1>
          <span
            class="
              inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
            "
            :class="catalogKindBadgeClass"
          >
            {{ catalogKindLabel }}
          </span>
        </div>

        <p v-if="moduleData.latest_module_version" class="text-sm text-gray-500 mt-2">
          {{ $t('modules.version') }}
          {{ moduleVersion }}
        </p>

        <p
          v-if="compatibilityRange"
          class="text-sm text-gray-600 mt-1"
        >
          {{ $t('modules.catalog_compatibility', { range: compatibilityRange }) }}
        </p>

        <div
          class="prose prose-sm max-w-none text-gray-500 text-sm my-6"
          v-html="moduleData.long_description"
        />

        <div v-if="moduleData.tags && moduleData.tags.length" class="flex flex-wrap gap-2 mb-6">
          <span
            v-for="(tag, i) in moduleData.tags"
            :key="i"
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
          >
            {{ tag }}
          </span>
        </div>

        <a
          v-if="moduleData.repository"
          :href="moduleData.repository"
          target="_blank"
          rel="noopener noreferrer"
          class="text-primary-600 text-sm font-medium hover:text-primary-500"
        >
          {{ $t('modules.view_repository') }}
        </a>

        <div class="mt-10 space-y-4">
          <div v-if="!moduleData.installed" class="flex flex-wrap gap-4">
            <BaseButton
              v-if="moduleData.latest_module_version"
              size="xl"
              variant="primary-outline"
              outline
              :loading="isInstalling"
              :disabled="isInstalling"
              class="flex items-center justify-center text-base"
              @click="installModule()"
            >
              <BaseIcon v-if="!isInstalling" name="ArrowDownTrayIcon" class="mr-2" />
              {{ $t('modules.install') }}
            </BaseButton>
          </div>

          <div v-else class="flex flex-wrap gap-4">
            <BaseButton
              v-if="moduleData.update_available"
              variant="primary"
              size="xl"
              :loading="isInstalling"
              :disabled="isInstalling"
              class="flex items-center justify-center text-base"
              @click="installModule()"
            >
              {{ $t('modules.update_to') }}
              <span class="ml-2">{{ moduleData.latest_module_version }}</span>
            </BaseButton>

            <template v-if="isModuleKind">
              <BaseButton
                v-if="moduleData.enabled"
                variant="danger"
                size="xl"
                :loading="isDisabling"
                :disabled="isDisabling"
                class="flex items-center justify-center text-base"
                @click="disableModule"
              >
                <BaseIcon v-if="!isDisabling" name="NoSymbolIcon" class="mr-2" />
                {{ $t('modules.disable') }}
              </BaseButton>
              <BaseButton
                v-else
                variant="primary-outline"
                size="xl"
                :loading="isEnabling"
                :disabled="isEnabling"
                class="flex items-center justify-center text-base"
                @click="enableModule"
              >
                <BaseIcon v-if="!isEnabling" name="CheckIcon" class="mr-2" />
                {{ $t('modules.enable') }}
              </BaseButton>

              <BaseButton
                variant="danger"
                size="xl"
                :loading="isUninstalling"
                :disabled="isUninstalling"
                class="flex items-center justify-center text-base"
                @click="uninstallModule"
              >
                {{ $t('modules.uninstall') }}
              </BaseButton>
            </template>
          </div>
        </div>

        <div v-if="isInstalling" class="border-t border-gray-200 mt-10 pt-10">
          <ul class="w-full p-0 list-none">
            <li
              v-for="step in installationSteps"
              :key="step.stepUrl"
              class="
                flex
                justify-between
                w-full
                py-3
                border-b border-gray-200 border-solid
                last:border-b-0
              "
            >
              <p class="m-0 text-sm leading-8">
                {{ $t(step.translationKey) }}
              </p>
              <div class="flex flex-row items-center">
                <span
                  :class="statusClass(step)"
                  class="block py-1 text-sm text-center uppercase rounded-full"
                  style="width: 88px"
                >
                  {{ getStatus(step) }}
                </span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div
      v-if="otherModules && otherModules.length"
      class="mt-24 sm:mt-32 lg:max-w-none"
    >
      <div class="flex items-center justify-between space-x-4">
        <h2 class="text-lg font-medium text-gray-900">
          {{ $t('modules.other_modules') }}
        </h2>
        <a
          href="/admin/modules"
          class="
            whitespace-nowrap
            text-sm
            font-medium
            text-primary-600
            hover:text-primary-500
          "
          >{{ $t('modules.view_all')
          }}<span aria-hidden="true"> &rarr;</span></a
        >
      </div>
      <div
        class="
          mt-6
          grid grid-cols-1
          gap-x-8 gap-y-8
          sm:grid-cols-2 sm:gap-y-10
          lg:grid-cols-4
        "
      >
        <div v-for="(other, moduleIdx) in otherModules" :key="moduleIdx">
          <RecentModuleCard :data="other" />
        </div>
      </div>
    </div>

    <div class="p-6"></div>
  </BasePage>
</template>

<script setup>
import { useModuleStore } from '@/scripts/admin/stores/module'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useI18n } from 'vue-i18n'
import http from '@/scripts/http'
import ModulePlaceholder from './partials/ModulePlaceholder.vue'
import ModulesSecurityNotice from './partials/ModulesSecurityNotice.vue'
import RecentModuleCard from './partials/RecentModuleCard.vue'
import { useNotificationStore } from '@/scripts/stores/notification'

/**
 * @param {Record<string, unknown>|null|undefined} compatibility
 */
function formatCatalogCompatibilityRange(compatibility) {
  if (! compatibility || typeof compatibility !== 'object') {
    return ''
  }

  const min = compatibility.min_version
  const max = compatibility.max_version
  const hasMin = min !== undefined && min !== null && String(min).trim() !== ''
  const hasMax = max !== undefined && max !== null && String(max).trim() !== ''

  if (hasMin && hasMax) {
    return `${String(min).trim()} – ${String(max).trim()}`
  }
  if (hasMin) {
    return `${String(min).trim()}+`
  }
  if (hasMax) {
    return `≤ ${String(max).trim()}`
  }

  return ''
}

const moduleStore = useModuleStore()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()

const route = useRoute()
const { t } = useI18n()
const isInstalling = ref(false)
const isFetchingInitialData = ref(true)
const displayImage = ref('')
const isEnabling = ref(false)
const isDisabling = ref(false)
const isUninstalling = ref(false)

const moduleData = computed(() => {
  return moduleStore.currentModule.data || {}
})

const isModuleKind = computed(() => {
  return (moduleData.value.catalog_kind || 'module') === 'module'
})

const catalogKindLabel = computed(() => {
  if (moduleData.value.catalog_kind === 'pdf_template') {
    return moduleData.value.pdf_template_type === 'invoice'
      ? t('modules.kind_invoice_template')
      : t('modules.kind_estimate_template')
  }

  return t('modules.kind_extension')
})

const catalogKindBadgeClass = computed(() => {
  if (moduleData.value.catalog_kind === 'pdf_template') {
    return moduleData.value.pdf_template_type === 'invoice'
      ? 'bg-sky-50 text-sky-900 ring-1 ring-inset ring-sky-200'
      : 'bg-violet-50 text-violet-900 ring-1 ring-inset ring-violet-200'
  }

  return 'bg-gray-100 text-gray-800 ring-1 ring-inset ring-gray-200'
})

const compatibilityRange = computed(() =>
  formatCatalogCompatibilityRange(moduleData.value.compatibility),
)

const otherModules = computed(() => {
  const raw = moduleStore.currentModule.meta?.modules
  if (!raw) {
    return []
  }
  if (Array.isArray(raw)) {
    return raw
  }

  return raw.data || []
})

const moduleVersion = computed(() => {
  const latest = moduleData.value.latest_module_version
  const installed = moduleData.value.installed_module_version

  return installed || latest
})

loadData()

watch(
  () => route.params.slug,
  async () => {
    loadData()
  }
)

const installationSteps = reactive([
  {
    translationKey: 'modules.download_zip_file',
    stepUrl: '/api/v1/modules/download',
    time: null,
    started: false,
    completed: false,
  },
  {
    translationKey: 'modules.unzipping_package',
    stepUrl: '/api/v1/modules/unzip',
    time: null,
    started: false,
    completed: false,
  },
  {
    translationKey: 'modules.copying_files',
    stepUrl: '/api/v1/modules/copy',
    time: null,
    started: false,
    completed: false,
  },
  {
    translationKey: 'modules.completing_installation',
    stepUrl: '/api/v1/modules/complete',
    time: null,
    started: false,
    completed: false,
  },
])

function resetInstallationSteps() {
  installationSteps.forEach((step) => {
    step.started = false
    step.completed = false
  })
}

async function installModule() {
  resetInstallationSteps()
  let path = null

  for (let index = 0; index < installationSteps.length; index++) {
    const currentStep = installationSteps[index]

    try {
      isInstalling.value = true
      currentStep.started = true
      const updateParams = {
        version: moduleData.value.latest_module_version,
        path: path || null,
        module: moduleData.value.module_name,
        catalog_kind: moduleData.value.catalog_kind || 'module',
      }

      const requestResponse = await http.post(currentStep.stepUrl, updateParams)

      currentStep.completed = true
      if (requestResponse.data) {
        path = requestResponse.data.path
      }

      if (!requestResponse.data.success) {
        const displayMsg = getErrorMessage(requestResponse.data.message)

        notificationStore.showNotification({
          type: 'error',
          message: displayMsg,
        })

        isInstalling.value = false
        currentStep.started = false
        currentStep.completed = true
        return false
      }
      if (currentStep.translationKey === 'modules.completing_installation') {
        isInstalling.value = false
        notificationStore.showNotification({
          type: 'success',
          message: t('modules.install_success'),
        })

        setTimeout(() => {
          location.reload()
        }, 1500)
      }
    } catch (error) {
      isInstalling.value = false
      currentStep.started = false
      currentStep.completed = true

      let msg = t('modules.install_failed')
      if (error.response?.data) {
        if (error.response.data.errors) {
          const firstKey = Object.keys(error.response.data.errors)[0]
          const first = error.response.data.errors[firstKey]
          msg = Array.isArray(first) ? first[0] : String(first)
        } else if (error.response.data.message) {
          msg = error.response.data.message
        }
      } else if (error.message) {
        msg = error.message
      }

      notificationStore.showNotification({
        type: 'error',
        message: msg,
      })
      return false
    }
  }
}

function getErrorMessage(message) {
  switch (message) {
    case 'module_not_found':
      return t('modules.module_not_found')
    case 'version_not_supported':
      return t('modules.version_not_supported', { version: '' })
    case 'version_mismatch':
      return t('modules.version_mismatch')
    case 'download_url_missing':
      return t('modules.download_url_missing')
    case 'download_failed':
      return t('modules.download_failed')
    case 'extensions_catalog_unavailable':
      return t('modules.extensions_catalog_unavailable')
    case 'download_write_failed':
      return t('modules.download_write_failed')
    default:
      return typeof message === 'string' ? message : t('general.action_failed')
  }
}

async function loadData() {
  if (!route.params.slug) {
    return
  }

  isFetchingInitialData.value = true
  try {
    await moduleStore.fetchModule(route.params.slug)
    displayImage.value = moduleData.value.cover || ''
  } finally {
    isFetchingInitialData.value = false
  }
}

function statusClass(step) {
  const status = getStatus(step)

  switch (status) {
    case 'pending':
      return 'text-primary-800 bg-gray-200'
    case 'finished':
      return 'text-teal-500 bg-teal-100'
    case 'running':
      return 'text-blue-400 bg-blue-100'
    case 'error':
      return 'text-danger bg-red-200'
    default:
      return ''
  }
}

function disableModule() {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('modules.disable_warning'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res) => {
      if (res) {
        isDisabling.value = true
        await moduleStore.disableModule(moduleData.value.module_name).then((res) => {
          if (res.data.success) {
            setTimeout(() => {
              location.reload()
            }, 1500)
          }
        })
        isDisabling.value = false
      }
    })
}

async function enableModule() {
  isEnabling.value = true

  await moduleStore.enableModule(moduleData.value.module_name).then((res) => {
    if (res.data.success) {
      setTimeout(() => {
        location.reload()
      }, 1500)
    }
    isEnabling.value = false
  })
}

function uninstallModule() {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('modules.uninstall_warning'),
      yesLabel: t('modules.uninstall'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then(async (res) => {
      if (!res) {
        return
      }
      isUninstalling.value = true
      try {
        await moduleStore.uninstallModule(moduleData.value.module_name)
        setTimeout(() => {
          window.location.href = '/admin/modules'
        }, 1200)
      } finally {
        isUninstalling.value = false
      }
    })
}

function getStatus(step) {
  if (step.started && step.completed) {
    return 'finished'
  } else if (step.started && !step.completed) {
    return 'running'
  } else if (!step.started && !step.completed) {
    return 'pending'
  }
  return 'error'
}
</script>
