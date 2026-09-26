<script setup lang="ts">
import { computed } from 'vue'
import {
  applySelection,
  groupAbilities,
  hasViewAndMore,
  lockedBy,
  withDependencies,
} from './abilities'
import type { AbilityDefinition, Selection } from './abilities'

/**
 * The permission grid of a role: the catalogue grouped by what it is about,
 * with shortcuts for all, read-only or none, per group and overall. Ticking
 * an ability ticks what it depends on and locks those while it stays ticked.
 * The value is a list of ability keys; keys outside the catalogue are kept.
 */
const props = withDefaults(
  defineProps<{
    abilities: AbilityDefinition[]
    modelValue: string[]
    readonly?: boolean
    error?: string | null
  }>(),
  { readonly: false, error: null }
)

const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()

const groups = computed(() => groupAbilities(props.abilities))
const selected = computed(() => new Set(props.modelValue))
const locked = computed(() => lockedBy(props.modelValue, props.abilities))

function toggle(ability: AbilityDefinition, checked: boolean): void {
  if (checked) {
    emit('update:modelValue', withDependencies([...props.modelValue, ability.ability], props.abilities))
  } else if (!locked.value.has(ability.ability)) {
    emit('update:modelValue', props.modelValue.filter((key) => key !== ability.ability))
  }
}

function apply(items: AbilityDefinition[], selection: Selection): void {
  emit('update:modelValue', applySelection(props.modelValue, items, selection, props.abilities))
}

const linkClass =
  'rounded-sm text-primary-600 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus'
</script>

<template>
  <div>
    <div class="flex justify-between">
      <h2 class="text-sm not-italic font-medium text-heading px-4 md:px-8 py-1.5">
        {{ $t('settings.roles.permission', 2) }}
        <span v-if="!readonly" class="text-sm text-danger" aria-hidden="true"> *</span>
      </h2>
      <div v-if="!readonly" class="text-sm not-italic font-medium text-subtle px-4 md:px-8 py-1.5">
        <button type="button" :class="linkClass" @click="apply(abilities, 'all')">
          {{ $t('settings.roles.select_all') }}
        </button>
        <span aria-hidden="true"> / </span>
        <button type="button" :class="linkClass" @click="apply(abilities, 'view')">
          {{ $t('settings.roles.view_only') }}
        </button>
        <span aria-hidden="true"> / </span>
        <button type="button" :class="linkClass" @click="apply(abilities, 'none')">
          {{ $t('settings.roles.none') }}
        </button>
      </div>
    </div>

    <div class="border-t border-line-default py-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 px-8 sm:px-8">
        <fieldset v-for="group in groups" :key="group.label" class="flex flex-col space-y-1">
          <legend
            class="flex w-full flex-wrap items-baseline justify-between gap-x-2 pb-1 mb-2 text-sm border-b text-muted border-line-default"
          >
            <span>{{ group.label }}</span>
            <span v-if="!readonly" class="flex shrink-0 gap-2 text-xs">
              <button
                type="button"
                :class="linkClass"
                :aria-label="$t('settings.roles.group_all', { group: group.label })"
                @click="apply(group.items, 'all')"
              >
                {{ $t('settings.roles.all') }}
              </button>
              <button
                v-if="hasViewAndMore(group.items)"
                type="button"
                :class="linkClass"
                :aria-label="$t('settings.roles.group_view', { group: group.label })"
                @click="apply(group.items, 'view')"
              >
                {{ $t('settings.roles.view') }}
              </button>
              <button
                type="button"
                :class="linkClass"
                :aria-label="$t('settings.roles.group_none', { group: group.label })"
                @click="apply(group.items, 'none')"
              >
                {{ $t('settings.roles.none') }}
              </button>
            </span>
          </legend>
          <div v-for="ability in group.items" :key="ability.ability" class="flex">
            <BaseCheckbox
              :model-value="selected.has(ability.ability)"
              variant="primary"
              :disabled="readonly || locked.has(ability.ability)"
              :label="ability.name"
              @update:model-value="(checked: boolean | unknown[]) => toggle(ability, checked === true)"
            />
          </div>
        </fieldset>
        <span v-if="error" class="block mt-0.5 text-sm text-danger">{{ error }}</span>
      </div>
    </div>
  </div>
</template>
