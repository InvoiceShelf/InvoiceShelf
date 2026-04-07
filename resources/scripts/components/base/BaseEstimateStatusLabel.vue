<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EstimateStatus } from '@/scripts/types/domain'

interface Props {
  status?: string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const { t } = useI18n()

const labelStatus = computed<string>(() => {
  switch (props.status) {
    case EstimateStatus.DRAFT:
    case 'DRAFT':
      return t('estimates.draft')
    case EstimateStatus.SENT:
    case 'SENT':
      return t('estimates.sent')
    case EstimateStatus.VIEWED:
    case 'VIEWED':
      return t('estimates.viewed')
    case EstimateStatus.EXPIRED:
    case 'EXPIRED':
      return t('estimates.expired')
    case EstimateStatus.ACCEPTED:
    case 'ACCEPTED':
      return t('estimates.accepted')
    case EstimateStatus.REJECTED:
    case 'REJECTED':
      return t('estimates.rejected')
    default:
      return props.status
  }
})
</script>

<template>
  {{ labelStatus }}
</template>
