<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import useVuelidate from '@vuelidate/core'
import { required, numeric, helpers } from '@vuelidate/validators'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { customFieldService } from '@/scripts/api/services/custom-field.service'
import type { CreateCustomFieldPayload } from '@/scripts/api/services/custom-field.service'
import { resolveCustomFieldTypeComponent } from '@/scripts/features/shared/custom-fields/resolve-type-component'
import { useCustomFieldModels } from '@/scripts/features/shared/custom-fields/use-custom-field-models'

interface FieldOption {
  name: string
}

interface DataType {
  label: string
  value: string
}

/** What the definition says a valid answer looks like. */
interface FieldValidation {
  min_length: number | null
  max_length: number | null
  min: number | null
  max: number | null
  pattern: string | null
}

interface CustomFieldForm {
  id: number | null
  name: string
  label: string
  model_type: string
  type: string
  placeholder: string | null
  is_required: number
  placement: string
  options: FieldOption[]
  order: number | null
  validation: FieldValidation
  default_answer: string | boolean | number | null
  dateTimeValue: string | null
  in_use: boolean
}

function submittedValidation(): Record<string, unknown> | null {
  const set = Object.entries(currentCustomField.value.validation)
    .filter(([, value]) => value !== null && value !== '' && value !== undefined)
    // A number input hands back a string; store the bounds as numbers so the
    // column holds what it says it holds.
    .map(([key, value]) => [key, key === 'pattern' ? value : Number(value)])

  return set.length > 0 ? Object.fromEntries(set) : null
}

function emptyValidation(): FieldValidation {
  return { min_length: null, max_length: null, min: null, max: null, pattern: null }
}

const modalStore = useModalStore()
const notificationStore = useNotificationStore()
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
  placement: 'internal',
  options: [],
  order: null,
  validation: emptyValidation(),
  default_answer: null,
  dateTimeValue: null,
  in_use: false,
})

const { models: modelCatalog, labelFor } = useCustomFieldModels()

const modelTypes = computed(() =>
  modelCatalog.value.map((model) => ({
    label: labelFor(model.value),
    value: model.value,
  }))
)

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

const showValidation = ref<boolean>(false)

/** Free text, so length and shape mean something. */
const isTextTypeSelected = computed<boolean>(() =>
  ['Input', 'TextArea', 'Phone', 'Url'].includes(currentCustomField.value.type)
)

const isNumberTypeSelected = computed<boolean>(
  () => currentCustomField.value.type === 'Number'
)

/**
 * A switch, a date, a time and a dropdown are already constrained by the
 * widget that collects them, so the section is not offered for those.
 */
const supportsValidation = computed<boolean>(
  () => isTextTypeSelected.value || isNumberTypeSelected.value
)

const isDropdownSelected = computed<boolean>(
  () => selectedType.value?.label === 'Select Field'
)

const defaultValueComponent = computed(() =>
  resolveCustomFieldTypeComponent(currentCustomField.value.type)
)

const isRequiredField = computed<boolean>({
  get: () => currentCustomField.value.is_required === 1,
  set: (value: boolean) => {
    currentCustomField.value.is_required = value ? 1 : 0
  },
})

/**
 * Whether the field reaches the printed document. Stored as a placement
 * rather than a flag so a third destination, the customer portal say, does
 * not need a second column.
 */
