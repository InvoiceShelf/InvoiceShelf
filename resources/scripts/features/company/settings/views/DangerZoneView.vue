<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useModalStore } from '../../../../stores/modal.store'
import DeleteCompanyModal from '../components/DeleteCompanyModal.vue'

const { t } = useI18n()
const modalStore = useModalStore()

function removeCompany(): void {
  modalStore.openModal({
    title: t('settings.company_info.are_you_absolutely_sure'),
    componentName: 'DeleteCompanyModal',
    size: 'sm',
  })
}
</script>

<template>
  <BaseSettingCard
    :title="$t('settings.company_info.danger_zone')"
    :description="$t('settings.company_info.delete_company_description')"
  >
    <div class="mt-6">
      <BaseButton
        variant="danger"
        type="button"
        @click="removeCompany"
      >
        <template #left="slotProps">
          <BaseIcon name="TrashIcon" :class="slotProps.class" />
        </template>
        {{ $t('settings.company_info.delete_company') }}
      </BaseButton>
    </div>
  </BaseSettingCard>

  <DeleteCompanyModal />
</template>
