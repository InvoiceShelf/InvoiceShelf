<template>
    <div
      class="bg-white rounded-md sm:rounded-lg border border-gray-200 shadow-sm min-w-0 dark:bg-gray-800 dark:border-gray-700"
    >
      <!-- Header -->
      <div
        class="flex items-center justify-between p-2 sm:p-4 border-b border-gray-200 dark:border-gray-700"
      >
        <div class="flex items-center space-x-2 sm:space-x-3">
          <div
            class="h-4 sm:h-5 w-20 sm:w-28 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="h-3 sm:h-4 w-16 sm:w-20 bg-gray-200 rounded animate-pulse dark:bg-gray-700"
          ></div>
        </div>
        <div
          class="h-6 sm:h-8 w-12 sm:w-16 bg-indigo-100 border border-indigo-200 rounded animate-pulse dark:bg-indigo-900/50 dark:border-indigo-800"
        ></div>
      </div>
    
      <!-- Table Header -->
      <div
        class="grid grid-cols-7 gap-1 sm:gap-2 p-2 sm:p-3 bg-gray-50 border-b border-gray-200 text-xs dark:bg-gray-700/50 dark:border-gray-700"
      >
        <div class="flex items-center space-x-1">
          <div
            class="w-2 sm:w-3 h-2 sm:h-3 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
          ></div>
          <div
            class="h-2 sm:h-3 w-8 sm:w-12 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
          ></div>
        </div>
        <div
          class="h-2 sm:h-3 w-8 sm:w-12 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
        ></div>
        <div
          class="h-2 sm:h-3 w-6 sm:w-8 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
        ></div>
        <div
          class="h-2 sm:h-3 w-12 sm:w-16 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
        ></div>
        <div
          class="h-2 sm:h-3 w-8 sm:w-12 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
        ></div>
        <div
          class="h-2 sm:h-3 w-6 sm:w-10 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
        ></div>
        <div
          class="h-2 sm:h-3 w-4 sm:w-6 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
        ></div>
      </div>
    
      <!-- Table Rows -->
      <div class="divide-y divide-gray-100 dark:divide-gray-700">
        <!-- Row 1 -->
        <div
          class="grid grid-cols-7 gap-1 sm:gap-2 p-2 sm:p-3 items-center hover:bg-gray-50 dark:hover:bg-gray-700/50"
        >
          <div class="flex items-center space-x-1 sm:space-x-2">
            <div
              class="w-2 sm:w-3 h-2 sm:h-3 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-3 sm:h-4 w-12 sm:w-16 bg-indigo-200 rounded animate-pulse dark:bg-indigo-900/50"
            ></div>
          </div>
          <div
            class="h-3 sm:h-4 w-8 sm:w-12 bg-yellow-200 rounded-full animate-pulse dark:bg-yellow-400/30"
          ></div>
          <div
            class="h-2 sm:h-3 w-10 sm:w-14 bg-gray-200 rounded animate-pulse dark:bg-gray-700"
          ></div>
          <div class="flex items-center space-x-1 sm:space-x-2">
            <div
              class="w-4 sm:w-6 h-4 sm:h-6 bg-green-300 rounded-full animate-pulse dark:bg-green-500/50"
            ></div>
            <div class="space-y-0.5 sm:space-y-1">
              <div
                class="h-2 sm:h-3 w-12 sm:w-16 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
              ></div>
              <div
                class="h-1.5 sm:h-2 w-10 sm:w-14 bg-gray-200 rounded animate-pulse dark:bg-gray-700"
              ></div>
            </div>
          </div>
          <div
            class="h-2 sm:h-3 w-6 sm:w-8 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
          ></div>
          <div class="space-y-0.5">
            <div
              class="h-2 sm:h-3 w-6 sm:w-8 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-2 sm:h-3 w-8 sm:w-10 bg-indigo-200 rounded animate-pulse dark:bg-indigo-900/50"
            ></div>
          </div>
          <div
            class="h-2 sm:h-3 w-2 sm:w-3 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
          ></div>
        </div>
    
        <!-- Row 2 -->
        <div
          class="grid grid-cols-7 gap-1 sm:gap-2 p-2 sm:p-3 items-center hover:bg-gray-50 dark:hover:bg-gray-700/50"
        >
          <div class="flex items-center space-x-1 sm:space-x-2">
            <div
              class="w-2 sm:w-3 h-2 sm:h-3 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-3 sm:h-4 w-12 sm:w-16 bg-indigo-200 rounded animate-pulse dark:bg-indigo-900/50"
            ></div>
          </div>
          <div
            class="h-3 sm:h-4 w-8 sm:w-12 bg-yellow-200 rounded-full animate-pulse dark:bg-yellow-400/30"
          ></div>
          <div
            class="h-2 sm:h-3 w-8 sm:w-12 bg-gray-200 rounded animate-pulse dark:bg-gray-700"
          ></div>
          <div class="flex items-center space-x-1 sm:space-x-2">
            <div
              class="w-4 sm:w-6 h-4 sm:h-6 bg-indigo-300 rounded-full animate-pulse dark:bg-indigo-500/50"
            ></div>
            <div class="space-y-0.5 sm:space-y-1">
              <div
                class="h-2 sm:h-3 w-14 sm:w-18 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
              ></div>
              <div
                class="h-1.5 sm:h-2 w-8 sm:w-12 bg-gray-200 rounded animate-pulse dark:bg-gray-700"
              ></div>
            </div>
          </div>
          <div
            class="h-2 sm:h-3 w-6 sm:w-8 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
          ></div>
          <div class="space-y-0.5">
            <div
              class="h-2 sm:h-3 w-6 sm:w-8 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-2 sm:h-3 w-8 sm:w-10 bg-indigo-200 rounded animate-pulse dark:bg-indigo-900/50"
            ></div>
          </div>
          <div
            class="h-2 sm:h-3 w-2 sm:w-3 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
          ></div>
        </div>
    
        <!-- Row 3 -->
        <div
          class="grid grid-cols-7 gap-1 sm:gap-2 p-2 sm:p-3 items-center hover:bg-gray-50 dark:hover:bg-gray-700/50"
        >
          <div class="flex items-center space-x-1 sm:space-x-2">
            <div
              class="w-2 sm:w-3 h-2 sm:h-3 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-3 sm:h-4 w-12 sm:w-16 bg-indigo-200 rounded animate-pulse dark:bg-indigo-900/50"
            ></div>
          </div>
          <div
            class="h-3 sm:h-4 w-8 sm:w-12 bg-yellow-200 rounded-full animate-pulse dark:bg-yellow-400/30"
          ></div>
          <div
            class="h-2 sm:h-3 w-9 sm:w-13 bg-gray-200 rounded animate-pulse dark:bg-gray-700"
          ></div>
          <div class="flex items-center space-x-1 sm:space-x-2">
            <div
              class="w-4 sm:w-6 h-4 sm:h-6 bg-purple-300 rounded-full animate-pulse dark:bg-purple-500/50"
            ></div>
            <div class="space-y-0.5 sm:space-y-1">
              <div
                class="h-2 sm:h-3 w-16 sm:w-20 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
              ></div>
              <div
                class="h-1.5 sm:h-2 w-10 sm:w-14 bg-gray-200 rounded animate-pulse dark:bg-gray-700"
              ></div>
            </div>
          </div>
          <div
            class="h-2 sm:h-3 w-6 sm:w-8 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
          ></div>
          <div class="space-y-0.5">
            <div
              class="h-2 sm:h-3 w-6 sm:w-8 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-2 sm:h-3 w-10 sm:w-12 bg-yellow-200 rounded animate-pulse dark:bg-yellow-400/30"
            ></div>
          </div>
          <div
            class="h-2 sm:h-3 w-2 sm:w-3 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
          ></div>
        </div>
    
        <!-- Row 4 -->
        <div
          class="grid grid-cols-7 gap-1 sm:gap-2 p-2 sm:p-3 items-center hover:bg-gray-50 dark:hover:bg-gray-700/50"
        >
          <div class="flex items-center space-x-1 sm:space-x-2">
            <div
              class="w-2 sm:w-3 h-2 sm:h-3 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-3 sm:h-4 w-12 sm:w-16 bg-indigo-200 rounded animate-pulse dark:bg-indigo-900/50"
            ></div>
          </div>
          <div
            class="h-3 sm:h-4 w-8 sm:w-12 bg-yellow-200 rounded-full animate-pulse dark:bg-yellow-400/30"
          ></div>
          <div
            class="h-2 sm:h-3 w-10 sm:w-14 bg-gray-200 rounded animate-pulse dark:bg-gray-700"
          ></div>
          <div class="flex items-center space-x-1 sm:space-x-2">
            <div
              class="w-4 sm:w-6 h-4 sm:h-6 bg-green-300 rounded-full animate-pulse dark:bg-green-500/50"
            ></div>
            <div class="space-y-0.5 sm:space-y-1">
              <div
                class="h-2 sm:h-3 w-12 sm:w-16 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
              ></div>
              <div
                class="h-1.5 sm:h-2 w-10 sm:w-14 bg-gray-200 rounded animate-pulse dark:bg-gray-700"
              ></div>
            </div>
          </div>
          <div
            class="h-2 sm:h-3 w-6 sm:w-8 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
          ></div>
          <div class="space-y-0.5">
            <div
              class="h-2 sm:h-3 w-6 sm:w-8 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
            ></div>
            <div
              class="h-2 sm:h-3 w-10 sm:w-12 bg-yellow-200 rounded animate-pulse dark:bg-yellow-400/30"
            ></div>
          </div>
          <div
            class="h-2 sm:h-3 w-2 sm:w-3 bg-gray-300 rounded animate-pulse dark:bg-gray-600"
          ></div>
        </div>
      </div>
    </div>
    </template>
    
    <script setup>
    // Invoice table skeleton that scales proportionally without wrapping
    // Maintains table structure and proportions at any size
    </script>
    