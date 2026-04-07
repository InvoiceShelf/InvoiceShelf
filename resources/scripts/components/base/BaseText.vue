<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  tag?: string
  text?: string
  length?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  tag: 'div',
  text: '',
  length: null,
})

const displayText = computed<string>(() => {
  if (props.length !== null) {
    return props.text.length <= props.length
      ? props.text
      : `${props.text.substring(0, props.length)}...`
  }
  return props.text
})
</script>

<template>
  <div class="whitespace-normal">
    <BaseCustomTag :tag="tag" :title="props.text" class="line-clamp-1">
      {{ displayText }}
    </BaseCustomTag>
  </div>
</template>
