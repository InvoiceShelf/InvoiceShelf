<template>
  <BaseInputGroup :label="label" :error="error" :required="required"
    ><slot
  /></BaseInputGroup>
</template>
<script setup lang="ts">
import { computed, inject } from 'vue'
import { purchaseErrors } from '../composables/use-purchase-form'
const props = defineProps<{
  label: string
  name?: string
  required?: boolean
}>()
const errors = inject(purchaseErrors, undefined)
const error = computed(() =>
  props.name
    ? Object.entries(errors?.value || {}).find(
        ([key]) => key === props.name || key.startsWith(props.name + '.'),
      )?.[1]?.[0]
    : undefined,
)
</script>
