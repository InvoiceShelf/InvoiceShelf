<template>
  <NumberCustomizer
    type="creditnote"
    :type-store="creditNoteStore"
    default-series="CN"
  />
</template>

<script setup>
import { useCreditNoteStore } from '@/scripts/admin/stores/credit-note'
import NumberCustomizer from '../NumberCustomizer.vue'

const creditNoteStore = useCreditNoteStore()
</script>
