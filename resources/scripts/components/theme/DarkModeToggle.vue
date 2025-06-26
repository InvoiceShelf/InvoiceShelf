<template>
  <div class="flex items-center space-x-3">
    <!-- Theme Icon -->
    <div class="flex-shrink-0">
      <transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 scale-75 rotate-180"
        enter-to-class="opacity-100 scale-100 rotate-0"
        leave-active-class="transition-all duration-300 ease-in"
        leave-from-class="opacity-100 scale-100 rotate-0"
        leave-to-class="opacity-0 scale-75 rotate-180"
        mode="out-in"
      >
        <BaseIcon
          v-if="themeStore.isDarkMode"
          key="moon"
          name="MoonIcon"
          :class="[
            'h-5 w-5 transition-colors duration-200',
            'text-blue-400'
          ]"
        />
        <BaseIcon
          v-else
          key="sun"
          name="SunIcon"
          :class="[
            'h-5 w-5 transition-colors duration-200',
            'text-yellow-500'
          ]"
        />
      </transition>
    </div>

    <!-- Label and Description -->
    <div v-if="showLabel" class="flex-1 min-w-0">
      <div class="flex items-center space-x-2">
        <label 
          :for="switchId" 
          class="text-sm font-medium text-gray-900 dark:text-gray-100 cursor-pointer"
        >
          {{ $t('theme.dark_mode.label') }}
        </label>
        
        <!-- Current Theme Badge -->
        <transition
          enter-active-class="transition-all duration-200 ease-out"
          enter-from-class="opacity-0 scale-75"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition-all duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-75"
        >
          <span 
            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
            :class="[
              themeStore.isDarkMode 
                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' 
                : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
            ]"
          >
            {{ themeStore.isDarkMode ? $t('theme.dark_mode.dark') : $t('theme.dark_mode.light') }}
          </span>
        </transition>
      </div>
      
      <p v-if="showDescription" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
        {{ $t('theme.dark_mode.description') }}
      </p>
    </div>

    <!-- Switch -->
    <div class="flex-shrink-0">
      <BaseSwitch
        :id="switchId"
        :model-value="themeStore.isDarkMode"
        :disabled="loading"
        class="focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
        @update:model-value="handleToggle"
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
import { computed } from 'vue'
import { useThemeStore } from '@/scripts/stores/theme'
import BaseSwitch from '@/scripts/components/base/BaseSwitch.vue'

const props = defineProps({
  /**
   * Whether to show the label and description
   */
  showLabel: {
    type: Boolean,
    default: true,
  },
  
  /**
   * Whether to show the description text
   */
  showDescription: {
    type: Boolean,
    default: true,
  },
  
  /**
   * Whether the toggle is currently loading/updating
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
    default: () => `dark-mode-toggle-${Math.random().toString(36).substr(2, 9)}`,
  },
})

const emit = defineEmits(['change'])

const themeStore = useThemeStore()

// Generate unique ID for the switch
const switchId = computed(() => props.id)

/**
 * Handle toggle event
 * @param {boolean} enabled - New dark mode state
 */
const handleToggle = (enabled) => {
  themeStore.setDarkMode(enabled)
  emit('change', enabled)
}
</script>

<style scoped>
/* Additional custom styles if needed */
.dark-mode-toggle {
  transition: all 0.2s ease-in-out;
}
</style> 