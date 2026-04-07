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

interface SettingsMenuItem {
  title: string
  link: string
  icon: string
}

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const currentSetting = ref<SettingsMenuItem | undefined>(undefined)

const menuItems = computed<SettingsMenuItem[]>(() => [
  {
    title: t('settings.mail.mail_config'),
    link: '/admin/administration/settings/mail-configuration',
    icon: 'EnvelopeIcon',
  },
  {
    title: t('settings.menu_title.pdf_generation'),
    link: '/admin/administration/settings/pdf-generation',
    icon: 'DocumentIcon',
  },
  {
    title: t('settings.menu_title.backup'),
    link: '/admin/administration/settings/backup',
    icon: 'CircleStackIcon',
  },
  {
    title: t('settings.menu_title.file_disk'),
    link: '/admin/administration/settings/file-disk',
    icon: 'FolderIcon',
  },
  {
    title: t('settings.menu_title.fonts'),
    link: '/admin/administration/settings/fonts',
    icon: 'LanguageIcon',
  },
  {
    title: t('settings.menu_title.update_app'),
    link: '/admin/administration/settings/update-app',
    icon: 'ArrowPathIcon',
  },
])

watchEffect(() => {
  if (route.path === '/admin/administration/settings') {
    router.push('/admin/administration/settings/mail-configuration')
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
