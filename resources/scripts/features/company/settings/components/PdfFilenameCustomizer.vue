<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import draggable from 'vuedraggable'
import DragIcon from '@/scripts/components/icons/DragIcon.vue'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { generateClientId } from '@/scripts/utils/generate-client-id'
import { announce } from '@/scripts/utils/page-focus'

interface FilenameField {
  id: string
  label: string
  description: string
  name: string
  paramLabel: string
  value: string
  inputDisabled: boolean
  allowMultiple: boolean
}

const { t } = useI18n()
const companyStore = useCompanyStore()
const isSaving = ref(false)

const allFields: Omit<FilenameField, 'id'>[] = [
  {
    label: t('settings.customization.pdf_filename.company_name'),
    description: t('settings.customization.pdf_filename.company_name_description'),
    name: 'COMPANY_NAME',
    paramLabel: '',
    value: '',
    inputDisabled: true,
    allowMultiple: false,
  },
  {
    label: t('settings.customization.pdf_filename.invoice_number'),
    description: t('settings.customization.pdf_filename.invoice_number_description'),
    name: 'DOCUMENT_NUMBER',
    paramLabel: '',
    value: '',
    inputDisabled: true,
    allowMultiple: false,
  },
  {
    label: t('settings.customization.pdf_filename.customer_company_name'),
    description: t(
      'settings.customization.pdf_filename.customer_company_name_description'
    ),
    name: 'CONTACT_DISPLAY_NAME',
    paramLabel: '',
    value: '',
    inputDisabled: true,
    allowMultiple: false,
  },
  {
    label: t('settings.customization.pdf_filename.custom_field'),
    description: t('settings.customization.pdf_filename.custom_field_description'),
    name: 'CUSTOM_TEXT',
    paramLabel: t('settings.customization.pdf_filename.custom_field_parameter'),
    value: ' - ',
    inputDisabled: false,
    allowMultiple: true,
  },
  {
    label: t('settings.customization.pdf_filename.invoice_date'),
    description: t('settings.customization.pdf_filename.invoice_date_description'),
    name: 'DOCUMENT_DATE',
    paramLabel: '',
    value: '',
    inputDisabled: true,
    allowMultiple: false,
  },
]

const selectedFields = ref<FilenameField[]>(parseFormat(
  companyStore.selectedCompanySettings.pdf_filename_format ?? ''
))

if (selectedFields.value.length === 0) {
  addField(allFields.find((field) => field.name === 'DOCUMENT_NUMBER')!)
}

const availableFields = computed(() => {
  return allFields.filter((field) => {
    if (field.allowMultiple) return true
    return !selectedFields.value.some((selected) => selected.name === field.name)
  })
})

const filenameFormat = computed(() => {
  return selectedFields.value
    .map((field) => field.name === 'CUSTOM_TEXT' ? field.value : `{${field.name}}`)
    .join('')
})

const isFormatTooLong = computed(() => filenameFormat.value.length > 255)

