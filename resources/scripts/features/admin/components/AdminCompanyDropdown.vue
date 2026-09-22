<template>
  <BaseDropdown>
    <template #activator>
      <span class="inline-flex items-center justify-center rounded-lg w-9 h-9 text-muted hover:bg-hover-strong hover:text-heading">
        <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
      </span>
    </template>

    <router-link :to="`/admin/administration/companies/${row.id}/edit`">
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-subtle group-hover:text-muted"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>
  </BaseDropdown>
</template>

<script setup lang="ts">
import type { Company } from '../../../types/domain/company'

interface Props {
  row: Company
  table: { refresh: () => void } | null
  loadData: (() => void) | null
}

defineProps<Props>()
</script>
