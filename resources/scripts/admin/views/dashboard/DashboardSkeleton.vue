<template>
    <div
      class="grid grid-cols-3 gap-2 p-2 bg-gray-50 sm:gap-4 sm:p-4 dark:bg-gray-800/50 min-w-0"
    >
      <!-- Summary Card -->
      <div
        class="p-2 space-y-2 bg-white border border-gray-200 rounded-md shadow-sm sm:p-4 sm:space-y-3 dark:bg-gray-700/50 dark:border-gray-600 min-w-0"
      >
        <div class="flex items-center justify-between min-w-0">
          <div
            class="flex-shrink-0 w-12 h-3 bg-gray-300 rounded sm:h-4 sm:w-16 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="inline-block px-1.5 py-0.5 text-xs text-indigo-600 bg-indigo-100 rounded-full sm:px-2 sm:py-1 flex-shrink-0"
          >
            <div
              class="w-4 h-2 bg-indigo-200 rounded sm:h-3 sm:w-6 animate-pulse dark:bg-indigo-900/50"
            ></div>
          </div>
        </div>
    
        <div class="pt-1 space-y-2 sm:pt-2 sm:space-y-3">
          <div class="flex items-center justify-between min-w-0">
            <span class="text-xs text-gray-600 truncate dark:text-gray-400 flex-1 mr-1"
              >Total invoices</span
            >
            <div
              class="flex-shrink-0 w-6 h-3 bg-gray-300 rounded sm:h-4 sm:w-8 animate-pulse dark:bg-gray-600"
            ></div>
          </div>
          <div class="flex items-center justify-between min-w-0">
            <span class="text-xs text-gray-600 truncate dark:text-gray-400 flex-1 mr-1">Sales</span>
            <div
              class="flex-shrink-0 w-8 h-3 bg-gray-300 rounded sm:h-4 sm:w-10 animate-pulse dark:bg-gray-600"
            ></div>
          </div>
          <div class="flex items-center justify-between min-w-0">
            <span class="text-xs text-gray-600 truncate dark:text-gray-400 flex-1 mr-1"
              >Receipts</span
            >
            <div
              class="flex-shrink-0 w-8 h-3 bg-gray-300 rounded sm:h-4 sm:w-10 animate-pulse dark:bg-gray-600"
            ></div>
          </div>
          <div class="flex items-center justify-between min-w-0">
            <span class="text-xs text-gray-600 truncate dark:text-gray-400 flex-1 mr-1"
              >Expenses</span
            >
            <div
              class="flex-shrink-0 w-6 h-3 bg-gray-300 rounded sm:h-4 sm:w-8 animate-pulse dark:bg-gray-600"
            ></div>
          </div>
          <div class="flex items-center justify-between min-w-0">
            <span class="text-xs text-gray-600 truncate dark:text-gray-400 flex-1 mr-1"
              >Total income</span
            >
            <div
              class="flex-shrink-0 w-8 h-3 bg-gray-300 rounded sm:h-4 sm:w-10 animate-pulse dark:bg-gray-600"
            ></div>
          </div>
        </div>
      </div>
    
      <!-- Bar Chart Card -->
      <div
        class="p-2 space-y-2 bg-white border border-gray-200 rounded-md shadow-sm sm:p-4 sm:space-y-3 dark:bg-gray-700/50 dark:border-gray-600 min-w-0"
      >
        <div
          class="w-24 h-3 bg-gray-300 rounded sm:h-4 sm:w-32 animate-pulse dark:bg-gray-600"
        ></div>
    
        <div class="flex pt-1 space-x-1 sm:space-x-2">
          <div
            class="flex-shrink-0 h-4 w-12 text-xs bg-gray-200 border rounded sm:h-6 sm:w-16 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="flex-shrink-0 h-4 w-14 text-xs bg-gray-200 border rounded sm:h-6 sm:w-18 animate-pulse dark:bg-gray-600"
          ></div>
        </div>
    
        <div class="pt-1 space-y-1.5 sm:pt-2 sm:space-y-2">
          <div class="flex items-center space-x-1 sm:space-x-2 min-w-0">
            <div
              class="flex-shrink-0 w-8 h-2 bg-gray-300 rounded sm:h-3 sm:w-12 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-3 bg-indigo-200 rounded sm:h-4 animate-pulse dark:bg-indigo-900/50"
              style="width: 60%"
            ></div>
          </div>
          <div class="flex items-center space-x-1 sm:space-x-2 min-w-0">
            <div
              class="flex-shrink-0 w-8 h-2 bg-gray-300 rounded sm:h-3 sm:w-12 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-3 bg-indigo-200 rounded sm:h-4 animate-pulse dark:bg-indigo-900/50"
              style="width: 45%"
            ></div>
          </div>
          <div class="flex items-center space-x-1 sm:space-x-2 min-w-0">
            <div
              class="flex-shrink-0 w-8 h-2 bg-gray-300 rounded sm:h-3 sm:w-12 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-3 bg-indigo-200 rounded sm:h-4 animate-pulse dark:bg-indigo-900/50"
              style="width: 70%"
            ></div>
          </div>
          <div class="flex items-center space-x-1 sm:space-x-2 min-w-0">
            <div
              class="flex-shrink-0 w-8 h-2 bg-gray-300 rounded sm:h-3 sm:w-12 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-3 bg-indigo-200 rounded sm:h-4 animate-pulse dark:bg-indigo-900/50"
              style="width: 35%"
            ></div>
          </div>
          <div class="flex items-center space-x-1 sm:space-x-2 min-w-0">
            <div
              class="flex-shrink-0 w-8 h-2 bg-gray-300 rounded sm:h-3 sm:w-12 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-3 bg-indigo-200 rounded sm:h-4 animate-pulse dark:bg-indigo-900/50"
              style="width: 50%"
            ></div>
          </div>
        </div>
    
        <!-- X-axis labels skeleton -->
        <div class="flex justify-between pt-1 space-x-0.5 text-xs">
          <div
            class="w-1 h-1.5 bg-gray-200 rounded sm:h-2 sm:w-2 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="w-2 h-1.5 bg-gray-200 rounded sm:h-2 sm:w-3 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="w-2 h-1.5 bg-gray-200 rounded sm:h-2 sm:w-4 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="w-2 h-1.5 bg-gray-200 rounded sm:h-2 sm:w-4 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="w-2 h-1.5 bg-gray-200 rounded sm:h-2 sm:w-4 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="w-2 h-1.5 bg-gray-200 rounded sm:h-2 sm:w-4 animate-pulse dark:bg-gray-600"
          ></div>
        </div>
      </div>
    
      <!-- Donut Chart Card -->
      <div
        class="p-2 space-y-2 bg-white border border-gray-200 rounded-md shadow-sm sm:p-4 sm:space-y-3 dark:bg-gray-700/50 dark:border-gray-600 min-w-0"
      >
        <div class="flex items-center justify-between min-w-0">
          <div
            class="flex-shrink-0 w-16 h-3 bg-gray-300 rounded sm:h-4 sm:w-20 animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="flex-shrink-0 h-4 w-12 bg-gray-200 border rounded sm:h-6 sm:w-16 animate-pulse dark:bg-gray-600"
          ></div>
        </div>
    
        <div class="flex justify-center py-2 sm:py-4">
          <div class="relative w-16 h-16 sm:w-24 sm:h-24">
            <!-- Donut chart skeleton -->
            <div
              class="absolute inset-0 border-[6px] rounded-full sm:border-[10px] border-gray-200 animate-pulse dark:border-gray-600"
            ></div>
            <div
              class="absolute inset-0 border-[6px] rounded-full sm:border-[10px] border-transparent border-t-indigo-200 border-r-indigo-200 border-b-indigo-300 animate-pulse dark:border-t-indigo-900/50 dark:border-r-indigo-900/50 dark:border-b-indigo-800/50"
            ></div>
          </div>
        </div>
    
        <div class="grid grid-cols-3 gap-1 pt-1 sm:gap-2">
          <div class="flex flex-col items-center space-y-1">
            <div
              class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-indigo-400 rounded-full animate-pulse dark:bg-indigo-900/50"
            ></div>
            <div
              class="w-3 h-1.5 bg-gray-200 rounded sm:h-2 sm:w-4 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="w-2 h-2 bg-gray-300 rounded sm:h-3 sm:w-3 animate-pulse dark:bg-gray-600"
            ></div>
          </div>
          <div class="flex flex-col items-center space-y-1">
            <div
              class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-indigo-200 rounded-full animate-pulse dark:bg-indigo-900/50"
            ></div>
            <div
              class="w-4 h-1.5 bg-gray-200 rounded sm:h-2 sm:w-6 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="w-2 h-2 bg-gray-300 rounded sm:h-3 sm:w-3 animate-pulse dark:bg-gray-600"
            ></div>
          </div>
          <div class="flex flex-col items-center space-y-1">
            <div
              class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-gray-400 rounded-full animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="w-4 h-1.5 bg-gray-200 rounded sm:h-2 sm:w-6 animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="w-2 h-2 bg-gray-300 rounded sm:h-3 sm:w-3 animate-pulse dark:bg-gray-600"
            ></div>
          </div>
        </div>
      </div>
    </div>
    </template>
    
    <script setup>
    // Responsive skeleton that scales proportionally without wrapping
    // Maintains form and proportions at any size
    </script>
    