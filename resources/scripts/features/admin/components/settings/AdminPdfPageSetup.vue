<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PdfPageSetup } from '@/scripts/api/services/pdf.service'

const { t } = useI18n()

/**
 * Page geometry, shared by both drivers.
 *
 * Paper size used to be Gotenberg-only and stored as a single "210mm 297mm"
 * string, so dompdf had no paper size at all and switching drivers lost it.
 * These fields are rendered identically for either driver and applied to both.
 */
const form = defineModel<PdfPageSetup>({ required: true })

defineProps<{
  isFetchingInitialData?: boolean
  errors?: Record<string, string | false | undefined>
  /**
   * Page numbers rely on Chromium repeating a footer template, which dompdf has
   * no equivalent of, so the control only appears for Gotenberg. The value is
   * still carried by both forms so saving from dompdf cannot clear it.
   */
  supportsPageNumbers?: boolean
}>()

// Convenience only. Storage is always a pair of CSS lengths, because Gotenberg
// has no concept of a named size and dompdf's named table cannot express
// everything Gotenberg accepts.
const PRESETS = [
  { label: 'A3', width: '297mm', height: '420mm' },
  { label: 'A4', width: '210mm', height: '297mm' },
  { label: 'A5', width: '148mm', height: '210mm' },
  { label: 'Letter', width: '8.5in', height: '11in' },
  { label: 'Legal', width: '8.5in', height: '14in' },
]

const CUSTOM = 'Custom'

const presetOptions = [...PRESETS.map((p) => p.label), CUSTOM]

const selectedPreset = computed({
  get() {
    const match = PRESETS.find(
      (p) => p.width === form.value.pdf_paper_width && p.height === form.value.pdf_paper_height
    )

    return match?.label ?? CUSTOM
  },
  set(label: string) {
    const preset = PRESETS.find((p) => p.label === label)

    if (preset) {
      form.value.pdf_paper_width = preset.width
      form.value.pdf_paper_height = preset.height
    }
  },
})

const isCustom = computed(() => selectedPreset.value === CUSTOM)

const orientations = computed(() => [
  { label: t('settings.pdf.portrait'), value: 'portrait' },
  { label: t('settings.pdf.landscape'), value: 'landscape' },
])
</script>

<template>
  <BaseInputGrid>
    <BaseInputGroup :label="$t('settings.pdf.paper_size')">
      <BaseMultiselect
        v-model="selectedPreset"
        :content-loading="isFetchingInitialData"
        :options="presetOptions"
        :can-deselect="false"
      />
    </BaseInputGroup>

    <BaseInputGroup :label="$t('settings.pdf.orientation')">
      <BaseMultiselect
        v-model="form.pdf_orientation"
        :content-loading="isFetchingInitialData"
        :options="orientations"
        label="label"
        value-prop="value"
        :can-deselect="false"
      />
    </BaseInputGroup>

    <BaseInputGroup
      v-if="isCustom"
      :label="$t('settings.pdf.paper_width')"
      :help-text="$t('settings.pdf.length_hint')"
      :error="errors?.pdf_paper_width"
    >
      <BaseInput
        v-model.trim="form.pdf_paper_width"
        :content-loading="isFetchingInitialData"
        :invalid="!!errors?.pdf_paper_width"
        type="text"
        name="pdf_paper_width"
      />
    </BaseInputGroup>

    <BaseInputGroup
      v-if="isCustom"
      :label="$t('settings.pdf.paper_height')"
      :help-text="$t('settings.pdf.length_hint')"
      :error="errors?.pdf_paper_height"
    >
      <BaseInput
        v-model.trim="form.pdf_paper_height"
        :content-loading="isFetchingInitialData"
        :invalid="!!errors?.pdf_paper_height"
        type="text"
        name="pdf_paper_height"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('settings.pdf.margin_top')"
      :help-text="$t('settings.pdf.length_hint')"
      :error="errors?.pdf_margin_top"
    >
      <BaseInput
        v-model.trim="form.pdf_margin_top"
        :content-loading="isFetchingInitialData"
        :invalid="!!errors?.pdf_margin_top"
        type="text"
        name="pdf_margin_top"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('settings.pdf.margin_bottom')"
      :help-text="$t('settings.pdf.length_hint')"
      :error="errors?.pdf_margin_bottom"
    >
      <BaseInput
        v-model.trim="form.pdf_margin_bottom"
        :content-loading="isFetchingInitialData"
        :invalid="!!errors?.pdf_margin_bottom"
        type="text"
        name="pdf_margin_bottom"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('settings.pdf.margin_left')"
      :help-text="$t('settings.pdf.length_hint')"
      :error="errors?.pdf_margin_left"
    >
      <BaseInput
        v-model.trim="form.pdf_margin_left"
        :content-loading="isFetchingInitialData"
        :invalid="!!errors?.pdf_margin_left"
        type="text"
        name="pdf_margin_left"
      />
    </BaseInputGroup>

    <BaseInputGroup
      :label="$t('settings.pdf.margin_right')"
      :help-text="$t('settings.pdf.length_hint')"
      :error="errors?.pdf_margin_right"
    >
      <BaseInput
        v-model.trim="form.pdf_margin_right"
        :content-loading="isFetchingInitialData"
        :invalid="!!errors?.pdf_margin_right"
        type="text"
        name="pdf_margin_right"
      />
    </BaseInputGroup>

    <BaseInputGroup
      v-if="supportsPageNumbers"
      :label="$t('settings.pdf.page_numbers')"
      :help-text="$t('settings.pdf.page_numbers_hint')"
    >
      <BaseSwitch v-model="form.pdf_page_numbers" class="flex" />
    </BaseInputGroup>
  </BaseInputGrid>
</template>
