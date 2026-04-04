<script setup lang="ts">
import { reactive, ref, computed, defineAsyncComponent } from 'vue'
import { useI18n } from 'vue-i18n'
import useVuelidate from '@vuelidate/core'
import { required, numeric, helpers } from '@vuelidate/validators'
import { useModalStore } from '@v2/stores/modal.store'
import { customFieldService } from '@v2/api/services/custom-field.service'
import type { CreateCustomFieldPayload } from '@v2/api/services/custom-field.service'

interface FieldOption {
  name: string
}

interface DataType {
  label: string
  value: string
}

interface CustomFieldForm {
  id: number | null
  name: string
  label: string
  model_type: string
  type: string
  placeholder: string | null
  is_required: number
  options: FieldOption[]
  order: number | null
  default_answer: string | boolean | number | null
  dateTimeValue: string | null
  in_use: boolean
}

const modalStore = useModalStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const isEdit = ref<boolean>(false)

const currentCustomField = ref<CustomFieldForm>({
  id: null,
  name: '',
  label: '',
  model_type: 'Customer',
  type: 'Input',
  placeholder: null,
  is_required: 0,
  options: [],
  order: null,
  default_answer: null,
  dateTimeValue: null,
  in_use: false,
})

const modelTypes = reactive([
  { label: t('settings.custom_fields.model_type.customer'), value: 'Customer' },
  { label: t('settings.custom_fields.model_type.invoice'), value: 'Invoice' },
  { label: t('settings.custom_fields.model_type.estimate'), value: 'Estimate' },
  { label: t('settings.custom_fields.model_type.expense'), value: 'Expense' },
  { label: t('settings.custom_fields.model_type.payment'), value: 'Payment' },
])

const dataTypes = reactive<DataType[]>([
  { label: 'Text', value: 'Input' },
  { label: 'Textarea', value: 'TextArea' },
  { label: 'Phone', value: 'Phone' },
  { label: 'URL', value: 'Url' },
  { label: 'Number', value: 'Number' },
  { label: 'Select Field', value: 'Dropdown' },
  { label: 'Switch Toggle', value: 'Switch' },
  { label: 'Date', value: 'Date' },
  { label: 'Time', value: 'Time' },
  { label: 'Date & Time', value: 'DateTime' },
])

const selectedType = ref<DataType>(dataTypes[0])

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'CustomFieldModal'
)

const isSwitchTypeSelected = computed<boolean>(
  () => selectedType.value?.label === 'Switch Toggle'
)

const isDropdownSelected = computed<boolean>(
  () => selectedType.value?.label === 'Select Field'
)

const defaultValueComponent = computed(() => {
  if (currentCustomField.value.type) {
    return defineAsyncComponent(
      () =>
        import(
          `@/scripts/admin/components/custom-fields/types/${currentCustomField.value.type}Type.vue`
        )
    )
  }
  return null
})

const isRequiredField = computed<boolean>({
  get: () => currentCustomField.value.is_required === 1,
  set: (value: boolean) => {
    currentCustomField.value.is_required = value ? 1 : 0
  },
})

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  label: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  model_type: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  order: {
    required: helpers.withMessage(t('validation.required'), required),
    numeric: helpers.withMessage(t('validation.numbers_only'), numeric),
  },
  type: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(rules, currentCustomField)

function setData(): void {
  if (isEdit.value) {
    const found = dataTypes.find(
      (type) => type.value === currentCustomField.value.type
    )
    if (found) selectedType.value = found
  } else {
    currentCustomField.value.model_type = modelTypes[0].value
    currentCustomField.value.type = dataTypes[0].value
    selectedType.value = dataTypes[0]
  }
}

async function setInitialData(): Promise<void> {
  if (modalStore.data && typeof modalStore.data === 'number') {
    isEdit.value = true
    const response = await customFieldService.get(modalStore.data)
    if (response.data) {
      const field = response.data
      currentCustomField.value = {
        id: field.id,
        name: field.name,
        label: field.label,
        model_type: field.model_type,
        type: field.type,
        placeholder: field.placeholder,
        is_required: field.is_required ? 1 : 0,
        options: field.options
          ? field.options.map((o) => ({ name: typeof o === 'string' ? o : o }))
          : [],
        order: field.order,
        default_answer: field.default_answer,
        dateTimeValue: null,
        in_use: field.in_use,
      }
    }
  } else {
    isEdit.value = false
    resetForm()
  }
  setData()
}

async function submitCustomFieldData(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true

  const payload: CreateCustomFieldPayload = {
    name: currentCustomField.value.name,
    label: currentCustomField.value.label,
    model_type: currentCustomField.value.model_type,
    type: currentCustomField.value.type,
    placeholder: currentCustomField.value.placeholder,
    is_required: currentCustomField.value.is_required === 1,
    options: currentCustomField.value.options.length
      ? currentCustomField.value.options.map((o) => o.name)
      : null,
    order: currentCustomField.value.order,
  }

  try {
    if (isEdit.value && currentCustomField.value.id) {
      await customFieldService.update(currentCustomField.value.id, payload)
    } else {
      await customFieldService.create(payload)
    }

    isSaving.value = false
    if (modalStore.refreshData) {
      modalStore.refreshData()
    }
    closeCustomFieldModal()
  } catch {
    isSaving.value = false
  }
}

