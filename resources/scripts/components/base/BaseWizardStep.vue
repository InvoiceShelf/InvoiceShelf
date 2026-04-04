<template>
  <div :class="stepContainerClass">
    <div v-if="title || description">
      <p v-if="title" :class="stepTitleClass">
        {{ title }}
      </p>
      <p v-if="description" :class="stepDescriptionClass">
        {{ description }}
      </p>
    </div>
    <slot />
  </div>
</template>

<script setup>
const props = defineProps({
  title: {
    type: String,
    default: null,
  },
  description: {
    type: String,
    default: null,
  },
  stepContainerClass: {
    type: String,
    default:
      'w-full p-8 mb-8 bg-surface border border-line-default border-solid rounded',
  },
  stepTitleClass: {
    type: String,
    default: 'text-2xl not-italic font-semibold leading-7 text-heading',
  },
  stepDescriptionClass: {
    type: String,
    default:
      'w-full mt-2.5 mb-8 text-sm not-italic leading-snug text-muted lg:w-7/12 md:w-7/12 sm:w-7/12',
  },
})
</script>
