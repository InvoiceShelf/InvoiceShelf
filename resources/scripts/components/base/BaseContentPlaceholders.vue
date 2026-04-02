<template>
  <div :class="classObject">
    <slot />
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  rounded: {
    type: Boolean,
    default: false,
  },
  centered: {
    type: Boolean,
    default: false,
  },
  animated: {
    type: Boolean,
    default: true,
  },
})

const classObject = computed(() => {
  return {
    'base-content-placeholders': true,
    'base-content-placeholders-is-rounded': props.rounded,
    'base-content-placeholders-is-centered': props.centered,
    'base-content-placeholders-is-animated': props.animated,
  }
})
</script>

<style>
@keyframes vueContentPlaceholdersAnimation {
  0% {
    transform: translate3d(-30%, 0, 0);
  }
  100% {
    transform: translate3d(100%, 0, 0);
  }
}

.base-content-placeholders-heading {
  display: flex;

  [class^='base-content-placeholders-'] + & {
    margin-top: 20px;
  }
}

.base-content-placeholders-heading__img {
  margin-right: 15px;
  position: relative;
  overflow: hidden;
  min-height: 15px;
  background: #eee;

  .base-content-placeholders-is-rounded & {
    border-radius: 6px;
  }
  .base-content-placeholders-is-centered & {
    margin-left: auto;
    margin-right: auto;
  }
  .base-content-placeholders-is-animated &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100vw;
    max-width: 1000px;
    height: 100%;
    background: linear-gradient(to right, transparent 0%, #e1e1e1 15%, transparent 30%);
    animation: vueContentPlaceholdersAnimation 1.5s linear infinite forwards;
  }
}

.base-content-placeholders-heading__content {
  display: flex;
  flex: 1;
  flex-direction: column;
  justify-content: center;
}

.base-content-placeholders-heading__title {
  width: 85%;
  margin-bottom: 10px;
  background: #ccc;
  position: relative;
  overflow: hidden;
  min-height: 15px;

  .base-content-placeholders-is-rounded & {
    border-radius: 6px;
  }
  .base-content-placeholders-is-centered & {
    margin-left: auto;
    margin-right: auto;
  }
  .base-content-placeholders-is-animated &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100vw;
    max-width: 1000px;
    height: 100%;
    background: linear-gradient(to right, transparent 0%, #e1e1e1 15%, transparent 30%);
    animation: vueContentPlaceholdersAnimation 1.5s linear infinite forwards;
  }
}

.base-content-placeholders-heading__subtitle {
  width: 90%;
  position: relative;
  overflow: hidden;
  min-height: 15px;
  background: #eee;

  .base-content-placeholders-is-rounded & {
    border-radius: 6px;
  }
  .base-content-placeholders-is-centered & {
    margin-left: auto;
    margin-right: auto;
  }
  .base-content-placeholders-is-animated &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100vw;
    max-width: 1000px;
    height: 100%;
    background: linear-gradient(to right, transparent 0%, #e1e1e1 15%, transparent 30%);
    animation: vueContentPlaceholdersAnimation 1.5s linear infinite forwards;
  }
}

.base-content-placeholders-text {
  [class^='base-content-placeholders-'] + & {
    margin-top: 20px;
  }
}

.base-content-placeholders-text__line {
  width: 100%;
  margin-bottom: 10px;
  position: relative;
  overflow: hidden;
  min-height: 15px;
  background: #eee;

  .base-content-placeholders-is-rounded & {
    border-radius: 6px;
  }
  .base-content-placeholders-is-centered & {
    margin-left: auto;
    margin-right: auto;
  }
  .base-content-placeholders-is-animated &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100vw;
    max-width: 1000px;
    height: 100%;
    background: linear-gradient(to right, transparent 0%, #e1e1e1 15%, transparent 30%);
    animation: vueContentPlaceholdersAnimation 1.5s linear infinite forwards;
  }

  &:first-child {
    width: 100%;
  }
  &:nth-child(2) {
    width: 90%;
  }
  &:nth-child(3) {
    width: 80%;
  }
  &:nth-child(4) {
    width: 70%;
  }
}

.base-content-placeholders-box {
  position: relative;
  overflow: hidden;
  min-height: 15px;
  background: #eee;

  .base-content-placeholders-is-animated &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100vw;
    max-width: 1000px;
    height: 100%;
    background: linear-gradient(to right, transparent 0%, #e1e1e1 15%, transparent 30%);
    animation: vueContentPlaceholdersAnimation 1.5s linear infinite forwards;
  }
}

.base-content-circle {
  border-radius: 100%;
}

.base-content-placeholders-is-rounded {
  border-radius: 6px;
}
</style>
