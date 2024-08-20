<template>
  <div class="whitespace-normal">
    <BaseCustomTag :tag="tag" :title="text" class="line-clamp-1">
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

const { text, length } = props;

const displayText = computed(() => {
  if (length) {
    return text.length <= length ? text : `${text.substring(0, length)}...`;
  }
  return text;
});

</script>
