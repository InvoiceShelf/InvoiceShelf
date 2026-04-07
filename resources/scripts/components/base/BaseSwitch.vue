<template>
  <SwitchGroup>
    <div class="flex flex-row items-start">
      <SwitchLabel v-if="labelLeft" class="mr-4 cursor-pointer">{{
        labelLeft
      }}</SwitchLabel>

      <Switch
        v-model="enabled"
        :class="enabled ? 'bg-primary-500' : 'bg-surface-muted'"
        class="
          relative
          inline-flex
          items-center
          h-6
          transition-colors
          rounded-full
          w-11
          focus:outline-hidden focus:ring-primary-500
        "
        v-bind="$attrs"
      >
        <span
          :class="enabled ? 'translate-x-6' : 'translate-x-1'"
          class="
            inline-block
            w-4
            h-4
            transition-transform
            bg-white
            rounded-full
          "
        />
      </Switch>

      <SwitchLabel v-if="labelRight" class="ml-4 cursor-pointer">{{
        labelRight
      }}</SwitchLabel>
    </div>
  </SwitchGroup>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Switch, SwitchGroup, SwitchLabel } from '@headlessui/vue'

interface Props {
  labelLeft?: string
  labelRight?: string
  modelValue?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  labelLeft: '',
  labelRight: '',
  modelValue: false,
})

interface Emits {
  (e: 'update:modelValue', value: boolean): void
}

const emit = defineEmits<Emits>()

const enabled = computed<boolean>({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
</script>
