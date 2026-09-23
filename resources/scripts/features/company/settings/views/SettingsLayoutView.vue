<script setup lang="ts">
import { ref, computed, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '../../../../stores/global.store'
import { useUserStore } from '../../../../stores/user.store'
import { extensionItems, extensionRegistry } from '@/scripts/extensions/runtime'

interface SettingMenuItem {
  title: string
  link: string
  icon: string
}

interface DropdownMenuItem extends SettingMenuItem {
  title: string
}

const { t } = useI18n()
const globalStore = useGlobalStore()
const userStore = useUserStore()
const route = useRoute()
const router = useRouter()

const showDangerZone = computed<boolean>(() => {
  return userStore.currentUser?.is_owner === true
})

const currentSetting = ref<DropdownMenuItem | undefined>(undefined)

const dropdownMenuItems = computed<DropdownMenuItem[]>(() => {
  const items = (globalStore.settingMenu as SettingMenuItem[]).map((item) => ({
    ...item,
    title: t(item.title),
  }))

  items.push(
    ...extensionItems(extensionRegistry.companySettingsNavigation.value).map((item) => ({
      title: t(item.title),
      link: router.resolve(item.to).fullPath,
      icon: item.icon,
    })),
  )

  if (showDangerZone.value) {
    items.push({
      title: t('settings.company_info.danger_zone'),
      link: '/admin/settings/danger-zone',
      icon: 'ExclamationTriangleIcon',
    })
  }

  return items
})

const sidebarMenuItems = computed<DropdownMenuItem[]>(() =>
  dropdownMenuItems.value.filter(
    (item) => item.link !== '/admin/settings/danger-zone',
  ),
)

watchEffect(() => {
  if (route.path === '/admin/settings') {
    // Redirect to first available setting menu item, or account settings as fallback
    const firstItem = globalStore.settingMenu?.[0]
    router.push(firstItem?.link ?? '/admin/settings/account-settings')
  }

  const item = dropdownMenuItems.value.find((item) => item.link === route.path)
  currentSetting.value = item
})

function hasActiveUrl(url: string): boolean {
  return route.path.indexOf(url) > -1
}

function navigateToSetting(setting: DropdownMenuItem): void {
  router.push(setting.link)
}

</script>

<template>
  <BasePage>
    <BasePageHeader :title="$t('settings.setting', 1)" class="mb-6">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem
          :title="$t('settings.setting', 2)"
          to="/admin/settings"
          active
        />
      </BaseBreadcrumb>
    </BasePageHeader>

    <div class="w-full mb-6 select-wrapper xl:hidden">
      <BaseMultiselect
        v-model="currentSetting"
        :options="dropdownMenuItems"
        :can-deselect="false"
        value-prop="title"
        track-by="title"
        label="title"
        object
        :aria-label="$t('navigation.settings')"
        @update:model-value="navigateToSetting"
      />
    </div>

    <div class="flex gap-8">
      <div class="hidden mt-1 xl:block min-w-[240px] sticky top-20 self-start">
        <BaseList>
          <BaseListItem
            v-for="(menuItem, index) in sidebarMenuItems"
            :key="index"
            :title="menuItem.title"
            :to="menuItem.link"
            :active="hasActiveUrl(menuItem.link)"
            :index="index"
            class="py-3"
          >
            <template #icon>
              <BaseIcon :name="menuItem.icon" />
            </template>
          </BaseListItem>
        </BaseList>

        <router-link
          v-if="showDangerZone"
          to="/admin/settings/danger-zone"
          :class="[
            'cursor-pointer px-3 py-2 mt-1 text-sm font-medium leading-5 flex items-center rounded-lg transition-colors',
            hasActiveUrl('/admin/settings/danger-zone')
              ? 'text-danger bg-hover-strong font-semibold'
              : 'text-danger hover:bg-hover-strong',
          ]"
        >
          <span class="mr-3">
            <BaseIcon name="ExclamationTriangleIcon" />
          </span>
          <span>{{ $t('settings.company_info.danger_zone') }}</span>
        </router-link>
      </div>

      <div class="w-full overflow-visible">
        <RouterView />
      </div>
    </div>
  </BasePage>
</template>
