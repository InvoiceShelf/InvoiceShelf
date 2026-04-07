<script setup lang="ts">

interface Props {
  currentStep?: number | null
  steps?: number | null
  containerClass?: string
  progress?: string
  currentStepClass?: string
  nextStepClass?: string
  previousStepClass?: string
  iconClass?: string
}

interface Emits {
  (e: 'click', index: number): void
}

const props = withDefaults(defineProps<Props>(), {
  currentStep: null,
  steps: null,
  containerClass: 'flex justify-between w-full my-10 max-w-xl mx-auto',
  progress: 'rounded-full float-left w-6 h-6 border-4 cursor-pointer',
  currentStepClass: 'bg-white border-primary-500',
  nextStepClass: 'border-line-default bg-surface',
  previousStepClass:
    'bg-primary-500 border-primary-500 flex justify-center items-center',
  iconClass:
    'flex items-center justify-center w-full h-full text-sm font-black text-center text-white',
})

const emit = defineEmits<Emits>()

function stepStyle(number: number): string[] {
  if (props.currentStep === number) {
    return [props.currentStepClass, props.progress]
  }
  if (props.currentStep !== null && props.currentStep > number) {
    return [props.previousStepClass, props.progress]
  }
  if (props.currentStep !== null && props.currentStep < number) {
    return [props.nextStepClass, props.progress]
  }
  return [props.progress]
}
</script>

<template>
  <div
    :class="containerClass"
    class="
      relative
      after:bg-surface-muted
      after:absolute
      after:transform
      after:top-1/2
      after:-translate-y-1/2
      after:h-2
      after:w-full
    "
  >
    <a
      v-for="(number, index) in steps"
      :key="index"
      :class="stepStyle(index)"
      class="z-10"
      href="#"
      @click.prevent="$emit('click', index)"
    >
      <svg
        v-if="currentStep !== null && currentStep > index"
        :class="iconClass"
        fill="currentColor"
        viewBox="0 0 20 20"
        @click="$emit('click', index)"
      >
        <path
          fill-rule="evenodd"
          d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
          clip-rule="evenodd"
        ></path>
      </svg>
    </a>
  </div>
</template>
