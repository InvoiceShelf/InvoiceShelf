<template>
  <BasePage>
    <BasePageHeader :title="$t('settings.account_settings.account_settings')">
      <template #actions>
        <router-link to="/admin/settings">
          <BaseButton variant="primary-outline">
            <template #left="slotProps">
              <BaseIcon name="CogIcon" :class="slotProps.class" />
            </template>
            {{ $t('navigation.settings') }}
          </BaseButton>
        </router-link>
      </template>
    </BasePageHeader>

    <BaseCard container-class="px-4 py-5 sm:px-8 sm:py-2">
      <BaseTabGroup>
        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.account_settings.general')"
        >
          <GeneralTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.account_settings.profile_picture')"
        >
          <ProfilePhotoTab />
        </BaseTab>

        <BaseTab
          tab-panel-container="py-4 mt-px"
          :title="$t('settings.account_settings.security')"
        >
          <SecurityTab />
        </BaseTab>
      </BaseTabGroup>
    </BaseCard>
  </BasePage>
</template>

<script setup>
import GeneralTab from './GeneralTab.vue'
import ProfilePhotoTab from './ProfilePhotoTab.vue'
import SecurityTab from './SecurityTab.vue'
</script>