const isPrintedOnDocument = computed<boolean>({
  get: () => currentCustomField.value.placement === 'document',
  set: (value: boolean) => {
    currentCustomField.value.placement = value ? 'document' : 'internal'
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
    currentCustomField.value.model_type =
      modelTypes.value[0]?.value ?? 'Customer'
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
        placement: field.placement ?? 'internal',
        validation: { ...emptyValidation(), ...(field.validation ?? {}) },
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

  let defaultAnswer = currentCustomField.value.default_answer
  // Handle Time type — convert object {HH, mm, ss} to 'HH:mm' string
  if (currentCustomField.value.type === 'Time' && typeof defaultAnswer === 'object' && defaultAnswer !== null) {
    const timeObj = defaultAnswer as Record<string, string>
    defaultAnswer = `${timeObj.HH ?? '00'}:${timeObj.mm ?? '00'}`
  }

  const payload: CreateCustomFieldPayload = {
    name: currentCustomField.value.name,
    label: currentCustomField.value.label,
    model_type: currentCustomField.value.model_type,
    type: currentCustomField.value.type,
    placeholder: currentCustomField.value.placeholder,
    is_required: currentCustomField.value.is_required === 1,
    placement: currentCustomField.value.placement,
    validation: submittedValidation(),
    options: currentCustomField.value.options.map((o) => o.name),
    order: currentCustomField.value.order,
    default_answer: defaultAnswer as string ?? null,
  }

  try {
    if (isEdit.value && currentCustomField.value.id) {
      await customFieldService.update(currentCustomField.value.id, payload)
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.custom_fields.updated_message',
      })
    } else {
      await customFieldService.create(payload)
      notificationStore.showNotification({
        type: 'success',
        message: 'settings.custom_fields.created_message',
      })
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

const newOptionValue = ref<string>('')

function onAddOption(): void {
  if (!newOptionValue.value?.trim()) return
  currentCustomField.value.options = [
    { name: newOptionValue.value.trim() },
    ...currentCustomField.value.options,
  ]
  newOptionValue.value = ''
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
    placement: 'internal',
    options: [],
    order: null,
    validation: emptyValidation(),
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
      <div class="overflow-y-auto max-h-[70vh]">
        <div class="px-4 py-6 space-y-8 md:px-8 sm:p-6">
          <!-- What the field is -->
          <section class="space-y-5">
            <h3 class="text-xs font-semibold tracking-wide uppercase text-muted">
              {{ $t('settings.custom_fields.section_field') }}
            </h3>

            <BaseInputGrid layout="two-column">
              <BaseInputGroup
                :label="$t('settings.custom_fields.name')"
                :help-text="$t('settings.custom_fields.name_help')"
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
                :label="$t('settings.custom_fields.label')"
                :help-text="$t('settings.custom_fields.label_help')"
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
                :label="$t('settings.custom_fields.model')"
                :error="v$.model_type.$error && v$.model_type.$errors[0].$message"
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
            </BaseInputGrid>
          </section>

          <!-- How it behaves -->
          <section class="space-y-5">
            <h3 class="text-xs font-semibold tracking-wide uppercase text-muted">
              {{ $t('settings.custom_fields.section_behaviour') }}
            </h3>

            <!-- The switch leads so the two rows align whatever the label
                 length; they used to sit in label-first groups and did not. -->
            <div class="flex flex-wrap gap-x-10 gap-y-4">
              <label class="flex items-center gap-3 cursor-pointer">
                <BaseSwitch v-model="isRequiredField" />
                <span class="text-sm text-heading">
                  {{ $t('settings.custom_fields.required') }}
                </span>
              </label>

              <label class="flex items-center gap-3 cursor-pointer">
                <BaseSwitch v-model="isPrintedOnDocument" />
                <span class="text-sm text-heading">
                  {{ $t('settings.custom_fields.show_on_document') }}
                </span>
              </label>
            </div>

            <BaseInputGrid layout="two-column">
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

            <BaseInputGroup
              v-if="isDropdownSelected"
              :label="$t('settings.custom_fields.options')"
            >
              <div class="flex items-center mt-1">
                <BaseInput
                  v-model="newOptionValue"
                  type="text"
                  class="w-full md:w-96"
                  :placeholder="$t('settings.custom_fields.press_enter_to_add')"
                  @keydown.enter.prevent.stop="onAddOption"
                />
                <BaseIcon
                  name="PlusCircleIcon"
                  class="ml-1 text-primary-500 cursor-pointer"
                  @click="onAddOption"
                />
              </div>

              <div
                v-for="(option, index) in currentCustomField.options"
                :key="index"
                class="flex items-center mt-5"
              >
                <BaseInput v-model="option.name" class="w-64" />
                <BaseIcon
                  name="MinusCircleIcon"
                  class="ml-1 cursor-pointer"
                  :class="currentCustomField.in_use ? 'text-subtle' : 'text-red-300'"
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
          </section>

          <!-- What counts as a valid answer. Folded away because most
               fields want none of it, and hidden entirely for the types
               their own widget already constrains. -->
          <section v-if="supportsValidation" class="space-y-5">
            <button
              type="button"
              class="flex items-center gap-2 text-xs font-semibold tracking-wide uppercase text-muted hover:text-heading"
              @click="showValidation = !showValidation"
            >
              <BaseIcon
                :name="showValidation ? 'ChevronDownIcon' : 'ChevronRightIcon'"
                class="w-4 h-4"
              />
              {{ $t('settings.custom_fields.section_validation') }}
              <span class="font-normal normal-case text-subtle">
                {{ $t('settings.custom_fields.validation_description') }}
              </span>
            </button>

            <template v-if="showValidation">
              <BaseInputGrid layout="two-column">
                <BaseInputGroup
                  v-if="isTextTypeSelected"
                  :label="$t('settings.custom_fields.min_length')"
                >
                  <BaseInput
                    v-model="currentCustomField.validation.min_length"
                    type="number"
                    min="0"
                  />
                </BaseInputGroup>

                <BaseInputGroup
                  v-if="isTextTypeSelected"
                  :label="$t('settings.custom_fields.max_length')"
                >
                  <BaseInput
                    v-model="currentCustomField.validation.max_length"
                    type="number"
                    min="1"
                  />
                </BaseInputGroup>

                <BaseInputGroup
                  v-if="isNumberTypeSelected"
                  :label="$t('settings.custom_fields.min_value')"
                >
                  <BaseInput
                    v-model="currentCustomField.validation.min"
                    type="number"
                  />
                </BaseInputGroup>

                <BaseInputGroup
                  v-if="isNumberTypeSelected"
                  :label="$t('settings.custom_fields.max_value')"
                >
                  <BaseInput
                    v-model="currentCustomField.validation.max"
                    type="number"
                  />
                </BaseInputGroup>
              </BaseInputGrid>

              <BaseInputGroup
                v-if="isTextTypeSelected"
                :label="$t('settings.custom_fields.pattern')"
                :help-text="$t('settings.custom_fields.pattern_help')"
              >
                <BaseInput
                  v-model="currentCustomField.validation.pattern"
                  :placeholder="'^[A-Z]{2}[0-9]{9}$'"
                />
              </BaseInputGroup>
            </template>
          </section>
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