const filenamePreview = computed(() => {
  if (!filenameFormat.value) {
    return 'INV-000001.pdf'
  }

  const previewValues: Record<string, string> = {
    '{COMPANY_NAME}': companyStore.selectedCompany?.name || 'My Company',
    '{DOCUMENT_NUMBER}': 'INV-000001',
    '{CONTACT_DISPLAY_NAME}': 'Customer Company',
    '{DOCUMENT_DATE}': new Date().toISOString().slice(0, 10),
  }

  let preview = filenameFormat.value
  Object.entries(previewValues).forEach(([token, value]) => {
    preview = preview.replaceAll(token, value)
  })

  preview = preview
    .replace(/[<>:"/\\|?*]/g, ' ')
    .replace(/\s+/g, ' ')
    .replace(/\.pdf$/i, '')
    .trim()
    .replace(/^[ .-]+|[ .-]+$/g, '')

  return `${preview || 'INV-000001'}.pdf`
})

function parseFormat(format: string): FilenameField[] {
  if (!format.trim()) return []

  const tokenPattern = /(\{(?:COMPANY_NAME|DOCUMENT_NUMBER|CONTACT_DISPLAY_NAME|DOCUMENT_DATE)\})/g

  return format
    .split(tokenPattern)
    .filter(Boolean)
    .map((part) => {
      const tokenName = part.match(/^\{(.+)\}$/)?.[1]
      const field = allFields.find((candidate) => candidate.name === tokenName)

      if (field) {
        return { ...field, id: generateClientId() }
      }

      const customField = allFields.find((candidate) => candidate.name === 'CUSTOM_TEXT')!
      return { ...customField, value: part, id: generateClientId() }
    })
}

function addField(field: Omit<FilenameField, 'id'>): void {
  if (!field.allowMultiple && selectedFields.value.some((item) => item.name === field.name)) {
    return
  }

  selectedFields.value.push({ ...field, id: generateClientId() })
}

function removeField(field: FilenameField): void {
  selectedFields.value = selectedFields.value.filter((item) => item.id !== field.id)
}

const handles = new Map<string, HTMLButtonElement>()

function setHandle(id: string, el: unknown): void {
  if (el instanceof HTMLButtonElement) {
    handles.set(id, el)
  } else {
    handles.delete(id)
  }
}

async function moveField(index: number, step: number): Promise<void> {
  const to = index + step

  if (to < 0 || to >= selectedFields.value.length) return

  const fields = [...selectedFields.value]
  const [moved] = fields.splice(index, 1)
  fields.splice(to, 0, moved)
  selectedFields.value = fields

  await nextTick()
  handles.get(moved.id)?.focus()
  announce(t('invoices.item.moved', { position: to + 1, count: fields.length }))
}

async function saveFormat(): Promise<void> {
  if (isSaving.value || isFormatTooLong.value) return

  isSaving.value = true
  try {
    await companyStore.updateCompanySettings({
      data: { settings: { pdf_filename_format: filenameFormat.value } },
      message: 'settings.customization.pdf_filename.updated_message',
    })
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <h3 class="text-heading text-lg font-medium">
    {{ $t('settings.customization.pdf_filename.title') }}
  </h3>
  <p class="mt-1 text-sm text-muted">
    {{ $t('settings.customization.pdf_filename.description') }}
  </p>

  <div class="overflow-x-auto">
    <table class="w-full mt-6 table-fixed">
      <colgroup>
        <col style="width: 4%" />
        <col style="width: 45%" />
        <col style="width: 27%" />
        <col style="width: 24%" />
      </colgroup>

      <thead>
        <tr>
          <th
            class="px-5 py-3 text-sm font-medium leading-5 text-start text-body border-t border-b border-line-default border-solid"
          />
          <th
            class="px-5 py-3 text-sm font-medium leading-5 text-start text-body border-t border-b border-line-default border-solid"
          >
            {{ $t('settings.customization.component') }}
          </th>
          <th
            class="px-5 py-3 text-sm font-medium leading-5 text-start text-body border-t border-b border-line-default border-solid"
          >
            {{ $t('settings.customization.Parameter') }}
          </th>
          <th
            class="px-5 py-3 text-sm font-medium leading-5 text-start text-body border-t border-b border-line-default border-solid"
          />
        </tr>
      </thead>

      <draggable
        v-model="selectedFields"
        class="divide-y divide-line-default"
        item-key="id"
        tag="tbody"
        handle=".handle"
        filter=".ignore-element"
      >
        <template #item="{ element, index }">
          <tr class="relative">
            <td class="align-middle">
              <!-- Drag to reorder, or focus and use the arrow keys -->
              <button
                :ref="(el) => setHandle(element.id, el)"
                type="button"
                class="flex items-center justify-center w-6 h-8 rounded-md cursor-move handle text-subtle focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus"
                :aria-label="$t('settings.customization.reorder_component', { name: element.label, position: index + 1, count: selectedFields.length })"
                @keydown.up.prevent="moveField(index, -1)"
                @keydown.down.prevent="moveField(index, 1)"
              >
                <DragIcon aria-hidden="true" />
              </button>
            </td>
            <td class="px-5 py-4">
              <p class="block text-sm font-medium text-primary-600 whitespace-nowrap me-2 min-w-[200px]">
                {{ element.label }}
              </p>
              <p class="text-xs text-muted mt-1">
                {{ element.description }}
              </p>
            </td>
            <td class="px-5 py-4 text-start align-middle">
              <BaseInputGroup :label="element.paramLabel">
                <BaseInput
                  v-model="element.value"
                  :disabled="element.inputDisabled"
                  :maxlength="255"
                />
              </BaseInputGroup>
            </td>
            <td class="px-5 py-4 text-end align-middle pt-10">
              <BaseButton
                variant="white"
                :aria-label="$t('general.remove_named', { name: element.label })"
                @click.prevent="removeField(element)"
              >
                {{ $t('general.remove') }}
                <template #left="slotProps">
                  <BaseIcon
                    name="XMarkIcon"
                    class="!sm:m-0"
                    :class="slotProps.class"
                  />
                </template>
              </BaseButton>
            </td>
          </tr>
        </template>

        <template #footer>
          <tr>
            <td colspan="2" class="px-5 py-4">
              <BaseInputGroup
                :label="$t('settings.customization.pdf_filename.preview')"
                :error="isFormatTooLong ? $t('settings.customization.pdf_filename.too_long') : ''"
              >
                <BaseInput :model-value="filenamePreview" disabled />
              </BaseInputGroup>
            </td>
            <td class="px-5 py-4 text-end align-middle" colspan="2">
              <BaseDropdown wrapper-class="flex items-center justify-end mt-5">
                <template #activator>
                  <BaseButton tag="span" variant="primary-outline">
                    <template #left="slotProps">
                      <BaseIcon :class="slotProps.class" name="PlusIcon" />
                    </template>
                    {{ $t('settings.customization.add_new_component') }}
                  </BaseButton>
                </template>

                <BaseDropdownItem
                  v-for="field in availableFields"
                  :key="field.name"
                  @click.prevent="addField(field)"
                >
                  {{ field.label }}
                </BaseDropdownItem>
              </BaseDropdown>
            </td>
          </tr>
        </template>
      </draggable>
    </table>
  </div>

  <BaseButton
    :loading="isSaving"
    :disabled="isSaving || isFormatTooLong"
    variant="primary"
    type="button"
    class="mt-4"
    @click="saveFormat"
  >
    <template #left="slotProps">
      <BaseIcon
        v-if="!isSaving"
        :class="slotProps.class"
        name="ArrowDownOnSquareIcon"
      />
    </template>
    {{ $t('settings.customization.save') }}
  </BaseButton>
</template>
