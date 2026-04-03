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
        @update:modelValue="navigateToSetting"
      />
    </div>

    <div class="flex">
      <div class="hidden mt-1 xl:block min-w-[240px]">
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

      <div class="w-full overflow-hidden">
        <RouterView />
      </div>
    </div>
  </BasePage>
</template>

<script setup>
import { ref, computed, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import BaseList from '@/scripts/components/list/BaseList.vue'
import BaseListItem from '@/scripts/components/list/BaseListItem.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

let currentSetting = ref({})

const menuItems = computed(() => [
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

function hasActiveUrl(url) {
  return route.path.indexOf(url) > -1
}

function navigateToSetting(setting) {
  return router.push(setting.link)
}
</script>
