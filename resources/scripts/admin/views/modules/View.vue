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

    <div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-12">
      <div class="lg:col-span-5 space-y-4">
        <div
          class="aspect-w-4 aspect-h-3 max-h-[260px] overflow-hidden rounded-lg bg-linear-to-br from-primary-100 to-gray-100 flex items-center justify-center"
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

        <div class="rounded-lg border border-gray-200 bg-white p-4">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-xl font-extrabold tracking-tight text-gray-900">
              {{ moduleData.name }}
            </h1>
            <span
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
              :class="catalogKindBadgeClass"
            >
              {{ catalogKindLabel }}
            </span>
          </div>

          <p
            v-if="moduleData.latest_module_version"
            class="mt-2 text-sm text-gray-500"
          >
            {{ $t('modules.version') }}
            {{ moduleVersion }}
          </p>

          <p v-if="compatibilityRange" class="mt-1 text-sm text-gray-600">
            {{
              $t('modules.catalog_compatibility', {
                range: compatibilityRange,
              })
            }}
          </p>

          <div class="mt-4 flex flex-wrap items-center gap-3">
            <a
              v-if="moduleData.repository"
              :href="moduleData.repository"
              target="_blank"
              rel="noopener noreferrer"
              class="text-primary-600 text-sm font-medium hover:text-primary-500"
            >
              {{ $t('modules.view_repository') }}
            </a>
          </div>
        </div>
      </div>

      <div class="lg:col-span-7">
        <div
          class="rounded-lg border border-gray-200 bg-white p-4 lg:sticky lg:top-6"
        >
          <div class="space-y-3">
            <div v-if="!moduleData.installed" class="flex flex-wrap gap-3">
              <BaseButton
                v-if="installFinished && installSuccess"
                size="xl"
                variant="primary"
                disabled
                class="flex items-center justify-center text-base cursor-default"
              >
                <BaseIcon name="CheckIcon" class="mr-2" />
                {{ $t('modules.installed') }}
              </BaseButton>

              <BaseButton
                v-else-if="moduleData.latest_module_version"
                size="xl"
                variant="primary-outline"
                outline
                :loading="isInstalling"
                :disabled="isInstalling"
                class="flex items-center justify-center text-base"
                @click="installModule()"
              >
                <BaseIcon
                  v-if="!isInstalling"
                  name="ArrowDownTrayIcon"
                  class="mr-2"
                />
                {{ $t('modules.install') }}
              </BaseButton>
            </div>

            <div v-else class="flex flex-wrap gap-3">
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
                  <BaseIcon
                    v-if="!isDisabling"
                    name="NoSymbolIcon"
                    class="mr-2"
                  />
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

          <div
            v-if="isInstalling || installFinished"
            class="mt-6 border-t border-gray-200 pt-6"
          >
            <ul class="w-full p-0 list-none">
              <li
                v-for="step in installationSteps"
                :key="step.stepUrl"
                class="flex justify-between w-full py-3 border-b border-gray-200 border-solid last:border-b-0"
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

            <div v-if="installFinished" class="mt-6 space-y-4">
              <div
                class="rounded-md border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900"
              >
                {{ installSuccessMessage }}
              </div>

              <div
                v-if="
                  postInstall?.runnable_commands?.length ||
                  postInstall?.shell_commands?.length
                "
                class="space-y-2"
              >
                <p class="text-sm font-medium text-gray-900 m-0">
                  {{ $t('modules.post_install_commands') }}
                </p>
                <div class="flex flex-wrap items-center gap-3">
                  <BaseButton
                    v-if="postInstall?.runnable_commands?.length"
                    size="md"
                    variant="primary-outline"
                    :loading="isRunningPostInstall"
                    :disabled="isRunningPostInstall"
                    @click="runPostInstallCommands"
                  >
                    {{ $t('modules.run_post_install_commands') }}
                  </BaseButton>
                </div>
                <pre
                  class="text-xs bg-gray-900 text-gray-100 rounded-md p-3 overflow-auto"
                ><code>{{ fullPostInstallCommand }}</code></pre>

                <div v-if="postInstallRunOutput?.length" class="space-y-2">
                  <p class="text-xs text-gray-600 m-0">
                    {{ $t('modules.post_install_output') }}
                  </p>
                  <pre
                    class="text-xs bg-gray-900 text-gray-100 rounded-md p-3 overflow-auto"
                  ><code>{{ postInstallRunOutput.join('\n\n') }}</code></pre>
                </div>
              </div>

              <div v-if="postInstall?.notes?.length" class="space-y-1">
                <p class="text-sm font-medium text-gray-900 m-0">
                  {{ $t('modules.post_install_notes') }}
                </p>
                <ul class="list-disc pl-5 text-sm text-gray-700">
                  <li v-for="(n, i) in postInstall.notes" :key="i">
                    {{ n }}
                  </li>
                </ul>
              </div>

              <div class="flex flex-wrap gap-3 pt-2">
                <BaseButton
                  size="xl"
                  variant="primary"
                  class="text-base"
                  @click="goBackToModules"
                >
                  {{ $t('modules.go_back_to_modules') }}
                </BaseButton>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4">
          <div
            class="prose prose-sm max-w-none text-gray-500 text-sm"
            v-html="moduleData.long_description"
          />

          <div
            v-if="moduleData.tags && moduleData.tags.length"
            class="flex flex-wrap gap-2 mt-6"
          >
            <span
              v-for="(tag, i) in moduleData.tags"
              :key="i"
              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
            >
              {{ tag }}
            </span>
          </div>
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
import { useNotificationStore } from '@/scripts/stores/notification'

