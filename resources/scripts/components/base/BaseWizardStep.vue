<script setup lang="ts">

interface Props {
  title?: string | null
  description?: string | null
  stepContainerClass?: string
  stepTitleClass?: string
  stepDescriptionClass?: string
}

/**
 * The wizard step lives inside InstallationLayout's card chrome, so the
 * container itself is just a content wrapper — no extra background, border,
 * or rounding. Earlier defaults included those, which created a visible
 * card-inside-a-card when used inside the layout.
 */
withDefaults(defineProps<Props>(), {
  title: null,
  description: null,
  stepContainerClass: 'w-full',
  stepTitleClass: 'text-2xl not-italic font-semibold leading-7 text-heading',
  stepDescriptionClass:
    'mt-2 mb-6 text-sm not-italic leading-relaxed text-muted',
})
</script>

<template>
  <div :class="stepContainerClass">
    <header v-if="title || description" class="mb-6">
      <h2 v-if="title" :class="stepTitleClass">
        {{ title }}
      </h2>
      <p v-if="description" :class="stepDescriptionClass">
        {{ description }}
      </p>
    </header>
    <slot />
  </div>
</template>
