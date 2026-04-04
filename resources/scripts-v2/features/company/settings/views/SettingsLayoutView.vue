<script setup lang="ts">
import { ref, computed, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '../../../../stores/global.store'

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
const route = useRoute()
const router = useRouter()

const currentSetting = ref<DropdownMenuItem | undefined>(undefined)

const dropdownMenuItems = computed<DropdownMenuItem[]>(() => {
  return (globalStore.settingMenu as SettingMenuItem[]).map((item) => ({
    ...item,
    title: t(item.title),
  }))
})

watchEffect(() => {
  if (route.path === '/admin/settings') {
    router.push('/admin/settings/company-info')
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
          to="/admin/settings/company-info"
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
        @update:model-value="navigateToSetting"
      />
    </div>

    <div class="flex gap-8">
      <div class="hidden mt-1 xl:block min-w-[240px] sticky top-20 self-start">
        <BaseList>
          <BaseListItem
            v-for="(menuItem, index) in globalStore.settingMenu"
            :key="index"
            :title="$t(menuItem.title)"
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
