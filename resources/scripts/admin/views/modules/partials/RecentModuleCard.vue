<template>
  <router-link class="relative group" :to="`/admin/modules/${data.slug}`">
    <div class="relative group">
      <div
        class="aspect-w-4 aspect-h-3 rounded-lg overflow-hidden bg-gradient-to-br from-primary-100 to-gray-100 flex items-center justify-center"
      >
        <img
          v-if="data.cover"
          :src="data.cover"
          class="h-full w-full object-contain object-center"
          alt=""
        />
        <BaseIcon
          v-else
          name="PuzzlePieceIcon"
          class="h-16 w-16 text-primary-400 opacity-80"
        />
        <div
          class="flex items-end opacity-0 p-4 group-hover:opacity-100 absolute inset-0"
          aria-hidden="true"
        >
          <div
            class="
              w-full
              bg-white/75
              backdrop-filter backdrop-blur
              py-2
              px-4
              rounded-md
              text-sm
              font-medium
              text-primary-500 text-center
            "
          >
            {{ $t('modules.view_module') }}
          </div>
        </div>
      </div>
      <div class="mt-4 cursor-pointer space-y-2">
        <div
          class="
            flex
            items-start
            justify-between
            gap-3
            text-base
            font-medium
            text-gray-900
          "
        >
          <h3 class="text-primary-500 font-bold leading-snug">
            <span aria-hidden="true" class="absolute inset-0"></span>
            {{ data.name }}
          </h3>
          <p class="shrink-0 text-sm text-gray-500">
            v{{ data.latest_module_version }}
          </p>
        </div>
        <span
          class="
            relative z-10 inline-flex max-w-full items-center rounded-full px-2 py-0.5 text-[11px] font-semibold
          "
          :class="catalogKindBadgeClass"
        >
          {{ catalogKindLabel }}
        </span>
      </div>
    </div>
  </router-link>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  data: {
    type: Object,
    default: null,
    required: true,
  },
})

const { t } = useI18n()

const catalogKindLabel = computed(() => {
  if (props.data.catalog_kind === 'pdf_template') {
    return props.data.pdf_template_type === 'invoice'
      ? t('modules.kind_invoice_template')
      : t('modules.kind_estimate_template')
  }

  return t('modules.kind_extension')
})

const catalogKindBadgeClass = computed(() => {
  if (props.data.catalog_kind === 'pdf_template') {
    return props.data.pdf_template_type === 'invoice'
      ? 'bg-sky-50 text-sky-900 ring-1 ring-inset ring-sky-200'
      : 'bg-violet-50 text-violet-900 ring-1 ring-inset ring-violet-200'
  }

  return 'bg-gray-100 text-gray-800 ring-1 ring-inset ring-gray-200'
})
</script>
