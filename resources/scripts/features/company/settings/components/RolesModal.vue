<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { roleService } from '@/scripts/api/services/role.service'
import type { CreateRolePayload } from '@/scripts/api/services/role.service'
import type { Ability } from '@/scripts/types/domain/role'

interface AbilityItem {
  name: string
  ability: string
  disabled: boolean
  depends_on?: string[]
  model?: string
}

interface AbilitiesList {
  [group: string]: AbilityItem[]
}

interface RoleForm {
  id: number | null
  name: string
  abilities: AbilityItem[]
}

const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(false)
const isEdit = ref<boolean>(false)

const currentRole = ref<RoleForm>({
  id: null,
  name: '',
  abilities: [],
})

const abilitiesList = ref<AbilitiesList>({})

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'RolesModal'
)

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 3 }),
      minLength(3)
    ),
  },
  abilities: {
    required: helpers.withMessage(
      t('validation.at_least_one_ability'),
      required
    ),
  },
}))

const v$ = useVuelidate(rules, currentRole)

async function setInitialData(): Promise<void> {
  isFetchingInitialData.value = true

  const abilitiesRes = await roleService.getAbilities()
  if (abilitiesRes.abilities) {
    const grouped: AbilitiesList = {}
    abilitiesRes.abilities.forEach((a: Record<string, unknown>) => {
      // Extract model name from PHP class path (e.g., "App\Models\Customer" → "Customer")
      const modelPath = (a.model as string) ?? ''
      const modelName = modelPath
        ? modelPath.substring(modelPath.lastIndexOf('\\') + 1)
        : 'Common'

      if (!grouped[modelName]) grouped[modelName] = []
      grouped[modelName].push({
        name: a.name as string,
        ability: a.ability as string,
        disabled: false,
        depends_on: (a.depends_on as string[]) ?? [],
      } as AbilityItem)
    })
    abilitiesList.value = grouped
  }

  if (modalStore.data && typeof modalStore.data === 'number') {
    isEdit.value = true
    const response = await roleService.get(modalStore.data)
    if (response.data) {
      currentRole.value = {
        id: response.data.id,
        name: response.data.name,
        abilities: [],
      }

      // Match role's abilities with the full ability objects from abilitiesList
      const roleAbilities = (response.data.abilities ?? []) as Array<Record<string, unknown>>
      roleAbilities.forEach((ra) => {
        Object.keys(abilitiesList.value).forEach((group) => {
          abilitiesList.value[group].forEach((_p) => {
            if (_p.ability === ra.name) {
              currentRole.value.abilities.push(_p)
            }
          })
        })
      })

      // Set disabled state for dependent abilities
      currentRole.value.abilities.forEach((ab) => {
        ab.depends_on?.forEach((_d) => {
          Object.keys(abilitiesList.value).forEach((group) => {
            abilitiesList.value[group].forEach((_a) => {
              if (_d === _a.ability) {
                _a.disabled = true
              }
            })
          })
        })
      })
    }
  } else {
    isEdit.value = false
    currentRole.value = { id: null, name: '', abilities: [] }
  }

  isFetchingInitialData.value = false
}

async function submitRoleData(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true
  try {
    const payload: CreateRolePayload = {
      name: currentRole.value.name,
      abilities: currentRole.value.abilities.map((a) => ({
        ability: a.ability,
      })),
    }

    if (isEdit.value && currentRole.value.id) {
      await roleService.update(currentRole.value.id, payload)
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.roles.updated_message',
      })
    } else {
      await roleService.create(payload)
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.roles.created_message',
      })
    }

    isSaving.value = false
    if (modalStore.refreshData) {
      modalStore.refreshData()
    }
    closeRolesModal()
  } catch {
    isSaving.value = false
  }
}

function onUpdateAbility(currentAbility: AbilityItem): void {
  const fd = currentRole.value.abilities.find(
    (_abl) => _abl.ability === currentAbility.ability
  )

  if (!fd && currentAbility.depends_on?.length) {
    enableAbilities(currentAbility)
    return
  }

  currentAbility.depends_on?.forEach((_d) => {
    Object.keys(abilitiesList.value).forEach((group) => {
      abilitiesList.value[group].forEach((_a) => {
        if (_d === _a.ability) {
          _a.disabled = true
          const found = currentRole.value.abilities.find(
            (_af) => _af.ability === _d
          )
          if (!found) {
            currentRole.value.abilities.push(_a)
          }
        }
      })
    })
  })
}

type Selection = 'all' | 'view' | 'none'

/** Read-only abilities: seeing records, reports and the dashboard. */
function isViewAbility(item: AbilityItem): boolean {
  return item.ability.startsWith('view-') || item.ability === 'dashboard'
}

function allAbilities(): AbilityItem[] {
  return Object.values(abilitiesList.value).flat()
}

function hasViewAndMore(items: AbilityItem[]): boolean {
  return items.some(isViewAbility) && items.some((item) => !isViewAbility(item))
}

/**
 * Give a set of abilities (one group, or all of them) everything, only the
 * read-only ones, or nothing, leaving the rest of the role as it is. The
 * shortcut for building a role without ticking boxes one by one.
 */
function applySelection(items: AbilityItem[], selection: Selection): void {
  const kept = currentRole.value.abilities.filter((a) => !items.includes(a))
  const added =
    selection === 'all' ? items : selection === 'view' ? items.filter(isViewAbility) : []

  currentRole.value.abilities = [...kept, ...added]
  settleDependencies()
}

/**
 * An ability pulls in the ones it depends on, which then cannot be unticked
 * while it is on. After a bulk change, add what the chosen abilities need and
 * lock exactly those.
 */
