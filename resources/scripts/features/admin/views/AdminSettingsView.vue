<template>
  <BasePage>
    <BasePageHeader :title="$t('administration.settings.title')" class="mb-6">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem
          :title="$t('administration.settings.title')"
          to="#"
          active
        />
      </BaseBreadcrumb>
    </BasePageHeader>

    <div class="w-full mb-6 select-wrapper xl:hidden">
      <BaseMultiselect
        v-model="currentSetting"
        :options="menuItems"
        :can-deselect="false"
        value-prop="title"
        track-by="title"
        label="title"
        object
        @update:model-value="navigateToSetting"
      />
    </div>

    <div class="flex gap-8">
      <div class="hidden mt-1 xl:block min-w-[240px] sticky top-20 self-start">
        <BaseList>
          <BaseListItem
            v-for="(menuItem, index) in menuItems"
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
      </div>

      <div class="w-full overflow-visible">
        <RouterView />
      </div>
    </div>
  </BasePage>
</template>

<script setup lang="ts">
import { ref, computed, watchEffect } from 'vue'
import { useRoute, useRouter, RouterView } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { extensionItems, extensionRegistry } from '@/scripts/extensions/runtime'
import { isManaged } from '@/scripts/utils/managed'

interface SettingsMenuItem {
  title: string
  link: string
  icon: string
  // Owned by the hosting provider on a managed install.
  managedHidden?: boolean
}

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const currentSetting = ref<SettingsMenuItem | undefined>(undefined)

const allMenuItems = computed<SettingsMenuItem[]>(() => [
  {
    title: t('settings.mail.mail_config'),
    link: '/admin/administration/settings/mail-configuration',
    managedHidden: true,
    icon: 'EnvelopeIcon',
  },
  {
    title: t('settings.menu_title.pdf_generation'),
    link: '/admin/administration/settings/pdf-generation',
    managedHidden: true,
    icon: 'DocumentIcon',
  },
  {
    title: t('settings.menu_title.backup'),
    link: '/admin/administration/settings/backup',
    managedHidden: true,
    icon: 'CircleStackIcon',
  },
  {
    title: t('settings.menu_title.file_disk'),
    link: '/admin/administration/settings/file-disk',
    managedHidden: true,
    icon: 'FolderIcon',
  },
  {
    title: t('settings.menu_title.fonts'),
    link: '/admin/administration/settings/fonts',
    managedHidden: true,
    icon: 'LanguageIcon',
  },
  {
    title: t('settings.menu_title.currencies'),
    link: '/admin/administration/settings/currencies',
    icon: 'BanknotesIcon',
  },
  {
    title: t('settings.menu_title.update_app'),
    link: '/admin/administration/settings/update-app',
    managedHidden: true,
    icon: 'ArrowPathIcon',
  },
  {
    title: t('settings.menu_title.appearance'),
    link: '/admin/administration/settings/appearance',
    icon: 'PaintBrushIcon',
  },
  {
    title: t('mcp.admin.menu_title'),
    link: '/admin/administration/settings/mcp',
    icon: 'SparklesIcon',
  },
  ...extensionItems(extensionRegistry.adminSettingsNavigation.value).map((item) => ({
    title: t(item.title),
    link: router.resolve(item.to).fullPath,
    icon: item.icon,
  })),
])

const menuItems = computed<SettingsMenuItem[]>(() =>
  isManaged() ? allMenuItems.value.filter((item) => !item.managedHidden) : allMenuItems.value,
)

watchEffect(() => {
  // A managed install lands on the first setting it offers, whether the
  // admin menu pointed at a hidden one or the URL was typed.
  const onHiddenSetting = isManaged()
    && allMenuItems.value.some((item) => item.managedHidden && route.path.startsWith(item.link))

  if ((route.path === '/admin/administration/settings' || onHiddenSetting) && menuItems.value.length > 0) {
    router.replace(menuItems.value[0].link)
  }

  const item = menuItems.value.find((item) => {
    return route.path.indexOf(item.link) > -1
  })

  currentSetting.value = item
})

function hasActiveUrl(url: string): boolean {
  return route.path.indexOf(url) > -1
}

function navigateToSetting(setting: SettingsMenuItem): void {
  router.push(setting.link)
}
</script>
