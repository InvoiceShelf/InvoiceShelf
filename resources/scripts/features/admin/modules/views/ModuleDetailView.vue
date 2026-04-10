<template>
  <div v-if="isFetchingInitialData" class="p-8">
    <div class="animate-pulse space-y-4">
      <div class="h-8 bg-surface-tertiary rounded w-1/3" />
      <div class="h-64 bg-surface-tertiary rounded" />
    </div>
  </div>

  <BasePage v-else-if="moduleData" class="bg-surface">
    <BasePageHeader :title="moduleData.name">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('modules.title')" to="/admin/administration/modules" />
        <BaseBreadcrumbItem :title="moduleData.name" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <!-- Hero: Info + Action -->
    <div class="mt-8 lg:grid lg:grid-cols-3 lg:gap-12">
      <!-- Module Info (2/3) -->
      <div class="lg:col-span-2">
        <div class="flex items-start gap-5">
          <!-- Icon / Cover thumbnail -->
          <div class="shrink-0 h-16 w-16 rounded-xl overflow-hidden bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center">
            <img v-if="moduleData.cover" :src="moduleData.cover" alt="" class="h-full w-full object-cover" />
            <BaseIcon v-else name="PuzzlePieceIcon" class="h-8 w-8 text-primary-400" />
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-3 flex-wrap">
              <h1 class="text-2xl font-bold tracking-tight text-heading">
                {{ moduleData.name }}
              </h1>
              <span class="rounded-full bg-primary-50 px-3 py-0.5 text-xs font-semibold uppercase tracking-wide text-primary-600">
                {{ moduleData.access_tier }}
              </span>
            </div>

            <div class="mt-1.5 flex items-center gap-4 text-sm text-muted flex-wrap">
              <span v-if="moduleData.author_name" class="flex items-center gap-1.5">
                <img
                  v-if="moduleData.author_avatar"
                  :src="moduleData.author_avatar"
                  alt=""
                  class="h-4 w-4 rounded-full"
                />
                {{ moduleData.author_name }}
              </span>
              <span v-if="moduleData.latest_module_version">
                v{{ moduleVersion }}
              </span>
              <span v-if="updatedAt">
                {{ updatedAt }}
              </span>
            </div>
          </div>
        </div>

        <p class="mt-6 text-sm text-muted leading-relaxed">
          {{ moduleData.long_description }}
        </p>
      </div>

      <!-- Action Card (1/3) -->
      <div class="mt-6 lg:mt-0">
        <div class="rounded-xl border border-line-default bg-surface-secondary p-6">
          <!-- Not purchased -->
          <template v-if="!moduleData.purchased">
            <a :href="buyLink" target="_blank">
              <BaseButton size="lg" class="w-full flex items-center justify-center">
                <BaseIcon name="ShoppingCartIcon" class="mr-2" />
                {{ $t('modules.buy_now') }}
              </BaseButton>
            </a>
          </template>

          <!-- Purchased, not installed -->
          <template v-else-if="!moduleData.installed">
            <BaseButton
              v-if="moduleData.latest_module_version"
              variant="primary"
              :loading="isInstalling"
              :disabled="isInstalling"
              class="w-full flex items-center justify-center"
              @click="handleInstall"
            >
              <BaseIcon v-if="!isInstalling" name="ArrowDownTrayIcon" class="mr-2 h-4 w-4" />
              {{ $t('modules.install') }} v{{ moduleData.latest_module_version }}
            </BaseButton>
          </template>

          <!-- Installed -->
          <template v-else-if="isModuleInstalled">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-1.5">
                <BaseIcon name="CheckCircleIcon" class="h-4 w-4 text-green-500" />
                <span class="text-sm font-medium text-heading">{{ $t('modules.installed') }}</span>
              </div>
              <span class="text-xs text-muted">v{{ moduleData.installed_module_version }}</span>
            </div>

            <div class="flex gap-2">
              <BaseButton
                v-if="moduleData.update_available"
                variant="primary"
                :loading="isInstalling"
                :disabled="isInstalling"
                class="flex-1 flex items-center justify-center"
                @click="handleInstall"
              >
                <BaseIcon v-if="!isInstalling" name="ArrowPathIcon" class="mr-1.5 h-4 w-4" />
                {{ $t('modules.update_to') }} {{ moduleData.latest_module_version }}
              </BaseButton>

              <BaseButton
                v-if="moduleData.enabled"
                variant="danger"
                :loading="isDisabling"
                :disabled="isDisabling"
                :class="moduleData.update_available ? '' : 'flex-1'"
                class="flex items-center justify-center"
                @click="handleDisable"
              >
                <BaseIcon v-if="!isDisabling" name="NoSymbolIcon" class="h-4 w-4" :class="{ 'mr-1.5': !moduleData.update_available }" />
                <span v-if="!moduleData.update_available">{{ $t('modules.disable') }}</span>
              </BaseButton>
              <BaseButton
                v-else
                variant="primary-outline"
                :loading="isEnabling"
                :disabled="isEnabling"
                class="flex-1 flex items-center justify-center"
                @click="handleEnable"
              >
                <BaseIcon v-if="!isEnabling" name="CheckIcon" class="mr-1.5 h-4 w-4" />
                {{ $t('modules.enable') }}
              </BaseButton>
            </div>
          </template>

          <!-- Installation Steps -->
          <ul v-if="isInstalling && installationSteps.length" class="mt-4 space-y-2">
            <li
              v-for="step in installationSteps"
              :key="step.translationKey"
              class="flex items-center justify-between text-sm py-1"
            >
              <span class="text-muted">{{ $t(step.translationKey) }}</span>
              <span
                :class="stepStatusClass(step)"
                class="px-2 py-0.5 text-xs rounded-full font-medium"
              >
                {{ stepStatusLabel(step) }}
              </span>
            </li>
          </ul>

          <!-- Links -->
          <div v-if="moduleData.links?.length" class="mt-5 pt-4 border-t border-line-light space-y-2">
            <a
              v-for="(link, key) in moduleData.links"
              :key="key"
              :href="(link as ModuleLinkItem).link"
              target="_blank"
              class="flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700"
            >
              <BaseIcon :name="(link as ModuleLinkItem).icon ?? 'LinkIcon'" class="h-4 w-4" />
              {{ (link as ModuleLinkItem).label }}
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Highlights -->
    <div v-if="moduleData.highlights?.length" class="mt-12 pt-8 border-t border-line-light">
      <h3 class="text-sm font-semibold text-heading mb-4">
        {{ $t('modules.what_you_get') }}
      </h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-3">
        <div
          v-for="highlight in moduleData.highlights"
          :key="highlight"
          class="flex items-start gap-2.5 text-sm text-muted"
        >
          <BaseIcon name="CheckIcon" class="h-4 w-4 text-green-500 shrink-0 mt-0.5" />
          {{ highlight }}
        </div>
      </div>
    </div>

    <!-- Screenshots Gallery -->
    <div v-if="displayImages.length" class="mt-12 pt-8 border-t border-line-light">
      <h3 class="text-sm font-semibold text-heading mb-5">
        {{ $t('modules.screenshots') }}
      </h3>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Video thumbnail -->
        <button
          v-if="thumbnail && videoUrl"
          class="relative rounded-lg overflow-hidden bg-surface-tertiary aspect-video group"
          type="button"
          @click="setDisplayVideo"
        >
          <img :src="thumbnail" alt="" class="w-full h-full object-cover" />
          <div class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/30 transition-colors">
            <BaseIcon name="PlayIcon" class="h-12 w-12 text-white" />
          </div>
        </button>

        <!-- Screenshots -->
        <button
          v-for="(screenshot, ssIdx) in displayImages"
          :key="ssIdx"
          class="rounded-lg overflow-hidden bg-surface-tertiary aspect-video"
          type="button"
          @click="setDisplayImage(screenshot.url)"
        >
          <img :src="screenshot.url" alt="" class="w-full h-full object-cover" />
        </button>
      </div>

      <!-- Lightbox-style expanded view -->
      <div
        v-if="displayVideo || expandedImage"
        class="mt-4 rounded-lg overflow-hidden bg-surface-tertiary"
      >
        <div v-if="displayVideo" class="aspect-video">
          <iframe
            :src="videoUrl ?? ''"
            class="w-full h-full"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
          />
        </div>
        <div v-else-if="expandedImage" class="relative">
          <img :src="expandedImage" alt="" class="w-full" />
          <button
            class="absolute top-3 right-3 rounded-full bg-black/50 hover:bg-black/70 p-1.5 text-white transition-colors"
            @click="expandedImage = null"
          >
            <BaseIcon name="XMarkIcon" class="h-5 w-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Tabs: Reviews, FAQ, License -->
    <div class="mt-12 pt-8 border-t border-line-light">
      <div class="-mb-px flex space-x-8 border-b border-line-default">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          :class="[
            activeTab === tab.key
              ? 'border-primary-600 text-primary-600'
              : 'border-transparent text-body hover:text-heading hover:border-line-strong',
            'whitespace-nowrap py-4 border-b-2 font-medium text-sm',
          ]"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Reviews -->
      <div v-if="activeTab === 'reviews'" class="py-6">
        <div v-if="moduleData.reviews?.length">
          <div
            v-for="(review, reviewIdx) in moduleData.reviews"
            :key="reviewIdx"
            class="flex text-sm text-muted space-x-4"
          >
            <div class="flex-none py-4">
              <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-surface-secondary">
                <span class="text-sm font-medium leading-none text-muted uppercase">
                  {{ review.user?.[0] ?? '?' }}
                </span>
              </span>
            </div>
            <div :class="[reviewIdx === 0 ? '' : 'border-t border-line-default', 'py-4']">
              <h3 class="font-medium text-heading">{{ review.user }}</h3>
              <p>{{ formatDate(review.created_at) }}</p>
              <div class="flex items-center mt-2">
                <BaseRating :rating="review.rating" />
              </div>
              <div class="mt-2 prose prose-sm max-w-none text-muted" v-html="review.comment" />
            </div>
          </div>
        </div>
        <p v-else class="text-muted text-sm">{{ $t('modules.no_reviews_found') }}</p>
      </div>

      <!-- FAQ -->
      <dl v-if="activeTab === 'faq'" class="py-6 text-sm text-muted space-y-6">
        <div v-for="faq in moduleData.faq" :key="faq.question">
          <dt class="font-medium text-heading">{{ faq.question }}</dt>
          <dd class="mt-1">{{ faq.answer }}</dd>
        </div>
      </dl>

      <!-- License -->
      <div v-if="activeTab === 'license'" class="py-6">
        <div class="prose prose-sm max-w-none text-muted" v-html="moduleData.license" />
      </div>
    </div>

    <!-- Other Modules -->
    <div v-if="otherModules?.length" class="mt-16">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-medium text-heading">{{ $t('modules.other_modules') }}</h2>
        <a
          href="/admin/administration/modules"
          class="whitespace-nowrap text-sm font-medium text-primary-600 hover:text-primary-500"
        >
          {{ $t('modules.view_all') }}
          <span aria-hidden="true"> &rarr;</span>
        </a>
      </div>
      <div class="mt-6 grid grid-cols-1 gap-x-8 gap-y-8 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="(other, moduleIdx) in otherModules" :key="moduleIdx">
          <ModuleCard :data="other" />
        </div>
      </div>
    </div>

    <div class="p-6" />
  </BasePage>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useModuleStore } from '../store'