function settleDependencies(): void {
  const all = allAbilities()
  const byName = new Map(all.map((a) => [a.ability, a]))
  const selected = new Set(currentRole.value.abilities)
  const locked = new Set<string>()

  const queue = [...selected]
  while (queue.length) {
    const item = queue.pop() as AbilityItem
    item.depends_on?.forEach((name) => {
      locked.add(name)
      const dependency = byName.get(name)
      if (dependency && !selected.has(dependency)) {
        selected.add(dependency)
        queue.push(dependency)
      }
    })
  }

  currentRole.value.abilities = all.filter((a) => selected.has(a))
  all.forEach((a) => {
    a.disabled = locked.has(a.ability)
  })
}

function enableAbilities(ability: AbilityItem): void {
  ability.depends_on?.forEach((_d) => {
    Object.keys(abilitiesList.value).forEach((group) => {
      abilitiesList.value[group].forEach((_a) => {
        const found = currentRole.value.abilities.find((_r) =>
          _r.depends_on?.includes(_a.ability)
        )
        if (_d === _a.ability && !found) {
          _a.disabled = false
        }
      })
    })
  })
}

function closeRolesModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    currentRole.value = { id: null, name: '', abilities: [] }
    isEdit.value = false

    Object.keys(abilitiesList.value).forEach((group) => {
      abilitiesList.value[group].forEach((_a) => {
        _a.disabled = false
      })
    })

    v$.value.$reset()
  }, 300)
}
</script>

<template>
  <BaseModal
    :show="modalActive"
    closable
    @close="closeRolesModal"
    @open="setInitialData"
  >
    <template #header>
      {{ modalStore.title }}
    </template>

    <form @submit.prevent="submitRoleData">
      <div class="px-4 md:px-8 py-4 md:py-6">
        <BaseInputGroup
          :label="$t('settings.roles.name')"
          class="mt-3"
          :error="v$.name.$error && v$.name.$errors[0].$message"
          required
          :content-loading="isFetchingInitialData"
        >
          <BaseInput
            v-model="currentRole.name"
            :invalid="v$.name.$error"
            type="text"
            :content-loading="isFetchingInitialData"
            @input="v$.name.$touch()"
          />
        </BaseInputGroup>
      </div>

      <div class="flex justify-between">
        <h2
          class="text-sm not-italic font-medium text-heading px-4 md:px-8 py-1.5"
        >
          {{ $t('settings.roles.permission', 2) }}
          <span class="text-sm text-danger" aria-hidden="true"> *</span>
        </h2>
        <div
          class="text-sm not-italic font-medium text-subtle px-4 md:px-8 py-1.5"
        >
          <button
            type="button"
            class="rounded-sm text-primary-600 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
            @click="applySelection(allAbilities(), 'all')"
          >
            {{ $t('settings.roles.select_all') }}
          </button>
          <span aria-hidden="true"> / </span>
          <button
            type="button"
            class="rounded-sm text-primary-600 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
            @click="applySelection(allAbilities(), 'view')"
          >
            {{ $t('settings.roles.view_only') }}
          </button>
          <span aria-hidden="true"> / </span>
          <button
            type="button"
            class="rounded-sm text-primary-600 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
            @click="applySelection(allAbilities(), 'none')"
          >
            {{ $t('settings.roles.none') }}
          </button>
        </div>
      </div>

      <div class="border-t border-line-default py-3">
        <div
          class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 px-8 sm:px-8"
        >
          <fieldset
            v-for="(abilityGroup, gIndex) in abilitiesList"
            :key="gIndex"
            class="flex flex-col space-y-1"
          >
            <legend
              class="flex w-full items-baseline justify-between gap-2 pb-1 mb-2 text-sm border-b text-muted border-line-default"
            >
              <span>{{ gIndex }}</span>
              <span class="flex shrink-0 gap-2 text-xs">
                <button
                  type="button"
                  class="rounded-sm text-primary-600 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
                  :aria-label="$t('settings.roles.group_all', { group: gIndex })"
                  @click="applySelection(abilityGroup, 'all')"
                >
                  {{ $t('settings.roles.all') }}
                </button>
                <button
                  v-if="hasViewAndMore(abilityGroup)"
                  type="button"
                  class="rounded-sm text-primary-600 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
                  :aria-label="$t('settings.roles.group_view', { group: gIndex })"
                  @click="applySelection(abilityGroup, 'view')"
                >
                  {{ $t('settings.roles.view') }}
                </button>
                <button
                  type="button"
                  class="rounded-sm text-primary-600 hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
                  :aria-label="$t('settings.roles.group_none', { group: gIndex })"
                  @click="applySelection(abilityGroup, 'none')"
                >
                  {{ $t('settings.roles.none') }}
                </button>
              </span>
            </legend>
            <div
              v-for="(ability, index) in abilityGroup"
              :key="index"
              class="flex"
            >
              <BaseCheckbox
                v-model="currentRole.abilities"
                :set-initial-value="true"
                variant="primary"
                :disabled="ability.disabled"
                :label="ability.name"
                :value="ability"
                @update:model-value="onUpdateAbility(ability)"
              />
            </div>
          </fieldset>
          <span
            v-if="v$.abilities.$error"
            class="block mt-0.5 text-sm text-danger"
          >
            {{ v$.abilities.$errors[0].$message }}
          </span>
        </div>
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-solid border-line-default"
      >
        <BaseButton
          class="me-3 text-sm"
          variant="primary-outline"
          type="button"
          @click="closeRolesModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ isEdit ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
