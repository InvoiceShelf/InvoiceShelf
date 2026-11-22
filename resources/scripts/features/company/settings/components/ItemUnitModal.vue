<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useGlobalStore } from '@/scripts/stores/global.store'
import {
  useItemStore,
  DEFAULT_UNIT_CODE,
} from '@/scripts/features/company/items/store'

interface ItemUnitForm {
  id: number | null
  name: string
  unit_code: string
}

const modalStore = useModalStore()
const globalStore = useGlobalStore()
const itemStore = useItemStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const isEdit = ref<boolean>(false)

const currentItemUnit = ref<ItemUnitForm>({
  id: null,
  name: '',
  unit_code: DEFAULT_UNIT_CODE,
})

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'ItemUnitModal'
)

const unitCodeOptions = computed(() =>
  globalStore.unitCodes.map((option) => ({
    value: option.value,
    label: `${t(option.key)} (${option.value})`,
  }))
)

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 2 }),
      minLength(2)
    ),
  },
  unit_code: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, currentItemUnit)

async function setInitialData(): Promise<void> {
  if (!globalStore.unitCodes.length) {
    await globalStore.fetchConfig({ key: 'unit_codes' })
  }

  if (modalStore.data && typeof modalStore.data === 'number') {
    isEdit.value = true
    await itemStore.fetchItemUnit(modalStore.data)
    currentItemUnit.value = {
      id: itemStore.currentItemUnit.id ?? null,
      name: itemStore.currentItemUnit.name,
      unit_code: itemStore.currentItemUnit.unit_code || DEFAULT_UNIT_CODE,
    }
  } else {
    isEdit.value = false
    resetForm()
  }
}

async function submitItemUnit(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true
  try {
    let res
    if (isEdit.value && currentItemUnit.value.id) {
      res = await itemStore.updateItemUnit({
        id: currentItemUnit.value.id,
        name: currentItemUnit.value.name,
        unit_code: currentItemUnit.value.unit_code,
      })
    } else {
      res = await itemStore.addItemUnit({
        name: currentItemUnit.value.name,
        unit_code: currentItemUnit.value.unit_code,
      })
    }

    if (modalStore.refreshData && res?.data) {
      modalStore.refreshData(res.data)
    }
    closeItemUnitModal()
  } catch {
    // handled
  } finally {
    isSaving.value = false
  }
}

function resetForm(): void {
  currentItemUnit.value = {
    id: null,
    name: '',
    unit_code: DEFAULT_UNIT_CODE,
  }
}

function closeItemUnitModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    resetForm()
    isEdit.value = false
    v$.value.$reset()
  }, 300)
}
</script>

<template>
  <BaseModal
    :show="modalActive"
    closable
    @close="closeItemUnitModal"
    @open="setInitialData"
  >
    <template #header>
      {{ modalStore.title }}
    </template>

    <form action="" @submit.prevent="submitItemUnit">
      <div class="p-8 sm:p-6">
        <BaseInputGroup
          :label="$t('settings.customization.items.unit_name')"
          :error="v$.name.$error && v$.name.$errors[0].$message"
          variant="horizontal"
          required
        >
          <BaseInput
            v-model="currentItemUnit.name"
            :invalid="v$.name.$error"
            type="text"
            @input="v$.name.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.customization.items.unit_code')"
          :help-text="$t('settings.customization.items.unit_code_help')"
          :error="v$.unit_code.$error && v$.unit_code.$errors[0].$message"
          variant="horizontal"
          class="mt-4"
          required
        >
          <BaseMultiselect
            v-model="currentItemUnit.unit_code"
            :options="unitCodeOptions"
            label="label"
            value-prop="value"
            track-by="label"
            :invalid="v$.unit_code.$error"
            :can-deselect="false"
            :can-clear="false"
            :searchable="true"
            class="w-full"
            @select="v$.unit_code.$touch()"
          />
        </BaseInputGroup>
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          type="button"
          variant="primary-outline"
          class="me-3 text-sm"
          @click="closeItemUnitModal"
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
            <BaseIcon
              v-if="!isSaving"
              name="ArrowDownOnSquareIcon"
              :class="slotProps.class"
            />
          </template>
          {{ isEdit ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
