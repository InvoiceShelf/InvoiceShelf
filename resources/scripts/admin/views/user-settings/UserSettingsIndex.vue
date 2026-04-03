<template>
  <BasePage>
    <BasePageHeader
      :title="$t('settings.account_settings.account_settings')"
      class="mb-6"
    >
      <BaseBreadcrumb>
        <BaseBreadcrumbItem
          :title="$t('general.home')"
          to="/admin/dashboard"
        />
        <BaseBreadcrumbItem
          :title="$t('settings.account_settings.account_settings')"
          to="/admin/user-settings/general"
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
import { ref, watchEffect, computed } from 'vue'
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
    title: t('settings.account_settings.general'),
    link: '/admin/user-settings/general',
    icon: 'UserIcon',
  },
  {
    title: t('settings.account_settings.profile_picture'),
    link: '/admin/user-settings/profile-photo',
    icon: 'PhotoIcon',
  },
  {
    title: t('settings.account_settings.security'),
    link: '/admin/user-settings/security',
    icon: 'LockClosedIcon',
  },
])

watchEffect(() => {
  if (route.path === '/admin/user-settings') {
    router.push('/admin/user-settings/general')
  }

  const item = menuItems.value.find((item) => {
    return item.link === route.path
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
