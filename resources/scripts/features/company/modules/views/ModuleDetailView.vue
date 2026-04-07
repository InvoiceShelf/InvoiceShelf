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
        <BaseBreadcrumbItem :title="$t('modules.title')" to="/admin/modules" />
        <BaseBreadcrumbItem :title="moduleData.name" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <div class="lg:grid lg:grid-rows-1 lg:grid-cols-7 lg:gap-x-8 lg:gap-y-10 xl:gap-x-16 mt-6">
      <!-- Image Gallery -->
      <div class="lg:row-end-1 lg:col-span-4">
        <div class="flex flex-col-reverse">
          <!-- Thumbnails -->
          <div class="hidden mt-6 w-full max-w-2xl mx-auto sm:block lg:max-w-none">
            <div class="grid grid-cols-3 xl:grid-cols-4 gap-6" role="tablist">
              <button
                v-if="thumbnail && videoUrl"
                :class="[
                  'relative md:h-24 lg:h-36 rounded hover:bg-hover',
                  { 'outline-hidden ring-3 ring-offset-1 ring-primary-500': displayVideo },
                ]"
                type="button"
                @click="setDisplayVideo"
              >
                <span class="absolute inset-0 rounded-md overflow-hidden">
                  <img :src="thumbnail" alt="" class="w-full h-full object-center object-cover" />
                </span>
              </button>

              <button
                v-for="(screenshot, ssIdx) in displayImages"
                :key="ssIdx"
                :class="[
                  'relative md:h-24 lg:h-36 rounded hover:bg-hover',
                  { 'outline-hidden ring-3 ring-offset-1 ring-primary-500': displayImage === screenshot.url },
                ]"
                type="button"
                @click="setDisplayImage(screenshot.url)"
              >
                <span class="absolute inset-0 rounded-md overflow-hidden">
                  <img :src="screenshot.url" alt="" class="w-full h-full object-center object-cover" />
                </span>
              </button>
            </div>
          </div>

          <!-- Video -->
          <div v-if="displayVideo" class="aspect-w-4 aspect-h-3">
            <iframe
              :src="videoUrl ?? ''"
              class="sm:rounded-lg"
              frameborder="0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
            />
          </div>

          <!-- Main Image -->
          <div
            v-else
            class="aspect-w-4 aspect-h-3 rounded-lg bg-surface-tertiary overflow-hidden"
          >
            <img
              :src="displayImage ?? ''"
              alt="Module Images"
              class="w-full h-full object-center object-cover sm:rounded-lg"
            />
          </div>
        </div>
      </div>

      <!-- Details -->
      <div class="max-w-2xl mx-auto mt-10 lg:max-w-none lg:mt-0 lg:row-end-2 lg:row-span-2 lg:col-span-3 w-full">
        <!-- Rating -->
        <div class="flex items-center">
          <BaseRating :rating="averageRating" />
        </div>

        <!-- Name & Version -->
        <div class="flex flex-col-reverse">
          <div class="mt-4">
            <h1 class="text-2xl font-extrabold tracking-tight text-heading sm:text-3xl">
              {{ moduleData.name }}
            </h1>
            <p v-if="moduleData.latest_module_version" class="text-sm text-muted mt-2">
              {{ $t('modules.version') }}
              {{ moduleVersion }} ({{ $t('modules.last_updated') }}
              {{ updatedAt }})
            </p>
          </div>
        </div>

        <!-- Description -->
        <div
          class="prose prose-sm max-w-none text-muted text-sm my-10"
          v-html="moduleData.long_description"
        />

        <!-- Action Buttons -->
        <div v-if="!moduleData.purchased">
          <a
            :href="buyLink"
            target="_blank"
            class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2"
          >
            <BaseButton size="xl" class="items-center flex justify-center text-base mt-10">
              <BaseIcon name="ShoppingCartIcon" class="mr-2" />
              {{ $t('modules.buy_now') }}
            </BaseButton>
          </a>
        </div>
        <div v-else>
          <!-- Not installed yet -->
          <div v-if="!moduleData.installed" class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <BaseButton
              v-if="moduleData.latest_module_version"
              size="xl"
              variant="primary-outline"
              :loading="isInstalling"
              :disabled="isInstalling"
              class="mr-4 flex items-center justify-center text-base"
              @click="handleInstall"
            >
              <BaseIcon v-if="!isInstalling" name="ArrowDownTrayIcon" class="mr-2" />
              {{ $t('modules.install') }}
            </BaseButton>
          </div>

          <!-- Already installed -->
          <div v-else-if="isModuleInstalled" class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <BaseButton
              v-if="moduleData.update_available"
              variant="primary"
              size="xl"
              :loading="isInstalling"
              :disabled="isInstalling"
              class="mr-4 flex items-center justify-center text-base"
              @click="handleInstall"
            >
              {{ $t('modules.update_to') }}
              <span class="ml-2">{{ moduleData.latest_module_version }}</span>
            </BaseButton>

            <BaseButton
              v-if="moduleData.enabled"
              variant="danger"
              size="xl"
              :loading="isDisabling"
              :disabled="isDisabling"
              class="mr-4 flex items-center justify-center text-base"
              @click="handleDisable"
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
              class="mr-4 flex items-center justify-center text-base"
              @click="handleEnable"
            >
              <BaseIcon v-if="!isEnabling" name="CheckIcon" class="mr-2" />
              {{ $t('modules.enable') }}
            </BaseButton>
          </div>
        </div>

        <!-- Highlights -->
        <div v-if="moduleData.highlights" class="border-t border-line-default mt-10 pt-10">
          <h3 class="text-sm font-medium text-heading">
            {{ $t('modules.what_you_get') }}
          </h3>
          <div class="mt-4 prose prose-sm max-w-none text-muted" v-html="moduleData.highlights" />
        </div>

        <!-- Links -->
        <div v-if="moduleData.links?.length" class="border-t border-line-default mt-10 pt-10">
          <div
            v-for="(link, key) in moduleData.links"
            :key="key"
            class="mb-4 last:mb-0 flex"
          >
            <BaseIcon :name="(link as ModuleLinkItem).icon ?? ''" class="mr-4" />
            <a :href="(link as ModuleLinkItem).link" class="text-primary-500" target="_blank">
              {{ (link as ModuleLinkItem).label }}
            </a>
          </div>
        </div>

        <!-- Installation Steps -->
        <div v-if="isInstalling" class="border-t border-line-default mt-10 pt-10">
          <ul class="w-full p-0 list-none">
            <li
              v-for="step in installationSteps"
              :key="step.translationKey"
              class="flex justify-between w-full py-3 border-b border-line-default border-solid last:border-b-0"
            >
              <p class="m-0 text-sm leading-8">{{ $t(step.translationKey) }}</p>
              <span
                :class="stepStatusClass(step)"
                class="block py-1 text-sm text-center uppercase rounded-full"
                style="width: 88px"
              >
                {{ stepStatusLabel(step) }}
              </span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Tabs: Reviews, FAQ, License -->
      <div class="w-full max-w-2xl mx-auto mt-16 lg:max-w-none lg:mt-0 lg:col-span-4">
        <!-- Simple tab implementation -->
        <div class="-mb-px flex space-x-8 border-b border-line-default">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            :class="[
              activeTab === tab.key
                ? 'border-primary-600 text-primary-600'
                : 'border-transparent text-body hover:text-heading hover:border-line-strong',
              'whitespace-nowrap py-6 border-b-2 font-medium text-sm',
            ]"
            @click="activeTab = tab.key"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- Reviews -->
        <div v-if="activeTab === 'reviews'" class="-mb-10">
          <div v-if="moduleData.reviews?.length">
            <div
              v-for="(review, reviewIdx) in moduleData.reviews"
              :key="reviewIdx"
              class="flex text-sm text-muted space-x-4"
            >
              <div class="flex-none py-10">
                <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-surface-secondary">
                  <span class="text-lg font-medium leading-none text-white uppercase">
                    {{ review.user?.[0] ?? '?' }}
                  </span>
                </span>
              </div>
              <div :class="[reviewIdx === 0 ? '' : 'border-t border-line-default', 'py-10']">
                <h3 class="font-medium text-heading">{{ review.user }}</h3>
                <p>{{ formatDate(review.created_at) }}</p>
                <div class="flex items-center mt-4">
                  <BaseRating :rating="review.rating" />
                </div>
                <div class="mt-4 prose prose-sm max-w-none text-muted" v-html="review.comment" />
              </div>
            </div>
          </div>
          <div v-else class="flex w-full items-center justify-center">
            <p class="text-muted mt-10 text-sm">{{ $t('modules.no_reviews_found') }}</p>
          </div>
        </div>

        <!-- FAQ -->
        <dl v-if="activeTab === 'faq'" class="text-sm text-muted">
          <template v-for="faq in moduleData.faq" :key="faq.question">
            <dt class="mt-10 font-medium text-heading">{{ faq.question }}</dt>
            <dd class="mt-2 prose prose-sm max-w-none text-muted">
              <p>{{ faq.answer }}</p>
            </dd>
          </template>
        </dl>

        <!-- License -->
        <div v-if="activeTab === 'license'" class="pt-10">
          <div class="prose prose-sm max-w-none text-muted" v-html="moduleData.license" />
        </div>
      </div>
    </div>

    <!-- Other Modules -->
    <div v-if="otherModules?.length" class="mt-24 sm:mt-32 lg:max-w-none">
      <div class="flex items-center justify-between space-x-4">
        <h2 class="text-lg font-medium text-heading">{{ $t('modules.other_modules') }}</h2>
        <a
          href="/admin/modules"
          class="whitespace-nowrap text-sm font-medium text-primary-600 hover:text-primary-500"
        >
          {{ $t('modules.view_all') }}
          <span aria-hidden="true"> &rarr;</span>
        </a>
      </div>
      <div class="mt-6 grid grid-cols-1 gap-x-8 gap-y-8 sm:grid-cols-2 sm:gap-y-10 lg:grid-cols-4">
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
const displayImage = ref<string | null>('')
const displayVideo = ref<boolean>(false)
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

  if (videoUrl.value) {
    setDisplayVideo()
  } else {
    displayImage.value = moduleData.value?.cover ?? null
  }

  isFetchingInitialData.value = false
}

async function handleInstall(): Promise<void> {
  if (!moduleData.value) return

  installationSteps.length = 0
  isInstalling.value = true

  const success = await moduleStore.installModule(
    moduleData.value.module_name,
    moduleData.value.latest_module_version,
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
  displayImage.value = url
}

function setDisplayVideo(): void {
  displayVideo.value = true
  displayImage.value = null
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