import type { InstallationStep } from '../store'
import ModuleCard from '../components/ModuleCard.vue'
import { useDialogStore } from '../../../../stores/dialog.store'
import type { Module, ModuleLink } from '../../../../types/domain/module'

interface ModuleLinkItem {
  icon: string
  link: string
  label: string
}

interface TabItem {
  key: string
  label: string
}

const moduleStore = useModuleStore()
const dialogStore = useDialogStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const isFetchingInitialData = ref<boolean>(true)
const isInstalling = ref<boolean>(false)
const isEnabling = ref<boolean>(false)
const isDisabling = ref<boolean>(false)
const displayVideo = ref<boolean>(false)
const expandedImage = ref<string | null>(null)
const thumbnail = ref<string | null>(null)
const videoUrl = ref<string | null>(null)
const activeTab = ref<string>('reviews')

const installationSteps = reactive<InstallationStep[]>([])

const tabs = computed<TabItem[]>(() => [
  { key: 'reviews', label: t('modules.customer_reviews') },
  { key: 'faq', label: t('modules.faq') },
  { key: 'license', label: t('modules.license') },
])

const moduleData = computed<Module | undefined>(() => {
  return moduleStore.currentModule?.data
})

const otherModules = computed<Module[] | undefined>(() => {
  return moduleStore.currentModule?.meta?.modules
})

