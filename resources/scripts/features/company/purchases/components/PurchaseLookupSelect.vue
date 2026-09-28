<template>
  <BaseMultiselect
    ref="select"
    v-model="model"
    :options="choices"
    value-prop="id"
    label="name"
    searchable
    :mode="multiple ? 'multiple' : 'single'"
    :disabled="disabled"
    :can-deselect="!required"
    v-bind="$attrs"
  >
    <template v-if="!disabled && context.allowed.value[kind]" #action>
      <BaseSelectAction @click="create"
        ><BaseIcon name="PlusIcon" class="me-2 h-4 w-4" />{{
          $t(labels[kind])
        }}</BaseSelectAction
      >
    </template>
  </BaseMultiselect>
</template>
<script setup lang="ts">
import { computed, ref, nextTick } from 'vue'
import {
  usePurchaseLookups,
  type PurchaseLookupKind,
} from '../composables/use-purchase-lookups'
defineOptions({ inheritAttrs: false })
const props = defineProps<{
  kind: PurchaseLookupKind
  options: Array<{ id: number; name: string }>
  multiple?: boolean
  disabled?: boolean
  required?: boolean
}>()
const model = defineModel<number | number[] | null>()
const context = usePurchaseLookups()
const select = ref<{ close: () => void; focus: () => void } | null>(null)
const labels = {
  category: 'purchases.new_category',
  method: 'purchases.new_payment_method',
  tax: 'purchases.new_purchase_tax',
}
const choices = computed(() =>
  Array.from(
    new Map(
      [...props.options, ...context.records.value[props.kind]].map((option) => [
        option.id,
        option,
      ]),
    ).values(),
  ),
)
async function create() {
  select.value?.close()
  await nextTick()
  select.value?.focus()
  context.open(props.kind, (record) => {
    model.value = props.multiple
      ? [
          ...new Set([
            ...(Array.isArray(model.value) ? model.value : []),
            record.id,
          ]),
        ]
      : record.id
  })
}
</script>
