<template>
  <div class="py-1">
    <!-- Administration mode -->
    <template v-if="userStore.currentUser?.is_super_admin">
      <button
        type="button"
        :class="rowClass(companyStore.isAdminMode)"
        @click="emit('admin')"
      >
        <span
          class="flex items-center justify-center w-8 h-8 rounded-lg shrink-0 bg-primary-50 text-primary-600"
        >
          <BaseIcon name="ShieldCheckIcon" class="w-4.5 h-4.5" />
        </span>
        <span class="flex-1 min-w-0 text-sm font-medium truncate">
          {{ $t('navigation.administration') }}
        </span>
        <BaseIcon
          v-if="companyStore.isAdminMode"
          name="CheckIcon"
          class="w-4 h-4 shrink-0 text-primary-600"
        />
      </button>
      <div class="mx-3 my-1.5 border-t border-line-light" />
    </template>

    <p class="px-3 pt-1.5 pb-1 text-xs font-medium text-muted">
      {{ $t('company_switcher.label') }}
    </p>

    <div
      v-if="companyStore.companies.length < 1"
      class="flex items-center gap-2 px-3 py-4 text-sm text-muted"
    >
      <BaseIcon name="ExclamationCircleIcon" class="w-4 h-4" />
      {{ $t('company_switcher.no_results_found') }}
    </div>

    <button
      v-for="company in companyStore.companies"
      :key="company.id"
      type="button"
      :class="rowClass(isSelected(company))"
      @click="emit('select', company)"
    >
      <span
        class="
          flex items-center justify-center w-8 h-8 overflow-hidden text-sm font-semibold
          rounded-lg shrink-0 bg-surface-muted text-body
        "
      >
        {{ initial(company.name) }}
      </span>
      <span class="flex flex-col flex-1 min-w-0 text-start">
        <span class="text-sm font-medium truncate">{{ company.name }}</span>
        <span v-if="company.user_role" class="text-xs truncate text-muted">
          {{ company.user_role }}
        </span>
      </span>
      <BaseIcon
        v-if="isSelected(company)"
        name="CheckIcon"
        class="w-4 h-4 shrink-0 text-primary-600"
      />
    </button>

    <template v-if="userStore.currentUser?.is_owner">
      <div class="mx-3 my-1.5 border-t border-line-light" />
      <button type="button" :class="rowClass(false)" @click="emit('add')">
        <span
          class="flex items-center justify-center w-8 h-8 border border-dashed rounded-lg shrink-0 border-line-strong text-muted"
        >
          <BaseIcon name="PlusIcon" class="w-4 h-4" />
        </span>
        <span class="text-sm font-medium">
          {{ $t('company_switcher.add_new_company') }}
        </span>
      </button>
    </template>
  </div>
</template>

<script setup lang="ts">
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useUserStore } from '@/scripts/stores/user.store'
import type { Company } from '@/scripts/types/domain/company'

const emit = defineEmits<{
  (e: 'select', company: Company): void
  (e: 'admin'): void
  (e: 'add'): void
}>()

const companyStore = useCompanyStore()
const userStore = useUserStore()

function isSelected(company: Company): boolean {
  return (
    !companyStore.isAdminMode &&
    !!companyStore.selectedCompany &&
    companyStore.selectedCompany.id === company.id
  )
}

function rowClass(active: boolean): string {
  return [
    'flex items-center w-full gap-3 px-3 py-2 rounded-lg text-start transition-colors',
    active ? 'bg-primary-50 text-heading' : 'text-body hover:bg-hover',
  ].join(' ')
}

function initial(name: string): string {
  return name ? name.trim().charAt(0).toUpperCase() : ''
}
</script>