function addNewOption(option: string): void {
  currentCustomField.value.options = [
    { name: option },
    ...currentCustomField.value.options,
  ]
}

function removeOption(index: number): void {
  if (isEdit.value && currentCustomField.value.in_use) {
    return
  }

  const option = currentCustomField.value.options[index]
  if (option.name === currentCustomField.value.default_answer) {
    currentCustomField.value.default_answer = null
  }

  currentCustomField.value.options.splice(index, 1)
}

function onSelectedTypeChange(data: DataType): void {
  currentCustomField.value.type = data.value
}

function resetForm(): void {
  currentCustomField.value = {
    id: null,
    name: '',
    label: '',
    model_type: 'Customer',
    type: 'Input',
    placeholder: null,
    is_required: 0,
    options: [],
    order: null,
    default_answer: null,
    dateTimeValue: null,
    in_use: false,
  }
  selectedType.value = dataTypes[0]
}

function closeCustomFieldModal(): void {
  modalStore.closeModal()
  setTimeout(() => {
    resetForm()
    v$.value.$reset()
  }, 300)
}
</script>

<template>
  <BaseModal :show="modalActive" @open="setInitialData">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="closeCustomFieldModal"
        />
      </div>
    </template>

    <form action="" @submit.prevent="submitCustomFieldData">
      <div class="overflow-y-auto max-h-[550px]">
        <div class="px-4 md:px-8 py-8 overflow-y-auto sm:p-6">
          <BaseInputGrid layout="one-column">
            <BaseInputGroup
              :label="$t('settings.custom_fields.name')"
              required
              :error="v$.name.$error && v$.name.$errors[0].$message"
            >
              <BaseInput
                v-model="currentCustomField.name"
                :invalid="v$.name.$error"
                @input="v$.name.$touch()"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('settings.custom_fields.model')"
              :error="
                v$.model_type.$error && v$.model_type.$errors[0].$message
              "
              :help-text="
                currentCustomField.in_use
                  ? $t('settings.custom_fields.model_in_use')
                  : ''
              "
              required
            >
              <BaseMultiselect
                v-model="currentCustomField.model_type"
                :options="modelTypes"
                value-prop="value"
                :can-deselect="false"
                :invalid="v$.model_type.$error"
                :searchable="true"
                :disabled="currentCustomField.in_use"
                @input="v$.model_type.$touch()"
              />
            </BaseInputGroup>

            <BaseInputGroup
              class="flex items-center space-x-4"
              :label="$t('settings.custom_fields.required')"
            >
              <BaseSwitch v-model="isRequiredField" />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('settings.custom_fields.type')"
              :error="v$.type.$error && v$.type.$errors[0].$message"
              :help-text="
                currentCustomField.in_use
                  ? $t('settings.custom_fields.type_in_use')
                  : ''
              "
              required
            >
              <BaseMultiselect
                v-model="selectedType"
                :options="dataTypes"
                :invalid="v$.type.$error"
                :disabled="currentCustomField.in_use"
                :searchable="true"
                :can-deselect="false"
                object
                @update:model-value="onSelectedTypeChange"
              />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('settings.custom_fields.label')"
              required
              :error="v$.label.$error && v$.label.$errors[0].$message"
            >
              <BaseInput
                v-model="currentCustomField.label"
                :invalid="v$.label.$error"
                @input="v$.label.$touch()"
              />
            </BaseInputGroup>

            <BaseInputGroup
              v-if="isDropdownSelected"
              :label="$t('settings.custom_fields.options')"
            >
              <div
                v-for="(option, index) in currentCustomField.options"
                :key="index"
                class="flex items-center mt-5"
              >
                <BaseInput v-model="option.name" class="w-64" />
                <BaseIcon
                  name="MinusCircleIcon"
                  class="ml-1 cursor-pointer"
                  :class="
                    currentCustomField.in_use
                      ? 'text-subtle'
                      : 'text-red-300'
                  "
                  @click="removeOption(index)"
                />
              </div>
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('settings.custom_fields.default_value')"
              class="relative"
            >
              <component
                :is="defaultValueComponent"
                v-if="defaultValueComponent"
                v-model="currentCustomField.default_answer"
                :options="currentCustomField.options"
                :default-date-time="currentCustomField.dateTimeValue"
              />
            </BaseInputGroup>

            <BaseInputGroup
              v-if="!isSwitchTypeSelected"
              :label="$t('settings.custom_fields.placeholder')"
            >
              <BaseInput v-model="currentCustomField.placeholder" />
            </BaseInputGroup>

            <BaseInputGroup
              :label="$t('settings.custom_fields.order')"
              :error="v$.order.$error && v$.order.$errors[0].$message"
              required
            >
              <BaseInput
                v-model="currentCustomField.order"
                type="number"
                :invalid="v$.order.$error"
                @input="v$.order.$touch()"
              />
            </BaseInputGroup>
          </BaseInputGrid>
        </div>
      </div>

      <div
        class="z-0 flex justify-end p-4 border-t border-solid border-line-default"
      >
        <BaseButton
          class="mr-3"
          type="button"
          variant="primary-outline"
          @click="closeCustomFieldModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>

        <BaseButton
          variant="primary"
          :loading="isSaving"
          :disabled="isSaving"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              :class="slotProps.class"
              name="ArrowDownOnSquareIcon"
            />
          </template>
          {{ isEdit ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
