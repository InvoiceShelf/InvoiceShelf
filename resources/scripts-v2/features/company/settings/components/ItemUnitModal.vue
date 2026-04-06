<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, helpers } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useModalStore } from '@v2/stores/modal.store'
import { useItemStore } from '@v2/features/company/items/store'

interface ItemUnitForm {
  id: number | null
  name: string
}

const modalStore = useModalStore()
const itemStore = useItemStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const isEdit = ref<boolean>(false)

const currentItemUnit = ref<ItemUnitForm>({
  id: null,
  name: '',
})

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'ItemUnitModal'
)

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(
      t('validation.name_min_length', { count: 2 }),
      minLength(2)
    ),
  },
}))

const v$ = useVuelidate(rules, currentItemUnit)

async function setInitialData(): Promise<void> {
  if (modalStore.data && typeof modalStore.data === 'number') {
    isEdit.value = true
    await itemStore.fetchItemUnit(modalStore.data)
    currentItemUnit.value = {
      id: itemStore.currentItemUnit.id ?? null,
      name: itemStore.currentItemUnit.name,
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
      })
    } else {
      res = await itemStore.addItemUnit({
        name: currentItemUnit.value.name,
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
  currentItemUnit.value = { id: null, name: '' }
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
    @close="closeItemUnitModal"
    @open="setInitialData"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeItemUnitModal"
        />
      </div>
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
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
      >
        <BaseButton
          type="button"
          variant="primary-outline"
          class="mr-3 text-sm"
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
