<script setup lang="ts">
import { computed } from 'vue'
import { extensionRegistry, extensionItems } from './runtime'
import type {
  InvoiceActionContext,
  InvoiceCreateContext,
  InvoiceDetailContext,
  RichEditorContext,
} from './types'

type ExtensionSlotContext =
  | RichEditorContext
  | InvoiceActionContext
  | InvoiceDetailContext
  | InvoiceCreateContext

const props = defineProps<{
  name:
    | 'header-actions'
    | 'company-layout-overlays'
    | 'rich-editor-toolbar-actions'
    | 'invoice-actions'
    | 'invoice-detail-actions'
    | 'invoice-create-actions'
    | 'invoice-create-sections'
    | 'invoice-detail-panels'
  context?: ExtensionSlotContext
}>()

const contributions = computed(() => {
  const items = {
    'header-actions': extensionRegistry.headerActions.value,
    'company-layout-overlays': extensionRegistry.companyLayoutOverlays.value,
    'rich-editor-toolbar-actions': extensionRegistry.richEditorToolbarActions.value,
    'invoice-actions': extensionRegistry.invoiceActions.value,
    'invoice-detail-actions': extensionRegistry.invoiceDetailActions.value,
    'invoice-create-actions': extensionRegistry.invoiceCreateActions.value,
    'invoice-create-sections': extensionRegistry.invoiceCreateSections.value,
    'invoice-detail-panels': extensionRegistry.invoiceDetailPanels.value,
  }[props.name]

  return extensionItems(items)
})

function componentProps(props_: Record<string, unknown> | undefined): Record<string, unknown> {
  return props.context === undefined
    ? (props_ ?? {})
    : { ...props_, context: props.context }
}
</script>

<template>
  <component
    :is="contribution.component"
    v-for="contribution in contributions"
    :key="contribution.id"
    v-bind="componentProps(contribution.props)"
  />
</template>
