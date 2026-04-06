<template>
  <div>
    <label class="flex text-heading font-medium text-sm mb-2">
      {{ $t('general.select_template') }}
      <span class="text-sm text-red-500"> *</span>
    </label>
    <BaseButton
      type="button"
      class="flex justify-center w-full text-sm lg:w-auto hover:bg-surface-muted"
      variant="gray"
      @click="openTemplateModal"
    >
      <template #right="slotProps">
        <BaseIcon name="PencilIcon" :class="slotProps.class" />
      </template>
      {{ templateName }}
    </BaseButton>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModalStore } from '@v2/stores/modal.store'
import type { DocumentFormData } from './use-document-calculations'

interface Props {
  store: Record<string, unknown> & {
    templates: Array<{ name: string; path?: string }>
  }
  storeProp: string
  isMarkAsDefault?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isMarkAsDefault: false,
})

const { t } = useI18n()
const modalStore = useModalStore()

const formData = computed<DocumentFormData>(() => {
  return props.store[props.storeProp] as DocumentFormData
})

const templateName = computed<string>(() => {
  return formData.value.template_name ?? ''
})

function openTemplateModal(): void {
  let markAsDefaultDescription = ''
  if (props.storeProp === 'newEstimate') {
    markAsDefaultDescription = t('estimates.mark_as_default_estimate_template_description')
  } else if (props.storeProp === 'newInvoice') {
    markAsDefaultDescription = t('invoices.mark_as_default_invoice_template_description')
  }

  modalStore.openModal({
    title: t('general.choose_template'),
    componentName: 'SelectTemplate',
    data: {
      templates: props.store.templates,
      store: props.store,
      storeProp: props.storeProp,
      isMarkAsDefault: props.isMarkAsDefault,
      markAsDefaultDescription,
    },
  })
}
</script>
