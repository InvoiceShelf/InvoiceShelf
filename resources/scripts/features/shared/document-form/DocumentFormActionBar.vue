<template>
  <!--
    The document editor's bar on phones: the running total, Save, and in edit
    mode a menu with the PDF. Wider screens keep these in the page header.
    It must sit inside the editor's <form>, so Save submits that form.
  -->
  <BaseActionBar>
    <div data-bar-info class="flex flex-col min-w-0 pr-1">
      <span class="text-xs text-muted">{{ $t('invoices.total') }}</span>
      <span class="text-base font-semibold leading-tight text-heading">
        <BaseFormatMoney :amount="total" :currency="moneyCurrency" />
      </span>
    </div>

    <BaseButton
      :loading="saving"
      :disabled="saving"
      :content-loading="loading"
      variant="primary"
      type="submit"
    >
      <template #left="slotProps">
        <BaseIcon v-if="!saving" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
      </template>
      {{ saveLabel }}
    </BaseButton>

    <BaseDropdown v-if="pdfUrl" position="top-end">
      <template #activator>
        <span
          data-overflow
          class="flex items-center justify-center h-11 border rounded-lg bg-surface border-line-default text-body"
        >
          <BaseIcon name="EllipsisHorizontalIcon" class="w-5 h-5" />
          <span class="sr-only">{{ $t('general.actions') }}</span>
        </span>
      </template>

      <router-link :to="pdfUrl" target="_blank">
        <BaseDropdownItem>
          <BaseIcon name="DocumentTextIcon" class="w-5 h-5 mr-3 text-subtle" />
          {{ $t('general.view_pdf') }}
        </BaseDropdownItem>
      </router-link>
    </BaseDropdown>
  </BaseActionBar>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { CurrencyConfig } from '@/scripts/utils/format-money'

interface Props {
  total: number
  // The document's currency as the form holds it; empty means the company's
  currency?: object | string | null
  saveLabel: string
  saving?: boolean
  loading?: boolean
  pdfUrl?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  currency: null,
  saving: false,
  loading: false,
  pdfUrl: null,
})

const moneyCurrency = computed<CurrencyConfig | null>(() => {
  return props.currency && typeof props.currency === 'object'
    ? props.currency as CurrencyConfig
    : null
})
</script>
