<template>
  <BaseDropdown :label="$t('general.actions_for', { name: row.name })">
    <template #activator>
      <span class="inline-flex items-center justify-center rounded-lg w-9 h-9 text-muted hover:bg-hover-strong hover:text-heading">
        <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
      </span>
    </template>

    <BaseDropdownItem :to="`/admin/administration/companies/${row.id}/edit`">
      <BaseIcon
        name="PencilIcon"
        class="w-5 h-5 me-3 text-subtle group-hover:text-muted"
      />
      {{ $t('general.edit') }}
    </BaseDropdownItem>
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
