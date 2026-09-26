<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAdminStore } from '../stores/admin.store'
import type { AdminMembership, CompanyRoleOption } from '../stores/admin.store'

/**
 * The companies a user belongs to, a row each: the company and the role held
 * there. A company the user owns is locked to its Owner role and cannot be
 * removed. Roles are loaded per company, since presets and a company's own
 * roles differ between companies.
 */
const props = defineProps<{
  modelValue: AdminMembership[]
  companies: Array<{ id: number; name: string }>
  lockedCompanyIds?: number[]
}>()

const emit = defineEmits<{ 'update:modelValue': [value: AdminMembership[]] }>()

const { t } = useI18n()
const adminStore = useAdminStore()
const rolesByCompany = ref<Record<number, CompanyRoleOption[]>>({})

const locked = computed(() => new Set(props.lockedCompanyIds ?? []))

async function ensureRoles(companyId: number | null): Promise<void> {
  if (companyId === null || rolesByCompany.value[companyId]) return
  rolesByCompany.value = { ...rolesByCompany.value, [companyId]: await adminStore.fetchCompanyRoles(companyId) }
}

watch(
  () => props.modelValue.map((row) => row.id),
  (ids) => ids.forEach((id) => void ensureRoles(id)),
  { immediate: true }
)

function companyOptions(index: number): Array<{ id: number; name: string }> {
  const taken = new Set(props.modelValue.filter((_, i) => i !== index).map((row) => row.id))
  return props.companies.filter((company) => !taken.has(company.id))
}

function companyName(id: number | null): string {
  return props.companies.find((company) => company.id === id)?.name ?? ''
}

function update(index: number, changes: Partial<AdminMembership>): void {
  emit(
    'update:modelValue',
    props.modelValue.map((row, i) => (i === index ? { ...row, ...changes } : row))
  )
}

function pickCompany(index: number, id: number | null): void {
  // A role belongs to one company, so a new company starts without one.
  update(index, { id, role: null })
}

function add(): void {
  emit('update:modelValue', [...props.modelValue, { id: null, role: null }])
}

function remove(index: number): void {
  emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
}
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="(row, index) in modelValue"
      :key="index"
      class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
    >
      <BaseInputGroup :label="index === 0 ? t('administration.users.company') : ''">
        <BaseMultiselect
          :model-value="row.id"
          :options="companyOptions(index)"
          value-prop="id"
          label="name"
          track-by="name"
          searchable
          :disabled="row.id !== null && locked.has(row.id)"
          :placeholder="t('administration.users.select_company')"
          :aria-label="t('administration.users.company')"
          @update:model-value="(value: unknown) => pickCompany(index, (value as number | null) ?? null)"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="index === 0 ? t('administration.users.role') : ''">
        <BaseMultiselect
          :model-value="row.role"
          :options="row.id !== null ? (rolesByCompany[row.id] ?? []) : []"
          value-prop="name"
          label="title"
          track-by="title"
          :disabled="row.id === null || locked.has(row.id)"
          :placeholder="t('administration.users.select_role')"
          :aria-label="t('administration.users.role')"
          @update:model-value="(value: unknown) => update(index, { role: (value as string | null) ?? null })"
        />
      </BaseInputGroup>

      <div class="flex h-10 items-center">
        <p v-if="row.id !== null && locked.has(row.id)" class="text-xs text-muted sm:max-w-40">
          {{ t('administration.users.owner_locked') }}
        </p>
        <BaseButton
          v-else
          variant="white"
          size="sm"
          type="button"
          :aria-label="t('administration.users.remove_company', { company: companyName(row.id) || t('administration.users.company') })"
          @click="remove(index)"
        >
          <BaseIcon name="TrashIcon" class="h-4 w-4 text-danger" aria-hidden="true" />
        </BaseButton>
      </div>
    </div>

    <BaseButton
      variant="primary-outline"
      size="sm"
      type="button"
      :disabled="modelValue.length >= companies.length"
      @click="add"
    >
      <template #left="slotProps">
        <BaseIcon name="PlusIcon" :class="slotProps.class" />
      </template>
      {{ t('administration.users.add_company') }}
    </BaseButton>
  </div>
</template>
