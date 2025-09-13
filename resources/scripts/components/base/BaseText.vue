<template>
  <div class="whitespace-normal">
    <BaseCustomTag :tag="tag" :title="props.text" class="line-clamp-1">
      {{ displayText }}
    </BaseCustomTag>
  </div>
</template>

<script setup>
import { computed } from "vue"

const props = defineProps({
  tag: {
    type: String,
    default: 'div',
  },

  text: {
    type: String,
    default: '',
  },

  length: {
    type: Number,
    default: null,
  }
})

const displayText = computed(() => {
  if (props.length) {
    return props.text.length <= props.length 
      ? props.text 
      : `${props.text.substring(0, props.length)}...`
  }
  return props.text
})
</script>
