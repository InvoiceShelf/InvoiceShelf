<template>
  <div class="pt-6 mt-5 border-t border-solid lg:pt-8 md:pt-4 border-gray-200">
    <!-- Basic Info -->
    <BaseHeading>
      {{ $t('installers.basic_info') }}
    </BaseHeading>

    <BaseDescriptionList>
      <BaseDescriptionListItem
        :content-loading="contentLoading"
        :label="$t('installers.display_name')"
        :value="selectedViewInstaller?.name"
      />

      <BaseDescriptionListItem
        :content-loading="contentLoading"
        :label="$t('installers.primary_contact_name')"
        :value="selectedViewInstaller?.contact_name"
      />
      <BaseDescriptionListItem
        :content-loading="contentLoading"
        :label="$t('installers.email')"
        :value="selectedViewInstaller?.email"
      />
    </BaseDescriptionList>

    <BaseDescriptionList class="mt-5">
      <BaseDescriptionListItem
        :content-loading="contentLoading"
        :label="$t('wizard.currency')"
        :value="
          selectedViewInstaller?.currency
            ? `${selectedViewInstaller?.currency?.code} (${selectedViewInstaller?.currency?.symbol})`
            : ''
        "
      />

      <BaseDescriptionListItem
        :content-loading="contentLoading"
        :label="$t('installers.phone_number')"
        :value="selectedViewInstaller?.phone"
      />
      <BaseDescriptionListItem
        :content-loading="contentLoading"
        :label="$t('installers.website')"
        :value="selectedViewInstaller?.website"
      />
    </BaseDescriptionList>

    <!-- Address -->
    <BaseHeading
      v-if="selectedViewInstaller.billing || selectedViewInstaller.shipping"
      class="mt-8"
    >
      {{ $t('installers.address') }}
    </BaseHeading>

    <BaseDescriptionList class="mt-5">
      <BaseDescriptionListItem
        v-if="selectedViewInstaller.billing"
        :content-loading="contentLoading"
        :label="$t('installers.billing_address')"
      >
        <BaseInstallerAddressDisplay :address="selectedViewInstaller.billing" />
      </BaseDescriptionListItem>

      <BaseDescriptionListItem
        v-if="selectedViewInstaller.shipping"
        :content-loading="contentLoading"
        :label="$t('installers.shipping_address')"
      >
        <BaseInstallerAddressDisplay :address="selectedViewInstaller.shipping" />
      </BaseDescriptionListItem>
    </BaseDescriptionList>

    <!-- Custom Fields -->
    <BaseHeading v-if="installerCustomFields.length > 0" class="mt-8">
      {{ $t('settings.custom_fields.title') }}
    </BaseHeading>

    <BaseDescriptionList class="mt-5">
      <BaseDescriptionListItem
        v-for="(field, index) in installerCustomFields"
        :key="index"
        :content-loading="contentLoading"
        :label="field.custom_field.label"
      >
        <p
          v-if="field.type === 'Switch'"
          class="text-sm font-bold leading-5 text-black non-italic"
        >
          <span v-if="field.default_answer === 1"> Yes </span>
          <span v-else> No </span>
        </p>
        <p v-else class="text-sm font-bold leading-5 text-black non-italic">
          {{ field.default_answer }}
        </p>
      </BaseDescriptionListItem>
    </BaseDescriptionList>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useInstallerStore } from '@/scripts/admin/stores/installer'

const installerStore = useInstallerStore()

const selectedViewInstaller = computed(() => installerStore.selectedViewInstaller)

const contentLoading = computed(() => installerStore.isFetchingViewData)

const installerCustomFields = computed(() => {
  if (selectedViewInstaller?.value?.fields) {
    return selectedViewInstaller?.value?.fields
  }
  return []
})
</script>
