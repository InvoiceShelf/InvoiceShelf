<script setup lang="ts">
import { computed } from 'vue'
import { entitySlotKey, extensionItems, extensionRegistry } from './runtime'
import type { EntitySlot } from './runtime'
import type {
  EntityContext,
  EntityFormContext,
  ExtensionEntity,
  RichEditorContext,
} from './types'

/**
 * Renders the module contributions for one slot. Shell slots are named;
 * record slots also take the entity and the area within it, so one
 * component serves every record type. Renders nothing when no module
 * contributes, so a host view looks the same without modules.
 */
const props = defineProps<{
  name:
    | 'header-actions'
    | 'company-layout-overlays'
    | 'rich-editor-toolbar-actions'
    | 'entity'
  entity?: ExtensionEntity
  area?: EntitySlot
  context?: RichEditorContext | EntityContext | EntityFormContext
}>()

const contributions = computed(() => {
  if (props.name === 'entity') {
    if (!props.entity || !props.area) {
      return []
    }

    return extensionItems(extensionRegistry.entityComponents.value[entitySlotKey(props.area, props.entity)] ?? [])
  }

  const items = {
    'header-actions': extensionRegistry.headerActions.value,
    'company-layout-overlays': extensionRegistry.companyLayoutOverlays.value,
    'rich-editor-toolbar-actions': extensionRegistry.richEditorToolbarActions.value,
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