/**
 * @param {Record<string, unknown>|null|undefined} compatibility
 */
function formatCatalogCompatibilityRange(compatibility) {
  if (!compatibility || typeof compatibility !== 'object') {
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
const installFinished = ref(false)
const installSuccess = ref(false)
const installSuccessMessage = ref('')
const postInstall = ref(null)
const isRunningPostInstall = ref(false)
const postInstallRunOutput = ref([])
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
  },
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
  installFinished.value = false
  installSuccess.value = false
  installSuccessMessage.value = ''
  postInstall.value = null
  isRunningPostInstall.value = false
  postInstallRunOutput.value = []
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
        installFinished.value = true
        installSuccess.value = true
        installSuccessMessage.value = t('modules.install_completed', {
          name: moduleData.value.name || t('modules.title'),
        })
        postInstall.value = requestResponse.data?.post_install || null
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

      installFinished.value = true
      installSuccess.value = false
      installSuccessMessage.value = msg
      return false
    }
  }
}

function goBackToModules() {
  window.location.href = '/admin/modules'
}

const fullPostInstallCommand = computed(() => {
  const pieces = []
  const shell = postInstall.value?.shell_commands || []
  const runnable = postInstall.value?.runnable_commands || []

  if (shell.length) {
    pieces.push(...shell)
  }
  if (runnable.length) {
    pieces.push(...runnable.map((c) => `php artisan ${c}`))
  }

  return pieces.join(' && ')
})

async function runPostInstallCommands() {
  if (!postInstall.value?.runnable_commands?.length) {
    return
  }

  isRunningPostInstall.value = true
  postInstallRunOutput.value = []

  try {
    const res = await http.post(
      `/api/v1/modules/${moduleData.value.module_name}/post-install`,
      {
        catalog_kind: moduleData.value.catalog_kind || 'module',
      },
    )

    const rows = res.data?.output || []
    postInstallRunOutput.value = rows.map((r) => {
      const cmd = r.command || ''
      const exit = r.exit_code ?? ''
      const out = (r.output || '').trim()
      return `$ ${cmd}\n(exit ${exit})\n${out}`
    })
  } catch (e) {
    postInstallRunOutput.value = [String(e?.message || e || 'Failed')]
  } finally {
    isRunningPostInstall.value = false
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
    case 'download_too_large':
      return t('modules.download_too_large')
    case 'download_url_invalid':
      return t('modules.download_url_invalid')
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
        await moduleStore
          .disableModule(moduleData.value.module_name)
          .then((res) => {
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
