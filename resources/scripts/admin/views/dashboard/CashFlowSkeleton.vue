<template>
    <div
      class="p-2 space-y-2 bg-white border border-gray-200 rounded-md shadow-sm sm:p-4 sm:space-y-4 dark:bg-gray-800 dark:border-gray-700 min-w-0"
    >
      <!-- Header -->
      <div class="flex items-start justify-between min-w-0">
        <div class="flex-1 space-y-1 sm:space-y-2 min-w-0">
          <div
            class="w-32 h-4 bg-gray-300 rounded sm:h-5 sm:w-48 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="w-40 h-2 bg-gray-200 rounded sm:h-3 sm:w-56 animate-pulse dark:bg-gray-700"
          ></div>
        </div>
        <div class="flex flex-shrink-0 space-x-1 sm:space-x-2">
          <div
            class="h-6 w-16 bg-gray-200 border border-gray-300 rounded sm:h-8 sm:w-20 animate-pulse dark:bg-gray-700 dark:border-gray-600"
          ></div>
          <div
            class="h-6 w-20 bg-gray-200 border border-gray-300 rounded sm:h-8 sm:w-24 animate-pulse dark:bg-gray-700 dark:border-gray-600"
          ></div>
        </div>
      </div>
    
      <!-- Chart Area -->
      <div
        class="relative p-2 bg-gray-50 rounded sm:p-4 min-h-[140px] sm:min-h-[220px] dark:bg-gray-700/50"
      >
        <!-- Y-axis labels -->
        <div
          class="absolute top-0 bottom-0 left-0 flex flex-col justify-between py-2 text-xs sm:py-4"
        >
          <div
            class="w-3 h-1.5 bg-gray-300 rounded sm:h-2 sm:w-4 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="w-3 h-1.5 bg-gray-300 rounded sm:h-2 sm:w-4 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="w-3 h-1.5 bg-gray-300 rounded sm:h-2 sm:w-4 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="w-3 h-1.5 bg-gray-300 rounded sm:h-2 sm:w-4 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="w-3 h-1.5 bg-gray-300 rounded sm:h-2 sm:w-4 animate-pulse dark:bg-gray-600"
          ></div>
        </div>
    
        <!-- Chart content area -->
        <div class="relative h-full ml-4 sm:ml-6 mr-2 sm:mr-4">
          <!-- Grid overlay: horizontales + verticales -->
          <div class="absolute inset-0 pointer-events-none">
            <!-- Líneas horizontales -->
            <div class="absolute inset-0 flex flex-col justify-between">
              <div class="h-px bg-gray-200 dark:bg-gray-600/50"></div>
              <div class="h-px bg-gray-200 dark:bg-gray-600/50"></div>
              <div class="h-px bg-gray-200 dark:bg-gray-600/50"></div>
              <div class="h-px bg-gray-200 dark:bg-gray-600/50"></div>
              <div class="h-px bg-gray-200 dark:bg-gray-600/50"></div>
            </div>
            <!-- Líneas verticales -->
            <div class="absolute inset-0 flex">
              <div class="flex-1 border-l border-gray-200 dark:border-gray-600/50"></div>
              <div class="flex-1 border-l border-gray-200 dark:border-gray-600/50"></div>
              <div class="flex-1 border-l border-gray-200 dark:border-gray-600/50"></div>
              <div class="flex-1 border-l border-gray-200 dark:border-gray-600/50"></div>
              <div class="flex-1 border-l border-gray-200 dark:border-gray-600/50"></div>
            </div>
          </div>
    
          <!-- SVG Chart Lines -->
          <svg
            class="absolute inset-0 w-full h-full"
            viewBox="0 0 100 100"
            preserveAspectRatio="none"
          >
            <!-- Expenses area -->
            <path
              d="M 0 85 Q 25 82 50 80 Q 75 78 100 85 L 100 100 L 0 100 Z"
              class="animate-pulse text-red-100 dark:text-red-900/50 fill-current"
              fill-opacity="0.6"
            />
            <!-- Real Income line (verde) -->
            <path
              d="M 0 70 Q 15 65 25 45 Q 35 25 45 20 Q 55 30 65 35 Q 75 40 100 45"
              class="animate-pulse text-green-500 dark:text-green-400"
              stroke="currentColor"
              stroke-width="1.5"
              fill="none"
            />
            <!-- Projected Income line (azul, dashed) -->
            <path
              d="M 0 75 Q 15 70 25 50 Q 35 30 45 25 Q 55 20 65 15 Q 75 25 85 45 Q 95 65 100 85"
              class="animate-pulse text-blue-500 dark:text-blue-400"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-dasharray="3,2"
              fill="none"
            />
            <!-- Net Cash Flow line (morado) -->
            <path
              d="M 0 65 Q 15 60 25 40 Q 35 20 45 15 Q 55 25 65 30 Q 75 35 85 50 Q 95 65 100 75"
              class="animate-pulse text-purple-500 dark:text-purple-400"
              stroke="currentColor"
              stroke-width="1.5"
              fill="none"
            />
    
            <!-- Data points -->
            <!-- Verde -->
            <circle
              cx="0"
              cy="70"
              r="1.5"
              class="animate-pulse text-green-500 dark:text-green-400 fill-current"
            />
            <circle
              cx="25"
              cy="45"
              r="1.5"
              class="animate-pulse text-green-500 dark:text-green-400 fill-current"
            />
            <circle
              cx="45"
              cy="20"
              r="1.5"
              class="animate-pulse text-green-500 dark:text-green-400 fill-current"
            />
            <circle
              cx="65"
              cy="35"
              r="1.5"
              class="animate-pulse text-green-500 dark:text-green-400 fill-current"
            />
            <circle
              cx="100"
              cy="45"
              r="1.5"
              class="animate-pulse text-green-500 dark:text-green-400 fill-current"
            />
            <!-- Azul -->
            <circle
              cx="0"
              cy="75"
              r="1.5"
              class="animate-pulse text-blue-500 dark:text-blue-400 fill-current"
            />
            <circle
              cx="25"
              cy="50"
              r="1.5"
              class="animate-pulse text-blue-500 dark:text-blue-400 fill-current"
            />
            <circle
              cx="45"
              cy="25"
              r="1.5"
              class="animate-pulse text-blue-500 dark:text-blue-400 fill-current"
            />
            <circle
              cx="65"
              cy="15"
              r="1.5"
              class="animate-pulse text-blue-500 dark:text-blue-400 fill-current"
            />
            <circle
              cx="85"
              cy="45"
              r="1.5"
              class="animate-pulse text-blue-500 dark:text-blue-400 fill-current"
            />
            <!-- Morado -->
            <circle
              cx="0"
              cy="65"
              r="1.5"
              class="animate-pulse text-purple-500 dark:text-purple-400 fill-current"
            />
            <circle
              cx="25"
              cy="40"
              r="1.5"
              class="animate-pulse text-purple-500 dark:text-purple-400 fill-current"
            />
            <circle
              cx="45"
              cy="15"
              r="1.5"
              class="animate-pulse text-purple-500 dark:text-purple-400 fill-current"
            />
            <circle
              cx="65"
              cy="30"
              r="1.5"
              class="animate-pulse text-purple-500 dark:text-purple-400 fill-current"
            />
            <circle
              cx="85"
              cy="50"
              r="1.5"
              class="animate-pulse text-purple-500 dark:text-purple-400 fill-current"
            />
          </svg>
    
          <!-- Today indicator line -->
          <div
            class="absolute left-[60%] top-0 bottom-0 w-0.5 animate-pulse opacity-70 bg-orange-400 dark:bg-orange-500"
          >
            <div
              class="absolute -top-1 left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-2 border-r-2 border-b-2 border-transparent border-b-orange-400 dark:border-b-orange-500"
            ></div>
          </div>
    
          <!-- X-axis labels -->
          <div
            class="absolute flex justify-between text-xs -bottom-4 sm:-bottom-6 left-0 right-0"
          >
            <div
              class="w-4 h-1.5 bg-gray-300 rounded sm:h-2 sm:w-6 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="w-4 h-1.5 bg-gray-300 rounded sm:h-2 sm:w-6 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="w-4 h-1.5 bg-gray-300 rounded sm:h-2 sm:w-6 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="w-4 h-1.5 bg-gray-300 rounded sm:h-2 sm:w-6 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="w-4 h-1.5 bg-gray-300 rounded sm:h-2 sm:w-6 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="w-4 h-1.5 bg-gray-300 rounded sm:h-2 sm:w-6 animate-pulse dark:bg-gray-600"
            ></div>
          </div>
        </div>
    
        <!-- Today badge -->
        <div class="absolute bottom-1 right-1 sm:bottom-2 sm:right-2">
          <div
            class="h-4 w-12 bg-yellow-200 rounded sm:h-5 sm:w-16 animate-pulse dark:bg-yellow-400/50"
          ></div>
        </div>
      </div>
    
      <!-- Legend -->
      <div
        class="flex flex-wrap justify-center gap-2 pt-2 sm:gap-4 sm:pt-4"
      >
        <div class="flex items-center space-x-1 sm:space-x-2">
          <div
            class="w-3 h-0.5 animate-pulse bg-green-400 sm:w-4 dark:bg-green-500"
          ></div>
          <div
            class="w-12 h-2 bg-gray-200 rounded sm:h-3 sm:w-16 animate-pulse dark:bg-gray-700"
          ></div>
        </div>
        <div class="flex items-center space-x-1 sm:space-x-2">
          <div
            class="w-3 h-0.5 border-dashed animate-pulse bg-blue-400 sm:w-4 dark:bg-blue-500"
          ></div>
          <div
            class="w-16 h-2 bg-gray-200 rounded sm:h-3 sm:w-20 animate-pulse dark:bg-gray-700"
          ></div>
        </div>
        <div class="flex items-center space-x-1 sm:space-x-2">
          <div
            class="w-3 h-2 bg-red-200 rounded sm:h-3 sm:w-4 animate-pulse dark:bg-red-900/50"
          ></div>
          <div
            class="w-10 h-2 bg-gray-200 rounded sm:h-3 sm:w-14 animate-pulse dark:bg-gray-700"
          ></div>
        </div>
        <div class="flex items-center space-x-1 sm:space-x-2">
          <div
            class="w-3 h-0.5 animate-pulse bg-purple-400 sm:w-4 dark:bg-purple-500"
          ></div>
          <div
            class="w-14 h-2 bg-gray-200 rounded sm:h-3 sm:w-18 animate-pulse dark:bg-gray-700"
          ></div>
        </div>
      </div>
    </div>
    </template>
    
    <script setup>
    // Skeleton de "Predictive Cash Flow Analysis" con grid lines visibles
    </script>
    