const averageRating = computed<number>(() => {
  return parseInt(String(moduleData.value?.average_rating ?? 0), 10)
})

const isModuleInstalled = computed<boolean>(() => {
  return !!(moduleData.value?.installed && moduleData.value?.latest_module_version)
})

const moduleVersion = computed<string>(() => {
  return moduleData.value?.installed_module_version ?? moduleData.value?.latest_module_version ?? ''
})

const updatedAt = computed<string>(() => {
  const date =
    moduleData.value?.installed_module_version_updated_at ??
    moduleData.value?.latest_module_version_updated_at
  return date ? formatDate(date) : ''
})

const displayImages = computed<Array<{ url: string }>>(() => {
  const images: Array<{ url: string }> = []
  if (moduleData.value?.cover) {
    images.push({ url: moduleData.value.cover })
  }
  if (moduleData.value?.screenshots) {
    moduleData.value.screenshots.forEach((s) => images.push({ url: s.url }))
  }
  return images
})

const buyLink = computed<string>(() => {
  return `/modules/${moduleData.value?.slug ?? ''}`
})

watch(() => route.params.slug, () => {
  loadData()
})

onMounted(() => {
  loadData()
})

async function loadData(): Promise<void> {
  const slug = route.params.slug as string
  if (!slug) return

  isFetchingInitialData.value = true
  await moduleStore.fetchModule(slug)

  videoUrl.value = moduleData.value?.video_link ?? null
  thumbnail.value = moduleData.value?.video_thumbnail ?? null
  displayVideo.value = false
  expandedImage.value = null

  isFetchingInitialData.value = false
}

