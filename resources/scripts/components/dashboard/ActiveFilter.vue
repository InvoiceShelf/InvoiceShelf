<template>
  <div class="flex items-center space-x-3 p-3 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
    <!-- Filter Icon -->
    <div class="flex-shrink-0">
      <BaseIcon 
        name="FunnelIcon" 
        :class="[
          'h-5 w-5 transition-colors duration-200',
          isActive ? 'text-primary-500' : 'text-gray-400'
        ]" 
      />
    </div>

    <!-- Filter Label and Description -->
    <div class="flex-1 min-w-0">
      <div class="flex items-center space-x-2">
        <label 
          :for="switchId" 
          class="text-sm font-medium text-gray-900 dark:text-gray-100 cursor-pointer"
        >
          {{ $t('dashboard.active_filter.label') }}
        </label>
        
        <!-- Active Indicator Badge -->
        <transition
          enter-active-class="transition-all duration-200 ease-out"
          enter-from-class="opacity-0 scale-75"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition-all duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-75"
        >
          <span 
            v-if="isActive"
            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200"
          >
            {{ $t('dashboard.active_filter.active') }}
          </span>
        </transition>
      </div>
      
      <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
        {{ $t('dashboard.active_filter.description') }}
      </p>
    </div>

    <!-- Switch -->
    <div class="flex-shrink-0">
      <BaseSwitch
        :id="switchId"
        v-model="isActive"
        :disabled="loading"
        class="focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
      />
    </div>

    <!-- Loading Indicator -->
    <transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="loading" class="flex-shrink-0">
        <BaseIcon name="ArrowPathIcon" class="h-4 w-4 text-gray-400 animate-spin" />
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import BaseSwitch from '@/scripts/components/base/BaseSwitch.vue'

const props = defineProps({
  /**
   * Current state of the active filter
   */
  modelValue: {
    type: Boolean,
    default: false,
  },
  
  /**
   * Whether the filter is currently loading/updating
   */
  loading: {
    type: Boolean,
    default: false,
  },
  
  /**
   * Unique identifier for the switch element
   */
  id: {
    type: String,
    default: () => `active-filter-${Math.random().toString(36).substr(2, 9)}`,
  },
})

const emit = defineEmits(['update:modelValue'])

// Generate unique ID for the switch
const switchId = computed(() => props.id)

// Reactive state for the switch
const isActive = computed({
  get: () => props.modelValue,
  set: (value) => {
    emit('update:modelValue', value)
  },
})
</script>

<style scoped>
/* Additional custom styles if needed */
.active-filter-container {
  transition: all 0.2s ease-in-out;
}

.active-filter-container:hover {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}
</style> 