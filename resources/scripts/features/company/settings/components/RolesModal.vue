<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { roleService } from '@/scripts/api/services/role.service'
import type { CreateRolePayload } from '@/scripts/api/services/role.service'
import AbilityMatrix from '@/scripts/features/shared/roles/AbilityMatrix.vue'
import type { AbilityDefinition } from '@/scripts/features/shared/roles/abilities'

/**
 * Creates, edits or (for a role preset, which the super administrator owns)
 * only shows a company role. The modal data is the role id to edit, or
 * `{ id, readonly: true }` to show one.
 */
interface RoleForm {
  id: number | null
  name: string
  abilities: string[]
}

interface ViewRoleData {
  id: number
  readonly?: boolean
}

const modalStore = useModalStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const isFetchingInitialData = ref<boolean>(false)
const isEdit = ref<boolean>(false)
const readonly = ref<boolean>(false)
const catalogue = ref<AbilityDefinition[]>([])

const currentRole = ref<RoleForm>({
  id: null,
  name: '',
  abilities: [],
})

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
  catalogue.value = (abilitiesRes.abilities ?? []) as unknown as AbilityDefinition[]

  const data = modalStore.data as number | ViewRoleData | null | undefined
  const id = typeof data === 'number' ? data : (data?.id ?? null)
  readonly.value = typeof data === 'object' && data !== null && data.readonly === true

  if (id) {
    isEdit.value = !readonly.value
    const response = await roleService.get(id)
    if (response.data) {
      currentRole.value = {
        id: response.data.id,
        // A preset's copy is named preset:{key}; it is shown by its title.
        name: response.data.preset ? (response.data.title ?? response.data.name) : response.data.name,
        abilities: (response.data.abilities ?? []).map((ability) => ability.name),
      }
    }
  } else {
    isEdit.value = false
    currentRole.value = { id: null, name: '', abilities: [] }
  }

  isFetchingInitialData.value = false
}

async function submitRoleData(): Promise<void> {
  if (readonly.value) return

  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true
  try {
    const payload: CreateRolePayload = {
      name: currentRole.value.name,
      abilities: currentRole.value.abilities.map((ability) => ({ ability })),
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

function closeRolesModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    currentRole.value = { id: null, name: '', abilities: [] }
    isEdit.value = false
    readonly.value = false
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
        <p v-if="readonly" class="mb-3 text-sm text-muted">
          {{ $t('settings.roles.preset_hint') }}
        </p>
        <BaseInputGroup
          :label="$t('settings.roles.name')"
          class="mt-3"
          :error="v$.name.$error && v$.name.$errors[0].$message"
          :required="!readonly"
          :content-loading="isFetchingInitialData"
        >
          <BaseInput
            v-model="currentRole.name"
            :invalid="v$.name.$error"
            :disabled="readonly"
            type="text"
            :content-loading="isFetchingInitialData"
            @input="v$.name.$touch()"
          />
        </BaseInputGroup>
      </div>

      <AbilityMatrix
        v-model="currentRole.abilities"
        :abilities="catalogue"
        :readonly="readonly"
        :error="v$.abilities.$error ? String(v$.abilities.$errors[0].$message) : null"
      />

      <div
        class="z-0 flex justify-end p-4 border-t border-solid border-line-default"
      >
        <BaseButton
          class="text-sm"
          :class="{ 'me-3': !readonly }"
          variant="primary-outline"
          type="button"
          @click="closeRolesModal"
        >
          {{ readonly ? $t('general.close') : $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          v-if="!readonly"
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