async function handleInstall(): Promise<void> {
  if (!moduleData.value) return

  installationSteps.length = 0
  isInstalling.value = true

  const success = await moduleStore.installModule(
    {
      slug: moduleData.value.slug,
      module_name: moduleData.value.module_name,
      version: moduleData.value.latest_module_version,
      checksum_sha256: moduleData.value.latest_module_checksum_sha256,
    },
    (step) => {
      const existing = installationSteps.find(
        (s) => s.translationKey === step.translationKey,
      )
      if (existing) {
        Object.assign(existing, step)
      } else {
        installationSteps.push({ ...step })
      }
    },
  )

  isInstalling.value = false

  if (success) {
    setTimeout(() => location.reload(), 1500)
  }
}

function handleDisable(): void {
  if (!moduleData.value) return

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
    .then(async (res: boolean) => {
      if (res) {
        isDisabling.value = true
        const response = await moduleStore.disableModule(moduleData.value!.module_name)
        isDisabling.value = false

        if (response.success) {
          setTimeout(() => location.reload(), 1500)
        }
      }
    })
}

async function handleEnable(): Promise<void> {
  if (!moduleData.value) return

  isEnabling.value = true
  const res = await moduleStore.enableModule(moduleData.value.module_name)
  isEnabling.value = false

  if (res.success) {
    setTimeout(() => location.reload(), 1500)
  }
}

function setDisplayImage(url: string): void {
  displayVideo.value = false
  expandedImage.value = url
}

function setDisplayVideo(): void {
  displayVideo.value = true
  expandedImage.value = null
}

function formatDate(dateStr: string): string {
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

function stepStatusClass(step: InstallationStep): string {
  const status = stepStatusLabel(step)
  switch (status) {
    case 'pending':
      return 'text-primary-800 bg-surface-muted'
    case 'finished':
      return 'text-teal-500 bg-teal-100'
    case 'running':
      return 'text-blue-400 bg-blue-100'
    default:
      return 'text-danger bg-red-200'
  }
}

function stepStatusLabel(step: InstallationStep): string {
  if (step.started && step.completed) return 'finished'
  if (step.started && !step.completed) return 'running'
  return 'pending'
}
</script>
