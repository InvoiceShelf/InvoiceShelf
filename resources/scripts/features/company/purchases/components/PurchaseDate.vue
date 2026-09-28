<template>{{ formatted }}</template>
<script setup lang="ts">
import { computed } from 'vue'
import flatpickr from 'flatpickr'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { parseDate } from '@/scripts/utils/format-date'
const props = defineProps<{ value?: string | null }>()
const company = useCompanyStore()
const formatted = computed(() => {
  const date = props.value ? parseDate(props.value) : null
  return date
    ? flatpickr.formatDate(
        date,
        company.selectedCompanySettings?.carbon_date_format || 'Y-m-d',
      )
    : '—'
})
</script>
