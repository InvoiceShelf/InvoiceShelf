<template>
  <form @submit.prevent="handleSubmit">
    <div
      v-for="(section, sectionIdx) in schema.sections"
      :key="sectionIdx"
      class="mb-10 last:mb-0"
    >
      <h3 class="text-base font-semibold text-heading mb-4">
        {{ $t(section.title) }}
      </h3>

      <div class="grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2">
        <BaseInputGroup
          v-for="field in section.fields"
          :key="field.key"
          :label="$t(field.label)"
          :required="isRequired(field)"
          :error="errorFor(field.key)"
          :class="{ 'md:col-span-2': isWideField(field) }"
        >
          <!-- text / password / number -->
          <BaseInput
            v-if="field.type === 'text' || field.type === 'password' || field.type === 'number'"
            :type="field.type"
            :model-value="(localValues[field.key] as string | number | null) ?? ''"
            :invalid="!!errorFor(field.key)"
            @update:model-value="setValue(field.key, $event)"
            @blur="touchField(field.key)"
          />

          <!-- textarea -->
          <BaseTextarea
            v-else-if="field.type === 'textarea'"
            :model-value="(localValues[field.key] as string) ?? ''"
            rows="4"
            @update:model-value="setValue(field.key, $event)"
            @blur="touchField(field.key)"
          />

          <!-- switch -->
          <BaseSwitch
            v-else-if="field.type === 'switch'"
            :model-value="!!localValues[field.key]"
            @update:model-value="setValue(field.key, $event)"
          />

          <!-- select -->
          <BaseMultiselect
            v-else-if="field.type === 'select'"
            :model-value="localValues[field.key]"
            :options="optionsArray(field)"
            :allow-empty="!isRequired(field)"
            track-by="value"
            label="label"
            @update:model-value="setValue(field.key, $event)"
          />

          <!-- multiselect -->
          <BaseMultiselect
            v-else-if="field.type === 'multiselect'"
            :model-value="localValues[field.key]"
            :options="optionsArray(field)"
            :multiple="true"
            track-by="value"
            label="label"
            @update:model-value="setValue(field.key, $event)"
          />
        </BaseInputGroup>
      </div>
    </div>

    <div class="flex justify-end mt-8 pt-6 border-t border-line-default">
      <BaseButton
        type="submit"
        :loading="isSaving"
        :disabled="isSaving"
      >
        <template #left="slotProps">
          <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('general.save') }}
      </BaseButton>
    </div>
  </form>
</template>

<script setup lang="ts">
import { reactive, ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVuelidate } from '@vuelidate/core'
import {
  required as requiredValidator,
  email as emailValidator,
  url as urlValidator,
  minLength as minLengthValidator,
  maxLength as maxLengthValidator,
  numeric as numericValidator,
  helpers,
} from '@vuelidate/validators'
import type {
  ModuleSettingsField,
  ModuleSettingsSchema,
} from '@/scripts/api/services/moduleSettings.service'

interface Props {
  schema: ModuleSettingsSchema
  values: Record<string, unknown>
  isSaving?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isSaving: false,
})

const emit = defineEmits<{
  (e: 'submit', values: Record<string, unknown>): void
}>()

const { t } = useI18n()

// ---------------------------------------------------------------------------
// Local form state — a reactive copy of the incoming values, with defaults
// from the schema for any keys not yet stored.
// ---------------------------------------------------------------------------

const localValues = reactive<Record<string, unknown>>({})

function rebuildLocalValues(): void {
  for (const key of Object.keys(localValues)) {
    delete localValues[key]
  }
  for (const section of props.schema.sections) {
    for (const field of section.fields) {
      const incoming = props.values[field.key]
      localValues[field.key] = incoming !== undefined && incoming !== null
        ? incoming
        : field.default
    }
  }
}

watch(
  () => [props.schema, props.values],
  () => rebuildLocalValues(),
  { immediate: true, deep: true },
)

function setValue(key: string, value: unknown): void {
  localValues[key] = value
}

function isRequired(field: ModuleSettingsField): boolean {
  return field.rules.includes('required')
}

function isWideField(field: ModuleSettingsField): boolean {
  return field.type === 'textarea' || field.type === 'multiselect'
}

function optionsArray(field: ModuleSettingsField): Array<{ value: string, label: string }> {
  if (!field.options) return []
  return Object.entries(field.options).map(([value, label]) => ({ value, label }))
}

// ---------------------------------------------------------------------------
// Vuelidate rules built dynamically from the schema's `rules` arrays.
// Supported rule strings: 'required', 'email', 'url', 'numeric',
// 'min:N' (string min length), 'max:N' (string max length).
// Unsupported rule strings are silently ignored client-side; the backend
// validator is the source of truth.
// ---------------------------------------------------------------------------

const dynamicRules = computed(() => {
  const fieldRules: Record<string, Record<string, unknown>> = {}

  for (const section of props.schema.sections) {
    for (const field of section.fields) {
      const rules: Record<string, unknown> = {}
      for (const rule of field.rules) {
        if (rule === 'required') {
          rules.required = helpers.withMessage(t('validation.required'), requiredValidator)
        } else if (rule === 'email') {
          rules.email = helpers.withMessage(t('validation.email_incorrect'), emailValidator)
        } else if (rule === 'url') {
          rules.url = helpers.withMessage(t('validation.url_incorrect'), urlValidator)
        } else if (rule === 'numeric') {
          rules.numeric = helpers.withMessage(t('validation.numeric'), numericValidator)
        } else if (rule.startsWith('min:')) {
          const n = parseInt(rule.slice(4), 10)
          if (!Number.isNaN(n)) {
            rules.minLength = helpers.withMessage(
              t('validation.name_min_length', { count: n }),
              minLengthValidator(n),
            )
          }
        } else if (rule.startsWith('max:')) {
          const n = parseInt(rule.slice(4), 10)
          if (!Number.isNaN(n)) {
            rules.maxLength = helpers.withMessage(
              t('validation.name_max_length', { count: n }),
              maxLengthValidator(n),
            )
          }
        }
      }
      if (Object.keys(rules).length > 0) {
        fieldRules[field.key] = rules
      }
    }
  }

  return fieldRules
})

const v$ = useVuelidate(dynamicRules, localValues)

function errorFor(key: string): string | undefined {
  const fieldState = (v$.value as Record<string, { $error: boolean, $errors: Array<{ $message: unknown }> }>)[key]
  if (!fieldState || !fieldState.$error) return undefined
  return String(fieldState.$errors[0]?.$message ?? '')
}

function touchField(key: string): void {
  const fieldState = (v$.value as Record<string, { $touch?: () => void }>)[key]
  fieldState?.$touch?.()
}

async function handleSubmit(): Promise<void> {
  const valid = await v$.value.$validate()
  if (!valid) return
  emit('submit', { ...localValues })
}
</script>
