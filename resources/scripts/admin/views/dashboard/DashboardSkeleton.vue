<template>
    <div class="grid grid-cols-3 gap-2 sm:gap-4 p-2 sm:p-4 bg-gray-50 min-w-0">
      <!-- Summary Card -->
      <div class="bg-white p-2 sm:p-4 rounded-md sm:rounded-lg border border-gray-200 shadow-sm space-y-2 sm:space-y-3 min-w-0">
        <div class="flex items-center justify-between min-w-0">
          <div class="h-3 sm:h-4 w-12 sm:w-16 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
          <div class="inline-block bg-indigo-100 text-indigo-600 text-xs px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full flex-shrink-0">
            <div class="h-2 sm:h-3 w-4 sm:w-6 bg-indigo-200 rounded animate-pulse"></div>
          </div>
        </div>
    
        <div class="space-y-2 sm:space-y-3 pt-1 sm:pt-2">
          <div class="flex justify-between items-center min-w-0">
            <span class="text-gray-600 text-xs truncate flex-1 mr-1">Total invoices</span>
            <div class="h-3 sm:h-4 w-6 sm:w-8 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
          </div>
          <div class="flex justify-between items-center min-w-0">
            <span class="text-gray-600 text-xs truncate flex-1 mr-1">Sales</span>
            <div class="h-3 sm:h-4 w-8 sm:w-10 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
          </div>
          <div class="flex justify-between items-center min-w-0">
            <span class="text-gray-600 text-xs truncate flex-1 mr-1">Receipts</span>
            <div class="h-3 sm:h-4 w-8 sm:w-10 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
          </div>
          <div class="flex justify-between items-center min-w-0">
            <span class="text-gray-600 text-xs truncate flex-1 mr-1">Expenses</span>
            <div class="h-3 sm:h-4 w-6 sm:w-8 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
          </div>
          <div class="flex justify-between items-center min-w-0">
            <span class="text-gray-600 text-xs truncate flex-1 mr-1">Total income</span>
            <div class="h-3 sm:h-4 w-8 sm:w-10 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
          </div>
        </div>
      </div>
    
      <!-- Bar Chart Card -->
      <div class="bg-white p-2 sm:p-4 rounded-md sm:rounded-lg border border-gray-200 shadow-sm space-y-2 sm:space-y-3 min-w-0">
        <div class="h-3 sm:h-4 w-24 sm:w-32 bg-gray-300 rounded animate-pulse"></div>
    
        <div class="flex space-x-1 sm:space-x-2 pt-1">
          <div class="h-4 sm:h-6 w-12 sm:w-16 bg-gray-200 rounded border animate-pulse text-xs flex-shrink-0"></div>
          <div class="h-4 sm:h-6 w-14 sm:w-18 bg-gray-200 rounded border animate-pulse text-xs flex-shrink-0"></div>
        </div>
    
        <div class="space-y-1.5 sm:space-y-2 pt-1 sm:pt-2">
          <div class="flex items-center space-x-1 sm:space-x-2 min-w-0">
            <div class="h-2 sm:h-3 w-8 sm:w-12 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
            <div class="h-3 sm:h-4 bg-indigo-200 rounded animate-pulse" style="width: 60%"></div>
          </div>
          <div class="flex items-center space-x-1 sm:space-x-2 min-w-0">
            <div class="h-2 sm:h-3 w-8 sm:w-12 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
            <div class="h-3 sm:h-4 bg-indigo-200 rounded animate-pulse" style="width: 45%"></div>
          </div>
          <div class="flex items-center space-x-1 sm:space-x-2 min-w-0">
            <div class="h-2 sm:h-3 w-8 sm:w-12 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
            <div class="h-3 sm:h-4 bg-indigo-200 rounded animate-pulse" style="width: 70%"></div>
          </div>
          <div class="flex items-center space-x-1 sm:space-x-2 min-w-0">
            <div class="h-2 sm:h-3 w-8 sm:w-12 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
            <div class="h-3 sm:h-4 bg-indigo-200 rounded animate-pulse" style="width: 35%"></div>
          </div>
          <div class="flex items-center space-x-1 sm:space-x-2 min-w-0">
            <div class="h-2 sm:h-3 w-8 sm:w-12 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
            <div class="h-3 sm:h-4 bg-indigo-200 rounded animate-pulse" style="width: 50%"></div>
          </div>
        </div>
    
        <!-- X-axis labels skeleton -->
        <div class="flex justify-between text-xs pt-1 space-x-0.5">
          <div class="h-1.5 sm:h-2 w-1 sm:w-2 bg-gray-200 rounded animate-pulse"></div>
          <div class="h-1.5 sm:h-2 w-2 sm:w-3 bg-gray-200 rounded animate-pulse"></div>
          <div class="h-1.5 sm:h-2 w-2 sm:w-4 bg-gray-200 rounded animate-pulse"></div>
          <div class="h-1.5 sm:h-2 w-2 sm:w-4 bg-gray-200 rounded animate-pulse"></div>
          <div class="h-1.5 sm:h-2 w-2 sm:w-4 bg-gray-200 rounded animate-pulse"></div>
          <div class="h-1.5 sm:h-2 w-2 sm:w-4 bg-gray-200 rounded animate-pulse"></div>
        </div>
      </div>
    
      <!-- Donut Chart Card -->
      <div class="bg-white p-2 sm:p-4 rounded-md sm:rounded-lg border border-gray-200 shadow-sm space-y-2 sm:space-y-3 min-w-0">
        <div class="flex items-center justify-between min-w-0">
          <div class="h-3 sm:h-4 w-16 sm:w-20 bg-gray-300 rounded animate-pulse flex-shrink-0"></div>
          <div class="h-4 sm:h-6 w-12 sm:w-16 bg-gray-200 rounded border animate-pulse flex-shrink-0"></div>
        </div>
    
        <div class="flex justify-center py-2 sm:py-4">
          <div class="relative w-16 h-16 sm:w-24 sm:h-24">
            <!-- Donut chart skeleton -->
            <div class="absolute inset-0 rounded-full border-[6px] sm:border-[10px] border-gray-200 animate-pulse"></div>
            <div class="absolute inset-0 rounded-full border-[6px] sm:border-[10px] border-transparent border-t-indigo-200 border-r-indigo-200 border-b-indigo-300 animate-pulse"></div>
          </div>
        </div>
    
        <div class="grid grid-cols-3 gap-1 sm:gap-2 pt-1">
          <div class="flex flex-col items-center space-y-1">
            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-indigo-400 rounded-full animate-pulse"></div>
            <div class="h-1.5 sm:h-2 w-3 sm:w-4 bg-gray-200 rounded animate-pulse"></div>
            <div class="h-2 sm:h-3 w-2 sm:w-3 bg-gray-300 rounded animate-pulse"></div>
          </div>
          <div class="flex flex-col items-center space-y-1">
            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-indigo-200 rounded-full animate-pulse"></div>
            <div class="h-1.5 sm:h-2 w-4 sm:w-6 bg-gray-200 rounded animate-pulse"></div>
            <div class="h-2 sm:h-3 w-2 sm:w-3 bg-gray-300 rounded animate-pulse"></div>
          </div>
          <div class="flex flex-col items-center space-y-1">
            <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-gray-400 rounded-full animate-pulse"></div>
            <div class="h-1.5 sm:h-2 w-4 sm:w-6 bg-gray-200 rounded animate-pulse"></div>
            <div class="h-2 sm:h-3 w-2 sm:w-3 bg-gray-300 rounded animate-pulse"></div>
          </div>
        </div>
      </div>
    </div>
    </template>
    
    <script setup>
    // Responsive skeleton that scales proportionally without wrapping
    // Maintains form and proportions at any size
    </script>
    