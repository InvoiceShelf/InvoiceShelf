<template>
  <article
    class="
      flex flex-col
      overflow-hidden
      rounded-sm
      border border-gray-300
      bg-white
      shadow-sm
      transition-shadow duration-150
      hover:shadow-md
    "
  >
    <div class="relative">
      <router-link
        :to="{ name: 'modules.view', params: { slug: data.slug } }"
        class="
          block
          focus:outline-none
          focus-visible:ring-2 focus-visible:ring-primary-500
          focus-visible:ring-offset-2
        "
      >
        <div
          class="
            flex h-40 w-full
            items-center justify-center
            border-b border-gray-200
            bg-gradient-to-br from-gray-100 to-gray-200
          "
        >
          <img
            v-if="data.cover"
            class="h-full w-full object-contain object-center"
            :src="data.cover"
            alt=""
          />
          <BaseIcon
            v-else
            name="PuzzlePieceIcon"
            class="h-16 w-16 text-gray-400"
          />
        </div>
      </router-link>
      <div
        v-if="data.installed"
        class="absolute right-3 top-3 flex flex-wrap justify-end gap-1.5"
      >
        <span
          class="
            rounded
            px-2.5
            py-1
            text-xs
            font-semibold
            shadow-md
            ring-2 ring-white/60
          "
          :class="
            data.update_available
              ? 'bg-amber-500 text-white'
              : 'bg-emerald-600 text-white'
          "
        >
          <span v-if="data.update_available">
            {{ $t('modules.update_available') }}
          </span>
          <span v-else>
            {{ $t('modules.installed') }}
          </span>
        </span>
      </div>
    </div>

    <div class="flex flex-1 flex-col p-4">
      <h3
        class="text-base font-semibold leading-snug text-gray-900 line-clamp-2"
      >
        <router-link
          :to="{ name: 'modules.view', params: { slug: data.slug } }"
          class="
            text-primary-600
            hover:text-primary-700 hover:underline
            focus:outline-none
          "
        >
          {{ data.name }}
        </router-link>
      </h3>
      <div class="mt-2">
        <span
          class="
            inline-flex max-w-full items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
          "
          :class="catalogKindBadgeClass"
        >
          {{ catalogKindLabel }}
        </span>
      </div>
      <p v-if="data.author_name" class="mt-2 text-sm text-gray-600">
        {{ $t('modules.by_author', { author: data.author_name }) }}
      </p>
      <base-text
        :text="data.short_description"
        class="mt-2 flex-1 text-sm leading-relaxed text-gray-700 line-clamp-4"
      />

      <div
        class="
          mt-4
          flex flex-col gap-3
          border-t border-gray-100
          pt-4
          sm:flex-row sm:items-center sm:justify-between
        "
      >
        <span class="text-xs text-gray-500">
          {{ $t('modules.version') }} {{ data.latest_module_version }}
        </span>
        <router-link
          :to="{ name: 'modules.view', params: { slug: data.slug } }"
          class="
            inline-flex
            items-center
            justify-center
            whitespace-nowrap
            rounded-md
            border border-transparent
            bg-primary-600
            px-3
            py-2
            text-sm
            font-medium
            leading-4
            text-white
            shadow-xs
            hover:bg-primary-700
            focus:outline-hidden
            focus:ring-2
            focus:ring-primary-500
            focus:ring-offset-2
          "
        >
          {{ $t('modules.view_details') }}
        </router-link>
      </div>
    </div>
  </article>
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
