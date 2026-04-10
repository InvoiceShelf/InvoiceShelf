<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useGlobalStore } from '@/scripts/stores/global.store'

const { t } = useI18n()
const globalStore = useGlobalStore()

const showSidebarGroupLabels = computed<boolean>({
  get: () => globalStore.globalSettings?.show_sidebar_group_labels === 'YES',
  set: async (enabled) => {
    await globalStore.updateGlobalSettings({
      data: {
        settings: {
          show_sidebar_group_labels: enabled ? 'YES' : 'NO',
        },
      },
      message: t('general.setting_updated'),
    })
  },
})
</script>

<template>
  <BaseSettingCard
    :title="$t('settings.appearance.title')"
    :description="$t('settings.appearance.description')"
  >
    <div class="mt-14">
      <BaseSwitchSection
        v-model="showSidebarGroupLabels"
        :title="$t('settings.appearance.sidebar_group_labels')"
        :description="$t('settings.appearance.sidebar_group_labels_desc')"
      />
    </div>
  </BaseSettingCard>
</template>
